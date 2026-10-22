<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class UserAccountReset
{
    public const PERSONAL_TABLES = [
        'reports', 'likes', 'activities', 'comments', 'discussions', 'reviews',
        'reading_logs', 'reading_progress', 'reading_lists', 'custom_list_items', 'custom_lists',
        'user_favorite_literatures', 'user_favorite_authors', 'follows', 'user_blocks',
        'notifications', 'personal_access_tokens', 'password_reset_tokens', 'sessions', 'users',
    ];

    public const CATALOG_TABLES = [
        'api_sources', 'authors', 'author_aliases', 'categories', 'literatures',
        'author_literature', 'category_literature', 'literature_relations', 'canonical_works',
        'canonical_work_identifiers', 'canonical_work_links', 'literature_source_mappings',
        'literature_metadata_overrides',
    ];

    /** @return array<string, int> */
    public function inspect(): array
    {
        $connection = DB::connection();
        $schema = $connection->getSchemaBuilder();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException('Account reset supports MySQL, MariaDB and SQLite only.');
        }
        $tableMetadata = $schema->getTables(in_array($driver, ['mysql', 'mariadb'], true) ? $connection->getDatabaseName() : null);
        $tables = array_column($tableMetadata, 'name');

        if ($driver === 'sqlite') {
            $constraintsEnabled = (int) $connection->selectOne('PRAGMA foreign_keys')->foreign_keys === 1;
            $hasTriggers = $connection->table('sqlite_master')->where('type', 'trigger')->whereIn('tbl_name', self::PERSONAL_TABLES)->exists();
        } else {
            $constraintsEnabled = (int) $connection->selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled === 1;
            $hasTriggers = $connection->table('information_schema.triggers')
                ->where('trigger_schema', $connection->getDatabaseName())
                ->whereIn('event_object_table', self::PERSONAL_TABLES)->exists();

            if ($connection->table('information_schema.key_column_usage')
                ->where('referenced_table_schema', $connection->getDatabaseName())
                ->where('table_schema', '<>', $connection->getDatabaseName())
                ->whereIn('referenced_table_name', self::PERSONAL_TABLES)->exists()) {
                throw new RuntimeException('Cross-database foreign keys reference user data. Review them before resetting.');
            }

            foreach ($tableMetadata as $table) {
                if (strtolower($table['engine'] ?? '') !== 'innodb') {
                    throw new RuntimeException('All tables must use InnoDB for a transactional reset and consistent backup.');
                }
            }
        }

        if (! $constraintsEnabled) {
            throw new RuntimeException('Foreign key constraints must be enabled before resetting accounts.');
        }

        if ($hasTriggers) {
            throw new RuntimeException('Unreviewed triggers exist on personal tables. Review their catalog side effects before resetting.');
        }
        $known = [...self::PERSONAL_TABLES, ...self::CATALOG_TABLES, 'migrations', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'job_batches'];
        $unknown = array_diff($tables, $known);

        if ($unknown !== []) {
            throw new RuntimeException('Unreviewed tables found: '.implode(', ', $unknown).'. Review their ownership before resetting.');
        }

        if (config('session.driver') !== 'database'
            || (config('session.connection') !== null && config('session.connection') !== $connection->getName())
            || config('session.table') !== 'sessions') {
            throw new RuntimeException('Reset requires database sessions on the default connection using the sessions table.');
        }

        foreach (['jobs', 'failed_jobs', 'job_batches'] as $table) {
            if (in_array($table, $tables, true) && $connection->table($table)->exists()) {
                throw new RuntimeException('Drain and review pending, failed and batched jobs before resetting accounts. Stop queue workers first.');
            }
        }

        foreach ($tables as $table) {
            foreach ($schema->getForeignKeys($table) as $foreignKey) {
                if (in_array($table, self::CATALOG_TABLES, true)
                    && in_array($foreignKey['foreign_table'], self::PERSONAL_TABLES, true)
                    && ! ($table === 'literature_metadata_overrides'
                        && $foreignKey['columns'] === ['edited_by']
                        && $foreignKey['foreign_table'] === 'users'
                        && strtolower($foreignKey['on_delete']) === 'set null')) {
                    throw new RuntimeException('An unsafe catalog foreign key was found on '.$table.'. No accounts were deleted.');
                }
            }
        }

        return collect(self::PERSONAL_TABLES)->filter(fn (string $table): bool => in_array($table, $tables, true))
            ->mapWithKeys(fn (string $table): array => [$table => $connection->table($table)->count()])->all();
    }

    /** @return list<string> */
    public function avatarPaths(): array
    {
        $paths = User::query()->whereNotNull('avatar_path')->pluck('avatar_path')->unique()->values()->all();

        foreach ($paths as $path) {
            if (! preg_match('#\Aavatars/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z#', $path)) {
                throw new RuntimeException('An unexpected avatar path was found. Review it before resetting accounts.');
            }
        }

        return $paths;
    }

    /** @param list<string> $tables */
    public function delete(array $tables): void
    {
        $this->inspect();
        $connection = DB::connection();
        $catalogCounts = collect(self::CATALOG_TABLES)->mapWithKeys(
            fn (string $table): array => [$table => $connection->table($table)->count()],
        );

        $connection->transaction(function () use ($connection, $tables, $catalogCounts): void {
            foreach ($tables as $table) {
                if (! in_array($table, self::PERSONAL_TABLES, true)) {
                    throw new RuntimeException('Refusing to delete a non-personal table.');
                }

                $connection->table($table)->delete();
            }

            foreach ($tables as $table) {
                if ($connection->table($table)->exists()) {
                    throw new RuntimeException('User data remains in '.$table.'. Reset rolled back.');
                }
            }

            foreach ($catalogCounts as $table => $count) {
                if ($connection->table($table)->count() !== $count) {
                    throw new RuntimeException('Catalog row count changed in '.$table.'. Reset rolled back.');
                }
            }
        });
    }

    /** @param list<string> $paths */
    public function removeAvatars(array $paths): bool
    {
        return $paths === [] || Storage::disk('public')->delete($paths);
    }
}
