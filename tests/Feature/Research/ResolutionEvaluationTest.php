<?php

namespace Tests\Feature\Research;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Literature;
use App\Services\Literature\SemanticLiteratureResolver;
use App\Services\Research\CandidatePairExporter;
use App\Services\Research\EvaluationFiles;
use App\Services\Research\IsolatedResolverReplay;
use App\Services\Research\PairDataset;
use App\Services\Research\ResolutionEvaluationRunner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ResolutionEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            File::deleteDirectory($directory);
        }
        parent::tearDown();
    }

    public function test_export_uses_unmapped_cross_source_records_and_never_invents_ground_truth(): void
    {
        Http::preventStrayRequests();
        $author = Author::factory()->create();
        $first = $this->record('anilist', 'A', 'One Piece', $author);
        $this->record('kitsu', 'B', 'One-Piece', $author);
        $first->forceFill(['cover_url' => 'https://user:secret@example.test/image?api_key=secret&zoom=1'])->save();
        app(SemanticLiteratureResolver::class)->resolve($first);
        $before = DB::table('literature_source_mappings')->get()->toJson();
        $directory = $this->directory();
        $manifest = app(CandidatePairExporter::class)->export($directory, 180, 'test');
        $dataset = app(PairDataset::class)->load($directory.'/snapshot.json', $directory.'/candidate_pairs.csv');

        $this->assertSame(1, $manifest['exported_pairs']);
        $this->assertSame(2, $manifest['snapshot_records']);
        $this->assertSame('UNLABELED', $dataset['pairs'][0]['ground_truth_label']);
        $this->assertNotSame($dataset['pairs'][0]['source_a'], $dataset['pairs'][0]['source_b']);
        $snapshot = file_get_contents($directory.'/snapshot.json');
        $this->assertStringNotContainsString('canonical_work_id', $snapshot);
        $this->assertStringNotContainsString('secret', $snapshot);
        $this->assertStringNotContainsString('api_key', $snapshot);
        $this->assertSame($before, DB::table('literature_source_mappings')->get()->toJson());
        Http::assertNothingSent();
    }

    public function test_sampling_and_snapshot_are_repeatable_for_a_frozen_catalog_and_seed(): void
    {
        $author = Author::factory()->create();
        $this->record('anilist', 'A', 'One Piece', $author);
        $this->record('kitsu', 'B', 'One Piece', $author);
        $this->record('google-books', 'C', 'One Other Story', null);
        $a = $this->directory();
        $b = $this->directory();
        $exporter = app(CandidatePairExporter::class);
        $exporter->export($a, 2, 'fixed-seed');
        $exporter->export($b, 2, 'fixed-seed');
        $this->assertSame(file_get_contents($a.'/candidate_pairs.csv'), file_get_contents($b.'/candidate_pairs.csv'));
        $this->assertSame(hash_file('sha256', $a.'/snapshot.json'), hash_file('sha256', $b.'/snapshot.json'));
    }

    public function test_unlabeled_dataset_cannot_generate_evaluation_scores(): void
    {
        $this->record('anilist', 'A', 'Home', null);
        $this->record('kitsu', 'B', 'Home', null);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $output = $this->directory();
        $this->artisan('research:evaluate', ['--snapshot' => $export.'/snapshot.json', '--labels' => $export.'/candidate_pairs.csv', '--out' => $output])
            ->expectsOutputToContain('No manually labeled pairs')->assertFailed();
        $this->assertDirectoryDoesNotExist($output);
    }

    public function test_actual_canonical_equality_not_matched_status_drives_prediction_and_source_is_untouched(): void
    {
        Http::preventStrayRequests();
        $a = $this->record('anilist', 'A', 'Home', Author::factory()->create());
        $this->record('kitsu', 'B', 'Home', Author::factory()->create());
        app(SemanticLiteratureResolver::class)->resolve($a);
        $before = DB::table('literature_source_mappings')->get()->toJson();
        $sourceName = DB::getDefaultConnection();
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $this->labelAll($export, 'DIFFERENT_WORK');
        $output = $this->directory();
        $manifest = app(ResolutionEvaluationRunner::class)->run($export.'/snapshot.json', $export.'/labels.csv', $output);
        $metrics = json_decode(file_get_contents($output.'/metrics.json'), true);
        $proposed = array_values(array_filter($metrics, fn (array $row): bool => $row['method'] === 'proposed'));
        $this->assertCount(3, $proposed);
        foreach ($proposed as $row) {
            $this->assertSame(1, $row['tn']);
            $this->assertSame(0, $row['fp']);
            $this->assertSame(1, $row['n']);
        }
        $assignments = json_decode(file_get_contents($output.'/canonical_assignments.json'), true);
        $this->assertContains('matched', array_column($assignments, 'mapping_status'));
        $this->assertSame('EVALUATED_LABELED_DATASET', $manifest['status']);
        $this->assertSame($sourceName, DB::getDefaultConnection());
        $this->assertSame($before, DB::table('literature_source_mappings')->get()->toJson());
        $this->assertDatabaseCount('literatures', 2);
        $this->assertSame(1, $metrics[0]['fp']);
        Http::assertNothingSent();
    }

    public function test_shared_isbn_uses_the_original_resolver_and_every_method_scores_the_same_pair(): void
    {
        $this->record('google-books', 'A', 'The Original Story', null, 'novel', 1950, '9780064471046');
        $this->record('hardcover', 'B', 'A Different Edition Title', null, 'novel', 2000, 'ISBN-13: 978-0-06-447104-6');
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $this->labelAll($export, 'SAME_WORK');
        $output = $this->directory();
        app(ResolutionEvaluationRunner::class)->run($export.'/snapshot.json', $export.'/labels.csv', $output);
        $metrics = json_decode(file_get_contents($output.'/metrics.json'), true);
        $this->assertCount(5, $metrics);
        foreach ($metrics as $row) {
            $this->assertSame(1, $row['n']);
            $this->assertSame($row['method'] === 'proposed' ? 1 : 0, $row['tp']);
        }
        $this->assertDatabaseCount('canonical_works', 0);
    }

    public function test_prediction_only_reports_order_sensitivity_without_publishing_metrics(): void
    {
        $author = Author::factory()->create();
        $this->record('anilist', 'A', 'Story', $author, 'manga', null);
        $this->record('google-books', 'B', 'Story', $author, 'manga', 2000);
        $this->record('kitsu', 'C', 'Story', $author, 'manga', 2001);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $runner = app(ResolutionEvaluationRunner::class);
        $one = $this->directory();
        $two = $this->directory();
        $manifest = $runner->run($export.'/snapshot.json', $export.'/candidate_pairs.csv', $one, 'order-seed', true);
        $runner->run($export.'/snapshot.json', $export.'/candidate_pairs.csv', $two, 'order-seed', true);
        $this->assertSame('PREDICTIONS_ONLY_NOT_EVALUATED', $manifest['status']);
        $this->assertSame(0, $manifest['scored_pairs_per_method']);
        $this->assertGreaterThan(0, $manifest['order_sensitive_pairs']);
        $this->assertSame([], json_decode(file_get_contents($one.'/metrics.json'), true));
        $this->assertSame(file_get_contents($one.'/predictions.json'), file_get_contents($two.'/predictions.json'));
        $this->assertSame(file_get_contents($one.'/canonical_assignments.json'), file_get_contents($two.'/canonical_assignments.json'));
        $this->assertDatabaseCount('literature_source_mappings', 0);
    }

    public function test_invalid_label_is_rejected_before_replay(): void
    {
        $this->record('anilist', 'A', 'Story', null);
        $this->record('kitsu', 'B', 'Story', null);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $this->labelAll($export, 'MATCHED');
        $this->expectException(InvalidArgumentException::class);
        app(PairDataset::class)->load($export.'/snapshot.json', $export.'/labels.csv');
    }

    public function test_duplicate_pairs_are_rejected_instead_of_double_counted(): void
    {
        $this->record('anilist', 'A', 'Story', null);
        $this->record('kitsu', 'B', 'Story', null);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $data = app(PairDataset::class)->load($export.'/snapshot.json', $export.'/candidate_pairs.csv');
        EvaluationFiles::csv($export.'/duplicates.csv', PairDataset::COLUMNS, [$data['pairs'][0], $data['pairs'][0]]);
        $this->expectException(InvalidArgumentException::class);
        app(PairDataset::class)->load($export.'/snapshot.json', $export.'/duplicates.csv');
    }

    public function test_tampered_snapshot_is_rejected(): void
    {
        $this->record('anilist', 'A', 'Story', null);
        $this->record('kitsu', 'B', 'Story', null);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        file_put_contents($export.'/snapshot.json', file_get_contents($export.'/snapshot.json')."\n");
        $this->expectException(InvalidArgumentException::class);
        app(PairDataset::class)->load($export.'/snapshot.json', $export.'/candidate_pairs.csv');
    }

    public function test_production_replay_is_refused_before_any_connection_change(): void
    {
        $original = DB::getDefaultConnection();
        $this->app->instance('env', 'production');
        try {
            app(IsolatedResolverReplay::class)->replay([], []);
            $this->fail('Production must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('restricted', $exception->getMessage());
            $this->assertSame($original, DB::getDefaultConnection());
        }
    }

    public function test_metadata_edits_in_label_csv_are_rejected(): void
    {
        $this->record('anilist', 'A', 'Story', null);
        $this->record('kitsu', 'B', 'Story', null);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $data = app(PairDataset::class)->load($export.'/snapshot.json', $export.'/candidate_pairs.csv');
        $data['pairs'][0]['title_a'] = 'Edited title';
        EvaluationFiles::csv($export.'/edited.csv', PairDataset::COLUMNS, $data['pairs']);
        $this->expectException(InvalidArgumentException::class);
        app(PairDataset::class)->load($export.'/snapshot.json', $export.'/edited.csv');
    }

    public function test_contradictory_transitive_manual_labels_are_rejected(): void
    {
        $this->record('anilist', 'A', 'Story', null);
        $this->record('google-books', 'B', 'Story', null);
        $this->record('kitsu', 'C', 'Story', null);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $data = app(PairDataset::class)->load($export.'/snapshot.json', $export.'/candidate_pairs.csv');
        $rows = array_map(fn (array $row): array => array_replace($row, [
            'ground_truth_label' => ($row['external_id_a'] === 'A' && $row['external_id_b'] === 'C') ? 'DIFFERENT_WORK' : 'SAME_WORK',
            'annotator_id' => 'synthetic-fixture', 'labeling_notes' => 'Synthetic contradiction fixture.',
        ]), $data['pairs']);
        EvaluationFiles::csv($export.'/conflict.csv', PairDataset::COLUMNS, $rows);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Contradictory manual labels');
        app(PairDataset::class)->load($export.'/snapshot.json', $export.'/conflict.csv');
    }

    public function test_partial_labels_are_scored_equally_and_uncertain_pairs_are_not_guessed(): void
    {
        $this->record('anilist', 'A', 'Story', null);
        $this->record('google-books', 'B', 'Story', null);
        $this->record('kitsu', 'C', 'Story', null);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $data = app(PairDataset::class)->load($export.'/snapshot.json', $export.'/candidate_pairs.csv');
        $data['pairs'][0] = array_replace($data['pairs'][0], ['ground_truth_label' => 'DIFFERENT_WORK', 'annotator_id' => 'synthetic', 'labeling_notes' => 'Fixture only']);
        $data['pairs'][1]['ground_truth_label'] = 'UNCERTAIN';
        EvaluationFiles::csv($export.'/partial.csv', PairDataset::COLUMNS, $data['pairs']);
        $output = $this->directory();
        $manifest = app(ResolutionEvaluationRunner::class)->run($export.'/snapshot.json', $export.'/partial.csv', $output);
        $this->assertSame('PARTIAL_LABELS_EVALUATED', $manifest['status']);
        $this->assertSame(1, $manifest['labeled_pairs']);
        $this->assertSame(1, $manifest['label_counts']['UNCERTAIN']);
        $this->assertSame(1, $manifest['label_counts']['UNLABELED']);
        foreach (json_decode(file_get_contents($output.'/metrics.json'), true) as $metric) {
            $this->assertSame(1, $metric['n']);
        }
    }

    public function test_sandbox_exception_restores_the_primary_connection_without_changing_source_records(): void
    {
        $this->record('anilist', 'A', 'Story', null);
        $this->record('kitsu', 'B', 'Story', null);
        $export = $this->directory();
        app(CandidatePairExporter::class)->export($export);
        $data = app(PairDataset::class)->load($export.'/snapshot.json', $export.'/candidate_pairs.csv');
        $records = $data['records'];
        $key = array_key_first($records);
        $records[$key]['attributes']['title'] = null;
        $original = DB::getDefaultConnection();
        try {
            app(IsolatedResolverReplay::class)->replay($records, array_keys($records));
            $this->fail('Invalid seed must fail.');
        } catch (QueryException) {
            $this->assertSame($original, DB::getDefaultConnection());
            $this->assertDatabaseCount('literatures', 2);
            $this->assertDatabaseCount('canonical_works', 0);
        }
    }

    public function test_existing_outputs_are_never_overwritten(): void
    {
        $directory = $this->directory();
        mkdir($directory);
        file_put_contents($directory.'/preserve.txt', 'keep');
        try {
            EvaluationFiles::newDirectory($directory);
            $this->fail('Existing output directory must be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame('keep', file_get_contents($directory.'/preserve.txt'));
        }
    }

    public function test_known_static_provider_demo_records_are_excluded_from_the_research_corpus(): void
    {
        $this->record('anilist', '30012', 'Static seeder example', null);
        $this->record('google-books', 'isbn-9780261102217', 'Static seeder example', null);
        $this->record('anilist', 'A', 'Imported title', null);
        $this->record('kitsu', 'B', 'Imported title', null);
        $directory = $this->directory();
        $manifest = app(CandidatePairExporter::class)->export($directory);
        $data = app(PairDataset::class)->load($directory.'/snapshot.json', $directory.'/candidate_pairs.csv');
        $this->assertSame(2, $manifest['snapshot_records']);
        $this->assertSame(1, $manifest['exported_pairs']);
        $this->assertSame(['A', 'B'], array_column(array_column($data['snapshot']['records'], 'attributes'), 'external_id'));
        $this->assertDatabaseCount('literatures', 4);
    }

    private function record(string $source, string $externalId, string $title, ?Author $author, string $type = 'manga', ?int $year = 2000, ?string $identifier = null): Literature
    {
        $provider = ApiSource::query()->firstOrCreate(['key' => $source], ['name' => $source, 'supported_types' => [$type]]);
        $record = Literature::factory()->for($provider)->create([
            'external_id' => $externalId, 'title' => $title, 'type' => $type,
            'publication_year' => $year, 'identifier' => $identifier,
        ]);
        if ($author !== null) {
            $record->authors()->attach($author, ['role' => 'author', 'position' => 0]);
        }

        return $record;
    }

    private function directory(): string
    {
        $path = sys_get_temp_dir().'/literahaven-evaluation-test-'.bin2hex(random_bytes(8));
        $this->directories[] = $path;

        return $path;
    }

    /** Synthetic fixture labels ONLY; these files never enter the empirical research dataset. */
    private function labelAll(string $directory, string $label): void
    {
        $dataset = app(PairDataset::class)->load($directory.'/snapshot.json', $directory.'/candidate_pairs.csv');
        $rows = array_map(fn (array $row): array => array_replace($row, [
            'ground_truth_label' => $label, 'annotator_id' => 'synthetic-test-fixture',
            'labeling_notes' => 'Explicit synthetic test fixture, not empirical ground truth.',
        ]), $dataset['pairs']);
        EvaluationFiles::csv($directory.'/labels.csv', PairDataset::COLUMNS, $rows);
    }
}
