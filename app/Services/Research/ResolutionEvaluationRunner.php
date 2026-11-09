<?php

namespace App\Services\Research;

use InvalidArgumentException;

final class ResolutionEvaluationRunner
{
    public function __construct(
        private PairDataset $datasets,
        private ResolutionBaselines $baselines,
        private IsolatedResolverReplay $replay,
        private ResolutionMetrics $metrics,
    ) {}

    public function run(string $snapshotPath, string $labelsPath, string $directory, string $seed = 'literahaven-v1', bool $predictOnly = false): array
    {
        $dataset = $this->datasets->load($snapshotPath, $labelsPath);
        $records = $dataset['records'];
        $pairs = $dataset['pairs'];
        $labeled = array_filter($pairs, fn (array $pair): bool => in_array($pair['ground_truth_label'], ['SAME_WORK', 'DIFFERENT_WORK'], true));
        if (! $predictOnly && $labeled === []) {
            throw new InvalidArgumentException('No manually labeled pairs. Evaluation is NOT complete. Use --predict-only for diagnostic predictions without scores.');
        }
        $directory = EvaluationFiles::newDirectory($directory);
        $stable = array_keys($records);
        sort($stable, SORT_STRING);
        $shuffled = $stable;
        usort($shuffled, fn (string $a, string $b): int => strcmp(hash('sha256', $seed.'|'.$a), hash('sha256', $seed.'|'.$b)));
        $orders = ['stable' => $stable, 'reverse' => array_reverse($stable), 'seeded' => $shuffled];
        $predictions = [];
        foreach ($pairs as $pair) {
            foreach ($this->baselines->predict($records[$pair['key_a']], $records[$pair['key_b']]) as $method => $prediction) {
                $predictions[] = $this->prediction($pair, $method, 'pairwise', $prediction);
            }
        }
        $assignments = [];
        foreach ($orders as $name => $order) {
            $mapping = $this->replay->replay($records, $order);
            foreach ($mapping as $key => $assignment) {
                $assignments[] = ['order' => $name, 'source_key' => $key] + $assignment;
            }
            foreach ($pairs as $pair) {
                $a = $mapping[$pair['key_a']];
                $b = $mapping[$pair['key_b']];
                $predictions[] = $this->prediction($pair, 'proposed', $name, $a['canonical_id'] === $b['canonical_id']) + [
                    'canonical_a' => $a['canonical_id'], 'canonical_b' => $b['canonical_id'],
                    'cluster_a' => $a['cluster_key'], 'cluster_b' => $b['cluster_key'],
                    'match_method_a' => $a['match_method'], 'match_method_b' => $b['match_method'],
                ];
            }
        }
        $summaries = [];
        $errors = [];
        $groups = [];
        foreach ($predictions as $prediction) {
            $group = $prediction['method'].'/'.$prediction['order'];
            $groups[$group] ??= [];
            if ($prediction['actual_same_work'] === null || $predictOnly) {
                continue;
            }
            $groups[$group][] = ['actual' => $prediction['actual_same_work'], 'predicted' => $prediction['predicted_same_work']];
            if ($prediction['actual_same_work'] !== $prediction['predicted_same_work']) {
                $errors[] = $prediction + ['error_type' => $prediction['predicted_same_work'] ? 'FALSE_MERGE' : 'MISSED_MERGE', 'analysis_notes' => ''];
            }
        }
        if (! $predictOnly) {
            foreach ($groups as $group => $observations) {
                [$method, $order] = explode('/', $group);
                $summaries[] = ['method' => $method, 'order' => $order] + $this->metrics->calculate($observations);
            }
        }
        $orderChanges = [];
        foreach ($pairs as $pair) {
            $values = [];
            foreach ($predictions as $prediction) {
                if ($prediction['pair_id'] === $pair['pair_id'] && $prediction['method'] === 'proposed') {
                    $values[$prediction['order']] = $prediction['predicted_same_work'];
                }
            }
            if (count(array_unique($values, SORT_REGULAR)) > 1) {
                $orderChanges[] = ['pair_id' => $pair['pair_id']] + $values;
            }
        }
        $columns = ['pair_id', 'method', 'order', 'source_a', 'external_id_a', 'title_a', 'source_b', 'external_id_b', 'title_b', 'ground_truth_label', 'actual_same_work', 'predicted_same_work', 'canonical_a', 'canonical_b', 'cluster_a', 'cluster_b', 'match_method_a', 'match_method_b', 'evidence_url', 'labeling_notes'];
        $csvPredictions = array_map($this->csvBooleans(...), $predictions);
        EvaluationFiles::csv($directory.'/predictions.csv', $columns, $csvPredictions);
        EvaluationFiles::json($directory.'/predictions.json', $predictions);
        EvaluationFiles::csv($directory.'/metrics.csv', ['method', 'order', 'n', 'tp', 'fp', 'fn', 'tn', 'accuracy', 'precision', 'recall', 'f1', 'false_merge_rate'], $summaries);
        EvaluationFiles::json($directory.'/metrics.json', $summaries);
        EvaluationFiles::csv($directory.'/error_analysis.csv', [...$columns, 'error_type', 'analysis_notes'], array_map($this->csvBooleans(...), $errors));
        EvaluationFiles::json($directory.'/error_analysis.json', $errors);
        EvaluationFiles::json($directory.'/canonical_assignments.json', $assignments);
        EvaluationFiles::json($directory.'/order_sensitivity.json', $orderChanges);
        $manifest = [
            'status' => $predictOnly ? 'PREDICTIONS_ONLY_NOT_EVALUATED' : (count($labeled) === count($pairs) ? 'EVALUATED_LABELED_DATASET' : 'PARTIAL_LABELS_EVALUATED'),
            'created_at' => now()->toIso8601String(), 'seed' => $seed,
            'pair_count' => count($pairs), 'labeled_pairs' => count($labeled),
            'label_counts' => array_count_values(array_column($pairs, 'ground_truth_label')),
            'scored_pairs_per_method' => $predictOnly ? 0 : count($labeled),
            'snapshot_records' => count($records), 'sandbox_driver' => 'sqlite', 'sandbox_database' => ':memory:',
            'php_version' => PHP_VERSION, 'laravel_version' => app()->version(),
            'sqlite_version' => \SQLite3::version()['versionString'],
            'source_driver_at_export' => $dataset['manifest']['source_driver'],
            'snapshot_sha256' => hash_file('sha256', $snapshotPath), 'labels_sha256' => hash_file('sha256', $labelsPath),
            'orders' => $orders, 'order_sensitive_pairs' => count($orderChanges),
            'fingerprints' => EvaluationFiles::fingerprints(),
            'interpretation' => [
                'No pooled metrics across orders: the same labels are reused; stable is the primary predeclared order.',
                'Zero denominators are JSON null / empty CSV, never invented perfect scores.',
                'Pairwise scores on a stratified sample are not population prevalence estimates or complete clustering scores.',
                'SQLite replay does not measure MySQL collation, row-locking, concurrency, or production performance.',
                'Author IDs are frozen upstream identities. Work canonical mappings are rebuilt from empty state.',
            ],
        ];
        EvaluationFiles::json($directory.'/run_manifest.json', $manifest);

        return $manifest;
    }

    private function prediction(array $pair, string $method, string $order, bool $prediction): array
    {
        return array_intersect_key($pair, array_flip(['pair_id', 'source_a', 'external_id_a', 'title_a', 'source_b', 'external_id_b', 'title_b', 'ground_truth_label', 'evidence_url', 'labeling_notes'])) + [
            'method' => $method, 'order' => $order, 'predicted_same_work' => $prediction,
            'actual_same_work' => match ($pair['ground_truth_label']) {
                'SAME_WORK' => true, 'DIFFERENT_WORK' => false, default => null,
            },
        ];
    }

    private function csvBooleans(array $row): array
    {
        foreach (['actual_same_work', 'predicted_same_work'] as $key) {
            $row[$key] = $row[$key] === null ? '' : ($row[$key] ? '1' : '0');
        }

        return $row;
    }
}
