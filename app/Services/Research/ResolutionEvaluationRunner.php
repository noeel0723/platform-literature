<?php

namespace App\Services\Research;

use InvalidArgumentException;

final class ResolutionEvaluationRunner
{
    public function __construct(private StudyDataset $datasets, private ResolutionBaselines $baselines, private ResolutionMetrics $metrics, private IsolatedResolverReplay $replay) {}

    public function run(string $name, string $run, string $scope = 'selected'): array
    {
        StudyFiles::localOnly();
        if (! in_array($scope, ['selected', 'all'], true)) {
            throw new InvalidArgumentException('Scope must be selected or all.');
        }
        $study = $this->datasets->load($name);
        if (! is_file($study['directory'].'/labels.csv')) {
            throw new InvalidArgumentException('Human ground truth is not available. Label pairs and export labels.csv first.');
        }
        $pairs = $this->datasets->importLabels($study);
        if ($scope === 'selected') {
            $pairs = array_intersect_key($pairs, array_flip($study['selected_ids']));
        }
        $labeled = array_filter($pairs, fn (array $p): bool => in_array($p['ground_truth_label'], ['SAME_WORK', 'DIFFERENT_WORK'], true));
        if ($labeled === []) {
            throw new InvalidArgumentException('No valid human-verified ground truth in this scope. No metrics were calculated.');
        }
        $directory = StudyFiles::directory('outputs', $run, true);
        $keys = array_keys($study['records']);
        sort($keys, SORT_STRING);
        $seeded = $keys;
        $seed = $study['manifest']['seed'];
        usort($seeded, fn (string $a, string $b): int => strcmp(hash('sha256', $seed.'|replay|'.$a), hash('sha256', $seed.'|replay|'.$b)));
        $orders = ['stable' => $keys, 'reverse' => array_reverse($keys), 'seeded' => $seeded];
        $predictions = [];
        $metrics = [];
        foreach ($orders as $order => $ordered) {
            $assignments = $this->replay->replay($study['records'], $ordered);
            $observations = ['exact_title' => [], 'exact_title_author' => [], 'proposed' => []];
            foreach ($pairs as $id => $pair) {
                $a = StudyFiles::key($pair['source_a'], $pair['external_id_a']);
                $b = StudyFiles::key($pair['source_b'], $pair['external_id_b']);
                $values = $this->baselines->predict($study['records'][$a], $study['records'][$b]);
                $values['proposed'] = $assignments[$a]['canonical_id'] === $assignments[$b]['canonical_id'];
                foreach ($values as $method => $predicted) {
                    $included = isset($labeled[$id]);
                    $actual = $included ? $pair['ground_truth_label'] === 'SAME_WORK' : null;
                    $outcome = $actual === null ? 'EXCLUDED' : ($actual ? ($predicted ? 'TP' : 'FN') : ($predicted ? 'FP' : 'TN'));
                    $predictions[] = [
                        'pair_id' => $id, 'method' => $method, 'order' => $order,
                        'title_a' => $pair['title_a'], 'source_a' => $pair['source_a'], 'external_id_a' => $pair['external_id_a'],
                        'title_b' => $pair['title_b'], 'source_b' => $pair['source_b'], 'external_id_b' => $pair['external_id_b'],
                        'ground_truth_label' => $pair['ground_truth_label'], 'predicted_same_work' => (int) $predicted,
                        'outcome' => $outcome, 'canonical_a' => $assignments[$a]['canonical_id'], 'canonical_b' => $assignments[$b]['canonical_id'],
                        'evidence_url' => $pair['evidence_url'], 'labeling_notes' => $pair['labeling_notes'],
                    ];
                    if ($included) {
                        $observations[$method][] = ['actual' => $actual, 'predicted' => $predicted];
                    }
                }
            }
            foreach ($observations as $method => $values) {
                $metrics[] = ['method' => $method, 'order' => $order] + $this->metrics->calculate($values);
            }
        }
        $errors = array_values(array_filter($predictions, fn (array $p): bool => in_array($p['outcome'], ['FP', 'FN'], true)));
        $confusion = array_map(fn (array $m): array => array_intersect_key($m, array_flip(['method', 'order', 'tp', 'fp', 'fn', 'tn'])), $metrics);
        $sensitivity = [];
        foreach ($pairs as $id => $pair) {
            $votes = array_column(array_filter($predictions, fn (array $p): bool => $p['pair_id'] === $id && $p['method'] === 'proposed'), 'predicted_same_work', 'order');
            if (count(array_unique($votes)) > 1) {
                $sensitivity[] = ['pair_id' => $id, 'predictions' => $votes];
            }
        }
        foreach (['metrics' => $metrics, 'predictions' => $predictions, 'error_analysis' => $errors, 'confusion_matrix' => $confusion] as $file => $rows) {
            $columns = array_keys(($rows[0] ?? ($file === 'error_analysis' ? $predictions[0] : [])));
            StudyFiles::csv($directory.'/'.$file.'.csv', $columns, $rows);
            StudyFiles::json($directory.'/'.$file.'.json', $rows);
        }
        StudyFiles::json($directory.'/order_sensitivity.json', ['primary_order' => 'stable', 'changed_pair_count' => count($sensitivity), 'pairs' => $sensitivity]);
        $currentFingerprints = StudyFiles::fingerprints();
        $changedFingerprints = array_keys(array_filter($currentFingerprints, fn (string $hash, string $path): bool => ($study['manifest']['fingerprints'][$path] ?? null) !== $hash, ARRAY_FILTER_USE_BOTH));
        $manifest = StudyFiles::gitState() + [
            'status' => count($labeled) === count($pairs) ? 'HUMAN_LABELED_SCOPE_EVALUATED' : 'PARTIAL_HUMAN_LABELED_SCOPE_EVALUATED',
            'created_at' => now()->utc()->toIso8601String(), 'dataset' => $name, 'scope' => $scope,
            'scope_pairs' => count($pairs), 'evaluated_pairs' => count($labeled), 'excluded_pairs' => count($pairs) - count($labeled),
            'excluded_labels' => array_count_values(array_column(array_filter($pairs, fn (array $p): bool => ! in_array($p['ground_truth_label'], ['SAME_WORK', 'DIFFERENT_WORK'], true)), 'ground_truth_label')),
            'snapshot_sha256' => $study['manifest']['snapshot_sha256'], 'labels_sha256' => hash_file('sha256', $study['directory'].'/labels.csv'),
            'selected_pairs_sha256' => $study['manifest']['subset_sha256'], 'seed' => $seed,
            'replay_records' => count($study['records']), 'primary_order' => 'stable', 'orders' => array_keys($orders),
            'fingerprints' => $currentFingerprints, 'changed_files_since_export' => $changedFingerprints, 'php_version' => PHP_VERSION, 'database' => 'fresh SQLite :memory: for each ordering',
            'limitations' => ['Human verification is an annotator attestation, not an independent adjudication.', 'Stratified challenge-sample metrics are not population accuracy estimates.', 'Frozen local canonical author identities are upstream inputs for baseline 2 and the resolver; their errors are not corrected.', 'SQLite replay may differ from MySQL collation/concurrency behavior.', 'Excluded unresolved pairs may bias scores; report coverage and exclusion reasons.'],
        ];
        StudyFiles::json($directory.'/run_manifest.json', $manifest);
        $this->charts($directory, $metrics);

        return $manifest;
    }

    private function charts(string $directory, array $metrics): void
    {
        foreach (['precision', 'recall', 'f1'] as $metric) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="620" height="210" viewBox="0 0 620 210"><rect width="620" height="210" fill="#faf7f2"/><text x="20" y="28" font-size="18">'.ucfirst($metric).' — stable order (0–1)</text>';
            foreach (array_values(array_filter($metrics, fn (array $m): bool => $m['order'] === 'stable')) as $i => $row) {
                $y = 50 + $i * 48;
                $value = $row[$metric];
                $svg .= '<text x="20" y="'.($y + 18).'" font-size="13">'.$row['method'].'</text><rect x="180" y="'.$y.'" width="'.round(($value ?? 0) * 350, 2).'" height="26" fill="#133361"/><text x="540" y="'.($y + 18).'" font-size="13">'.($value === null ? 'N/A' : round($value, 4)).'</text>';
            }
            StudyFiles::atomic($directory.'/'.$metric.'_comparison.svg', $svg.'</svg>');
        }
        foreach (array_filter($metrics, fn (array $m): bool => $m['order'] === 'stable') as $row) {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="270"><rect width="420" height="270" fill="#faf7f2"/><text x="20" y="25">'.$row['method'].' — stable order</text><text x="150" y="55">Predicted SAME / DIFFERENT</text><text x="5" y="110">Actual SAME</text><text x="5" y="190">DIFFERENT</text>';
            foreach (['tp', 'fn', 'fp', 'tn'] as $i => $cell) {
                $x = 150 + ($i % 2) * 120;
                $y = 75 + intdiv($i, 2) * 80;
                $svg .= '<rect x="'.$x.'" y="'.$y.'" width="115" height="75" fill="'.(in_array($cell, ['fp', 'fn']) ? '#ffc8be' : '#d6e5f5').'"/><text x="'.($x + 12).'" y="'.($y + 42).'">'.strtoupper($cell).': '.$row[$cell].'</text>';
            }
            StudyFiles::atomic($directory.'/confusion_'.$row['method'].'.svg', $svg.'</svg>');
        }
    }
}
