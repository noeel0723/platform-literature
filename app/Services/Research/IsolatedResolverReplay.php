<?php

namespace App\Services\Research;

use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use App\Services\Literature\SemanticLiteratureResolver;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

final class IsolatedResolverReplay
{
    public const CONNECTION = 'research_resolution_sandbox';

    /** Rebuild a fresh, empty SQLite memory database for EVERY order; never reset a configured database. */
    public function replay(array $records, array $order): array
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            throw new InvalidArgumentException('Research replay is restricted to local/development/testing.');
        }
        if (count($records) !== count($order) || array_diff(array_keys($records), $order) !== [] || count(array_unique($order)) !== count($order)) {
            throw new InvalidArgumentException('Replay order must contain every frozen source identity exactly once.');
        }
        $previous = DB::getDefaultConnection();
        $previousConfig = config('database.connections.'.self::CONNECTION);
        $previousHttp = Http::getFacadeRoot();
        $http = new Factory;
        $http->preventStrayRequests();
        Http::swap($http);
        config(['database.connections.'.self::CONNECTION => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge(self::CONNECTION);
        DB::setDefaultConnection(self::CONNECTION);
        Schema::clearResolvedInstance('db.schema');
        try {
            if (DB::connection()->getDatabaseName() !== ':memory:' || DB::connection()->getDriverName() !== 'sqlite') {
                throw new RuntimeException('Refusing replay outside the ephemeral memory database.');
            }
            $status = Artisan::call('migrate', ['--database' => self::CONNECTION, '--force' => true, '--no-interaction' => true]);
            if ($status !== 0) {
                throw new RuntimeException('Sandbox migrations failed.');
            }
            $this->seed($records);
            $resolver = app(SemanticLiteratureResolver::class);
            foreach ($order as $key) {
                $resolver->resolve(Literature::query()->findOrFail($records[$key]['attributes']['id']));
            }
            $byId = LiteratureSourceMapping::query()->get()->keyBy('literature_id');
            $members = [];
            foreach ($records as $key => $record) {
                $mapping = $byId->get($record['attributes']['id']);
                if ($mapping === null) {
                    throw new RuntimeException('Resolver failed to produce a mapping.');
                }
                $members[$mapping->canonical_work_id][] = $key;
            }
            $result = [];
            foreach ($records as $key => $record) {
                $mapping = $byId[$record['attributes']['id']];
                $cluster = $members[$mapping->canonical_work_id];
                sort($cluster, SORT_STRING);
                $result[$key] = [
                    'canonical_id' => $mapping->canonical_work_id,
                    'cluster_key' => hash('sha256', json_encode($cluster, JSON_THROW_ON_ERROR)),
                    'match_method' => $mapping->match_method, 'mapping_status' => $mapping->mapping_status,
                    'confidence' => $mapping->confidence,
                ];
            }

            return $result;
        } finally {
            DB::setDefaultConnection($previous);
            app('migrator')->setConnection($previous);
            DB::purge(self::CONNECTION);
            config(['database.connections.'.self::CONNECTION => $previousConfig]);
            Schema::clearResolvedInstance('db.schema');
            Http::swap($previousHttp);
        }
    }

    private function seed(array $records): void
    {
        $sources = [];
        $authors = [];
        $categories = [];
        foreach ($records as $record) {
            $sources[$record['source']['id']] = $record['source'];
            foreach ($record['authors'] as $author) {
                $authors[$author['id']] = array_intersect_key($author, array_flip(['id', 'name', 'normalized_name', 'external_entity_id'])) + ['slug' => 'research-author-'.$author['id']];
            }
            foreach ($record['categories'] as $category) {
                $categories[$category['id']] = $category;
            }
        }
        foreach ($sources as $source) {
            DB::table('api_sources')->insert($source + ['supported_types' => '[]', 'is_active' => true]);
        }
        foreach ($authors as $author) {
            DB::table('authors')->insert($author);
        }
        foreach ($categories as $category) {
            DB::table('categories')->insert($category);
        }
        foreach ($records as $record) {
            $attributes = $record['attributes'];
            $attributes['slug'] = 'research-literature-'.$attributes['id'];
            $attributes['api_source_id'] = $record['source']['id'];
            DB::table('literatures')->insert($attributes);
            foreach ($record['authors'] as $author) {
                DB::table('author_literature')->insert([
                    'literature_id' => $attributes['id'], 'author_id' => $author['id'],
                    'position' => $author['position'], 'role' => $author['role'],
                ]);
            }
            foreach ($record['categories'] as $category) {
                DB::table('category_literature')->insert(['literature_id' => $attributes['id'], 'category_id' => $category['id']]);
            }
        }
    }
}
