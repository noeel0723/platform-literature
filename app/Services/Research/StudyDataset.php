<?php

namespace App\Services\Research;

use InvalidArgumentException;

final class StudyDataset
{
    public const LABELS = ['SAME_WORK', 'DIFFERENT_WORK', 'UNCERTAIN', 'UNLABELED'];

    public const EDITABLE = ['ground_truth_label', 'evidence_url', 'labeling_notes', 'annotator_id', 'human_verified_at', 'label_origin'];

    public const COLUMNS = [
        'pair_id', 'source_a', 'external_id_a', 'title_a', 'original_title_a', 'author_a', 'year_a', 'type_a', 'identifier_a', 'cover_url_a', 'evidence_url_a',
        'source_b', 'external_id_b', 'title_b', 'original_title_b', 'author_b', 'year_b', 'type_b', 'identifier_b', 'cover_url_b', 'evidence_url_b',
        'sampling_stratum', 'ground_truth_label', 'evidence_url', 'labeling_notes', 'annotator_id', 'human_verified_at', 'label_origin',
    ];

    public function load(string $name): array
    {
        $directory = StudyFiles::directory('exports', $name);
        $manifest = StudyFiles::readJson($directory.'/export_manifest.json');
        foreach (['snapshot.json' => 'snapshot_sha256', 'candidate_pairs.csv' => 'candidate_csv_sha256', 'selected_pairs.csv' => 'subset_sha256'] as $file => $checksum) {
            if (is_link($directory.'/'.$file) || ! is_file($directory.'/'.$file) || ! hash_equals($manifest[$checksum] ?? '', hash_file('sha256', $directory.'/'.$file))) {
                throw new InvalidArgumentException('Immutable dataset checksum mismatch: '.$file);
            }
        }
        $snapshot = StudyFiles::readJson($directory.'/snapshot.json');
        if (($snapshot['schema_version'] ?? null) !== 2) {
            throw new InvalidArgumentException('Only fresh manual-study schema v2 is supported.');
        }
        $records = [];
        foreach ($snapshot['records'] as $record) {
            $key = StudyFiles::key($record['source']['key'], $record['attributes']['external_id']);
            if ($key !== $record['key'] || isset($records[$key])) {
                throw new InvalidArgumentException('Invalid or duplicated source identity.');
            }
            $records[$key] = $record;
        }
        $csv = StudyFiles::readCsv($directory.'/candidate_pairs.csv');
        if ($csv['columns'] !== self::COLUMNS) {
            throw new InvalidArgumentException('Unexpected dataset columns.');
        }
        $pairs = [];
        foreach ($csv['rows'] as $row) {
            $a = StudyFiles::key($row['source_a'], $row['external_id_a']);
            $b = StudyFiles::key($row['source_b'], $row['external_id_b']);
            if (! isset($records[$a], $records[$b]) || $row['source_a'] === $row['source_b'] || $row['pair_id'] !== StudyFiles::pairId($a, $b) || isset($pairs[$row['pair_id']])) {
                throw new InvalidArgumentException('Invalid or duplicated cross-provider pair.');
            }
            foreach (['a' => $a, 'b' => $b] as $side => $key) {
                $r = $records[$key];
                $expected = [
                    'title' => $r['attributes']['title'], 'original_title' => $r['attributes']['original_title'] ?? '',
                    'author' => implode(' | ', array_column($r['authors'], 'name')), 'year' => $r['attributes']['publication_year'] ?? '',
                    'type' => $r['attributes']['type'], 'identifier' => $r['attributes']['identifier'] ?? '',
                    'cover_url' => $r['attributes']['cover_url'] ?? '', 'evidence_url' => $r['evidence_url'] ?? '',
                ];
                foreach ($expected as $field => $value) {
                    if ($row[$field.'_'.$side] !== (string) $value) {
                        throw new InvalidArgumentException('Candidate metadata does not match frozen snapshot.');
                    }
                }
            }
            foreach (self::EDITABLE as $field) {
                if ($row[$field] !== ($field === 'ground_truth_label' ? 'UNLABELED' : '')) {
                    throw new InvalidArgumentException('Fresh source candidates must be entirely UNLABELED.');
                }
            }
            $pairs[$row['pair_id']] = $row;
        }
        if (count($pairs) !== ($manifest['exported_pairs'] ?? null) || count($records) !== ($manifest['snapshot_records'] ?? null)) {
            throw new InvalidArgumentException('Manifest counts do not match frozen files.');
        }
        $selected = StudyFiles::readCsv($directory.'/selected_pairs.csv');
        $ids = [];
        foreach ($selected['rows'] as $row) {
            if (! isset($pairs[$row['pair_id']]) || isset($ids[$row['pair_id']]) || $row !== $pairs[$row['pair_id']]) {
                throw new InvalidArgumentException('Invalid frozen evaluation subset.');
            }
            $ids[$row['pair_id']] = true;
        }
        if ($selected['columns'] !== self::COLUMNS || count($ids) !== ($manifest['selected_pairs'] ?? null)) {
            throw new InvalidArgumentException('Subset columns or count mismatch.');
        }
        if ($pairs === [] || $ids === []) {
            throw new InvalidArgumentException('Dataset contains no usable pairs.');
        }

        return compact('directory', 'manifest', 'records', 'pairs') + ['selected_ids' => array_keys($ids)];
    }

    public function validateDecision(array $decision): void
    {
        if (array_diff(array_keys($decision), self::EDITABLE) !== []) {
            throw new InvalidArgumentException('Only labeling columns can be edited.');
        }
        foreach ($decision as $value) {
            if (! is_string($value) || strlen($value) > 20000) {
                throw new InvalidArgumentException('Label fields must be strings of at most 20,000 bytes.');
            }
        }
        if (! in_array($decision['ground_truth_label'] ?? null, self::LABELS, true)) {
            throw new InvalidArgumentException('Invalid ground truth label.');
        }
        $url = trim($decision['evidence_url'] ?? '');
        if ($url !== '' && (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
            throw new InvalidArgumentException('Evidence must be an HTTP(S) URL without credentials.');
        }
        if (in_array($decision['ground_truth_label'], ['SAME_WORK', 'DIFFERENT_WORK'], true)) {
            if (trim($decision['annotator_id'] ?? '') === '' || ($url === '' && mb_strlen(trim($decision['labeling_notes'] ?? '')) < 30)) {
                throw new InvalidArgumentException('Definitive labels need an annotator and an evidence URL or a rationale of at least 30 characters.');
            }
            if (($decision['label_origin'] ?? '') !== 'HUMAN_MANUAL' || strtotime($decision['human_verified_at'] ?? '') === false) {
                throw new InvalidArgumentException('A documented personal manual-verification attestation is required.');
            }
        }
    }

    public function importLabels(array $study, string $filename = 'labels.csv'): array
    {
        if ($filename !== 'labels.csv') {
            throw new InvalidArgumentException('Only the study-local labels.csv may be evaluated.');
        }
        $csv = StudyFiles::readCsv($study['directory'].'/'.$filename);
        if ($csv['columns'] !== self::COLUMNS) {
            throw new InvalidArgumentException('Label export columns differ from source.');
        }
        $pairs = [];
        foreach ($csv['rows'] as $row) {
            $id = $row['pair_id'];
            if (! isset($study['pairs'][$id]) || isset($pairs[$id])) {
                throw new InvalidArgumentException('Unknown or duplicated labeled pair.');
            }
            foreach (array_diff(self::COLUMNS, self::EDITABLE) as $column) {
                if ($row[$column] !== $study['pairs'][$id][$column]) {
                    throw new InvalidArgumentException('Immutable metadata changed in labels.csv.');
                }
            }
            $this->validateDecision(array_intersect_key($row, array_flip(self::EDITABLE)));
            $pairs[$id] = $row;
        }
        if (array_keys($pairs) !== array_keys($study['pairs'])) {
            throw new InvalidArgumentException('Label export must preserve every original pair and its order.');
        }
        $this->validateConsistency($pairs);

        return $pairs;
    }

    public function validateConsistency(array $pairs): void
    {
        $parents = [];
        $root = function (string $key) use (&$parents): string {
            while (isset($parents[$key]) && $parents[$key] !== $key) {
                $key = $parents[$key];
            }

            return $key;
        };
        foreach ($pairs as $pair) {
            if ($pair['ground_truth_label'] === 'SAME_WORK') {
                $parents[$root(StudyFiles::key($pair['source_a'], $pair['external_id_a']))] = $root(StudyFiles::key($pair['source_b'], $pair['external_id_b']));
            }
        }
        foreach ($pairs as $pair) {
            if ($pair['ground_truth_label'] === 'DIFFERENT_WORK' && $root(StudyFiles::key($pair['source_a'], $pair['external_id_a'])) === $root(StudyFiles::key($pair['source_b'], $pair['external_id_b']))) {
                throw new InvalidArgumentException('Contradictory manual labels require adjudication before evaluation.');
            }
        }
    }
}
