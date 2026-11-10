<?php

namespace Tests\Feature\Research;

use App\Models\ApiSource;
use App\Models\Author;
use App\Models\Literature;
use App\Services\Research\CandidatePairExporter;
use App\Services\Research\IsolatedResolverReplay;
use App\Services\Research\LabelingWorkspace;
use App\Services\Research\LocalLabelingServer;
use App\Services\Research\ResolutionEvaluationRunner;
use App\Services\Research\StudyDataset;
use App\Services\Research\StudyFiles;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManualStudyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/manual-study-'.bin2hex(random_bytes(8)));
        config(['research.root' => $this->root]);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }

    private function exportStudy(): array
    {
        Http::preventStrayRequests();
        $author = Author::factory()->create(['name' => 'J. K. Écrivain', 'normalized_name' => 'jk écrivain']);
        foreach (['anilist' => '0012345', 'kitsu' => '67890', 'google-books' => 'volume12345'] as $sourceKey => $externalId) {
            $source = ApiSource::factory()->create(['key' => $sourceKey]);
            $literature = Literature::factory()->create([
                'api_source_id' => $source->id, 'external_id' => $externalId,
                'title' => "A Tale, \"春\"\nSecond line", 'original_title' => 'Été', 'publication_year' => 2001,
                'type' => 'manga', 'identifier' => 'ISBN 9780064471046',
            ]);
            $literature->authors()->attach($author->id, ['position' => 1, 'role' => 'author']);
        }
        app(CandidatePairExporter::class)->export('fixture', 3, 'test-seed', 2);

        return app(StudyDataset::class)->load('fixture');
    }

    private function saveDefinitive(string $id, int $revision = 0): array
    {
        return app(LabelingWorkspace::class)->save('fixture', $id, [
            'ground_truth_label' => 'SAME_WORK', 'annotator_id' => 'synthetic-test-only',
            'evidence_url' => 'https://example.org/bibliographic-fixture', 'labeling_notes' => "Synthetic test assertion, not an empirical label.\nCSV quote: \"same\".",
        ], $revision, true);
    }

    public function test_fresh_export_is_deterministic_blind_and_unlabeled_without_mutating_catalog(): void
    {
        $study = $this->exportStudy();
        $before = Literature::query()->get()->toJson();
        app(CandidatePairExporter::class)->export('fixture-copy', 3, 'test-seed', 2);
        $copy = app(StudyDataset::class)->load('fixture-copy');
        $this->assertSame($study['pairs'], $copy['pairs']);
        $this->assertSame($study['selected_ids'], $copy['selected_ids']);
        $this->assertCount(3, $study['records']);
        $this->assertCount(2, $study['selected_ids']);
        $this->assertSame(['UNLABELED'], array_unique(array_column($study['pairs'], 'ground_truth_label')));
        $this->assertSame($before, Literature::query()->get()->toJson());
        $this->assertDatabaseCount('literature_source_mappings', 0);
        $view = json_encode(app(LabelingWorkspace::class)->view('fixture'));
        foreach (['canonical_work_id', 'match_method', 'confidence', 'predicted', 'sampling_stratum', 'normalized_name'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $view);
        }
    }

    public function test_excludes_known_demo_and_obviously_fabricated_provider_identities(): void
    {
        $this->exportStudy();
        $source = ApiSource::query()->where('key', 'anilist')->firstOrFail();
        foreach (['30012', 'demo-1', 'fixture-100', 'synthetic-1'] as $id) {
            Literature::factory()->create(['api_source_id' => $source->id, 'external_id' => $id]);
        }
        $result = app(CandidatePairExporter::class)->export('excluded', 3, 'test', 2);
        $this->assertSame(3, $result['snapshot_records']);
    }

    public function test_save_recover_export_and_import_preserve_unicode_metadata_and_identifiers(): void
    {
        $study = $this->exportStudy();
        $hashes = $study['manifest'];
        $id = array_key_first($study['pairs']);
        $saved = $this->saveDefinitive($id);
        $this->assertSame(1, $saved['revision']);
        $workspace = app(LabelingWorkspace::class)->read('fixture');
        $this->assertSame('SAME_WORK', $workspace['study']['pairs'][$id]['ground_truth_label']);
        $this->assertCount(1, $workspace['state']['audit']);
        $this->assertSame('HUMAN_MANUAL', $workspace['study']['pairs'][$id]['label_origin']);
        $text = app(LabelingWorkspace::class)->export('fixture');
        $this->assertStringContainsString('0012345', $text);
        $import = app(StudyDataset::class)->importLabels($study);
        foreach (array_diff(StudyDataset::COLUMNS, StudyDataset::EDITABLE) as $column) {
            $this->assertSame($study['pairs'][$id][$column], $import[$id][$column]);
        }
        $this->assertStringContainsString("\nCSV quote", $import[$id]['labeling_notes']);
        $this->assertSame($hashes, app(StudyDataset::class)->load('fixture')['manifest']);
    }

    public function test_definitive_label_requires_annotator_documentation_and_manual_attestation(): void
    {
        $study = $this->exportStudy();
        foreach ([['', '', '', true], ['test', '', 'short', true], ['test', 'https://example.org', '', false]] as [$annotator, $url, $notes, $attested]) {
            try {
                app(LabelingWorkspace::class)->save('fixture', array_key_first($study['pairs']), ['ground_truth_label' => 'SAME_WORK', 'annotator_id' => $annotator, 'evidence_url' => $url, 'labeling_notes' => $notes], 0, $attested);
                $this->fail('Invalid definitive label accepted.');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertSame(0, app(LabelingWorkspace::class)->read('fixture')['state']['revision']);
    }

    public function test_clear_label_keeps_evidence_and_uncertain_is_not_promoted(): void
    {
        $study = $this->exportStudy();
        $id = array_key_first($study['pairs']);
        $this->saveDefinitive($id);
        $input = ['ground_truth_label' => 'UNCERTAIN', 'annotator_id' => '', 'evidence_url' => '', 'labeling_notes' => 'Need a second edition record.'];
        $result = app(LabelingWorkspace::class)->save('fixture', $id, $input, 1, false);
        $this->assertSame('', $result['decision']['human_verified_at']);
        $input['ground_truth_label'] = 'UNLABELED';
        app(LabelingWorkspace::class)->save('fixture', $id, $input, 2, false);
        $this->assertSame($input['labeling_notes'], app(LabelingWorkspace::class)->read('fixture')['study']['pairs'][$id]['labeling_notes']);
    }

    public function test_stale_tab_cannot_overwrite_another_save(): void
    {
        $study = $this->exportStudy();
        $id = array_key_first($study['pairs']);
        $this->saveDefinitive($id);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(409);
        $this->saveDefinitive($id, 0);
    }

    public function test_snapshot_tampering_is_rejected(): void
    {
        $study = $this->exportStudy();
        file_put_contents($study['directory'].'/snapshot.json', "\n", FILE_APPEND);
        $this->expectException(\InvalidArgumentException::class);
        app(StudyDataset::class)->load('fixture');
    }

    public function test_csv_tampering_and_duplicate_pair_ids_are_rejected(): void
    {
        $study = $this->exportStudy();
        $rows = array_values($study['pairs']);
        $rows[1] = $rows[0];
        StudyFiles::csv($study['directory'].'/labels.csv', StudyDataset::COLUMNS, $rows);
        $this->expectException(\InvalidArgumentException::class);
        app(StudyDataset::class)->importLabels($study);
    }

    public function test_label_csv_cannot_change_original_source_metadata(): void
    {
        $study = $this->exportStudy();
        $rows = array_values($study['pairs']);
        $rows[0]['title_a'] = 'Altered source title';
        StudyFiles::csv($study['directory'].'/labels.csv', StudyDataset::COLUMNS, $rows);
        $this->expectException(\InvalidArgumentException::class);
        app(StudyDataset::class)->importLabels($study);
    }

    public function test_paths_cannot_escape_the_research_root(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StudyFiles::directory('exports', '../catalog', true);
    }

    public function test_existing_exports_are_never_overwritten(): void
    {
        $this->exportStudy();
        $this->expectException(\InvalidArgumentException::class);
        app(CandidatePairExporter::class)->export('fixture', 3, 'test', 2);
    }

    public function test_replay_uses_real_resolver_and_restores_database_without_changes(): void
    {
        $study = $this->exportStudy();
        $original = DB::getDefaultConnection();
        $before = Literature::query()->get()->toJson();
        $keys = array_keys($study['records']);
        $result = app(IsolatedResolverReplay::class)->replay($study['records'], $keys);
        $reverse = app(IsolatedResolverReplay::class)->replay($study['records'], array_reverse($keys));
        $this->assertSame($result[$keys[0]]['canonical_id'], $result[$keys[1]]['canonical_id']);
        $this->assertSame($result[$keys[0]]['cluster_key'], $reverse[$keys[0]]['cluster_key']);
        $this->assertSame($original, DB::getDefaultConnection());
        $this->assertSame($before, Literature::query()->get()->toJson());
        $this->assertDatabaseCount('literature_source_mappings', 0);
    }

    public function test_unlabeled_data_refuses_metrics_and_creates_no_run_directory(): void
    {
        $this->exportStudy();
        app(LabelingWorkspace::class)->export('fixture');
        try {
            app(ResolutionEvaluationRunner::class)->run('fixture', 'must-refuse');
            $this->fail('Unlabeled dataset was evaluated.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('No valid human-verified', $e->getMessage());
        }
        $this->assertDirectoryDoesNotExist($this->root.'/outputs/must-refuse');
    }

    public function test_evaluator_excludes_uncertain_and_unlabeled_and_generates_all_outputs(): void
    {
        $study = $this->exportStudy();
        $ids = array_keys($study['pairs']);
        $this->saveDefinitive($ids[0]);
        app(LabelingWorkspace::class)->save('fixture', $ids[1], ['ground_truth_label' => 'UNCERTAIN'], 1, false);
        app(LabelingWorkspace::class)->export('fixture');
        $manifest = app(ResolutionEvaluationRunner::class)->run('fixture', 'fixture-run', 'all');
        $this->assertSame(1, $manifest['evaluated_pairs']);
        $this->assertSame(2, $manifest['excluded_pairs']);
        $this->assertSame('stable', $manifest['primary_order']);
        foreach (['metrics.csv', 'predictions.csv', 'error_analysis.csv', 'confusion_matrix.csv', 'run_manifest.json', 'order_sensitivity.json', 'precision_comparison.svg', 'recall_comparison.svg', 'f1_comparison.svg', 'confusion_proposed.svg'] as $file) {
            $this->assertFileExists($this->root.'/outputs/fixture-run/'.$file);
        }
        $this->assertCount(9, StudyFiles::readCsv($this->root.'/outputs/fixture-run/metrics.csv')['rows']);
        $this->assertDatabaseCount('literature_source_mappings', 0);
    }

    public function test_server_restricts_host_ip_token_and_cross_origin_requests(): void
    {
        $this->exportStudy();
        $server = app(LocalLabelingServer::class);
        $token = str_repeat('x', 64);
        foreach ([['127.0.0.1', 'http://evil.test/api/study', $token, null], ['203.0.113.2', 'http://localhost/api/study', $token, null], ['127.0.0.1', 'http://localhost/api/study', 'wrong', null], ['127.0.0.1', 'http://localhost/api/study', $token, 'http://evil.test']] as [$ip, $url, $key, $origin]) {
            $request = Request::create($url, 'GET', server: ['REMOTE_ADDR' => $ip, 'HTTP_X_RESEARCH_TOKEN' => $key, 'HTTP_ORIGIN' => $origin]);
            $this->assertSame(403, $server->handle($request, 'fixture', $token)->status());
        }
        $request = Request::create('http://localhost/api/study', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_RESEARCH_TOKEN' => $token]);
        $this->assertSame(200, $server->handle($request, 'fixture', $token)->status());
    }

    public function test_research_tools_and_server_refuse_production(): void
    {
        app()->instance('env', 'production');
        $request = Request::create('http://localhost/api/study', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $this->assertSame(403, app(LocalLabelingServer::class)->handle($request, 'fixture', str_repeat('x', 64))->status());
        $this->expectException(\RuntimeException::class);
        StudyFiles::directory('exports', 'anything', true);
    }

    public function test_replay_restores_original_connection_even_when_snapshot_seed_fails(): void
    {
        $study = $this->exportStudy();
        $records = $study['records'];
        $first = array_key_first($records);
        $records[$first]['attributes']['nonexistent_column'] = 'trigger sandbox failure';
        $original = DB::getDefaultConnection();
        try {
            app(IsolatedResolverReplay::class)->replay($records, array_keys($records));
            $this->fail('Invalid sandbox seed unexpectedly succeeded.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('nonexistent_column', $exception->getMessage());
        }
        $this->assertSame($original, DB::getDefaultConnection());
        $this->assertDatabaseCount('literatures', 3);
        $this->assertDatabaseCount('literature_source_mappings', 0);
    }

    public function test_contradictory_manual_transitive_labels_are_rejected(): void
    {
        $study = $this->exportStudy();
        $rows = array_values($study['pairs']);
        foreach ($rows as &$row) {
            $row['ground_truth_label'] = 'SAME_WORK';
        }
        unset($row);
        $rows[2]['ground_truth_label'] = 'DIFFERENT_WORK';
        $this->expectException(\InvalidArgumentException::class);
        app(StudyDataset::class)->validateConsistency($rows);
    }

    public function test_http_save_and_export_have_persistent_manual_decisions_and_immutable_data(): void
    {
        $study = $this->exportStudy();
        $id = array_key_first($study['pairs']);
        $token = str_repeat('y', 64);
        $server = app(LocalLabelingServer::class);
        $payload = ['pair_id' => $id, 'revision' => 0, 'attested' => true, 'decision' => [
            'ground_truth_label' => 'DIFFERENT_WORK', 'annotator_id' => 'synthetic-api-fixture',
            'evidence_url' => 'https://example.org/fixture', 'labeling_notes' => 'Synthetic HTTP test only.',
        ]];
        $request = Request::create('http://127.0.0.1:8765/api/save', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_RESEARCH_TOKEN' => $token, 'HTTP_ORIGIN' => 'http://127.0.0.1:8765',
        ], content: json_encode($payload));
        $this->assertSame(200, $server->handle($request, 'fixture', $token)->status());
        $this->assertSame('DIFFERENT_WORK', app(LabelingWorkspace::class)->view('fixture')['pairs'][0]['decision']['ground_truth_label']);
        $export = Request::create('http://127.0.0.1:8765/api/export', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1', 'HTTP_X_RESEARCH_TOKEN' => $token,
        ]);
        $response = $server->handle($export, 'fixture', $token);
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('DIFFERENT_WORK', $response->getContent());
        $this->assertSame($study['manifest'], app(StudyDataset::class)->load('fixture')['manifest']);
    }

    public function test_web_app_does_not_expose_research_routes(): void
    {
        $this->get('/research/labeling')->assertNotFound();
    }
}
