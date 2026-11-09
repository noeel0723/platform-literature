<?php

namespace App\Services\Research;

use InvalidArgumentException;

final class PairDataset
{
    public const COLUMNS = [
        'pair_id', 'source_a', 'external_id_a', 'title_a', 'author_a', 'year_a', 'type_a', 'identifier_a', 'original_title_a',
        'source_b', 'external_id_b', 'title_b', 'author_b', 'year_b', 'type_b', 'identifier_b', 'original_title_b',
        'evidence_url_a', 'evidence_url_b', 'ground_truth_label', 'evidence_url', 'labeling_notes', 'annotator_id',
    ];

    public function load(string $snapshotPath, string $csvPath): array
    {
        $snapshot = json_decode(file_get_contents($snapshotPath), true, 512, JSON_THROW_ON_ERROR);
        if (($snapshot['schema_version'] ?? null) !== 1 || ! isset($snapshot['records'])) {
            throw new InvalidArgumentException('Unsupported snapshot format.');
        }
        $manifestPath = dirname($snapshotPath).'/export_manifest.json';
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException('The original export manifest is required beside the snapshot.');
        }
        $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['snapshot_sha256'] ?? '') !== hash_file('sha256', $snapshotPath)) {
            throw new InvalidArgumentException('Snapshot checksum mismatch; re-export rather than editing source metadata.');
        }
        $records = [];
        foreach ($snapshot['records'] as $record) {
            $key = EvaluationFiles::key($record['source']['key'], $record['attributes']['external_id']);
            if ($key !== $record['key'] || isset($records[$key])) {
                throw new InvalidArgumentException('Invalid or duplicate stable source identity in snapshot.');
            }
            $records[$key] = $record;
        }
        $stream = fopen($csvPath, 'rb');
        if ($stream === false) {
            throw new InvalidArgumentException('Cannot read labels CSV.');
        }
        $rows = [];
        $seen = [];
        try {
            $columns = fgetcsv($stream, 0, ',', '"', '');
            if ($columns !== false) {
                $columns[0] = preg_replace('/^\xEF\xBB\xBF/', '', $columns[0]);
            }
            if ($columns === false || array_diff(self::COLUMNS, $columns) !== [] || count(array_unique($columns)) !== count($columns)) {
                throw new InvalidArgumentException('Missing or duplicate CSV columns. Use the exported template.');
            }
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                if (count($values) !== count($columns)) {
                    throw new InvalidArgumentException('Malformed CSV row.');
                }
                $row = array_combine($columns, $values);
                $a = EvaluationFiles::key($row['source_a'], $row['external_id_a']);
                $b = EvaluationFiles::key($row['source_b'], $row['external_id_b']);
                $pairId = EvaluationFiles::pairId($a, $b);
                if ($a === $b || ! isset($records[$a], $records[$b]) || $row['pair_id'] !== $pairId || isset($seen[$pairId])) {
                    throw new InvalidArgumentException('Unknown, duplicated, or inconsistent pair identity.');
                }
                foreach (['a' => $a, 'b' => $b] as $side => $key) {
                    $record = $records[$key];
                    $expected = [
                        'title' => $record['attributes']['title'],
                        'author' => implode(' | ', array_column($record['authors'], 'name')),
                        'year' => $record['attributes']['publication_year'] ?? '',
                        'type' => $record['attributes']['type'],
                        'identifier' => $record['attributes']['identifier'] ?? '',
                        'original_title' => $record['attributes']['original_title'] ?? '',
                    ];
                    foreach ($expected as $column => $value) {
                        if ($row[$column.'_'.$side] !== (string) $value) {
                            throw new InvalidArgumentException('Metadata was edited in labels CSV; only edit labeling columns.');
                        }
                    }
                }
                if (! in_array($row['ground_truth_label'], ['UNLABELED', 'UNCERTAIN', 'SAME_WORK', 'DIFFERENT_WORK'], true)) {
                    throw new InvalidArgumentException('Labels must be UNLABELED, UNCERTAIN, SAME_WORK, or DIFFERENT_WORK.');
                }
                if (in_array($row['ground_truth_label'], ['SAME_WORK', 'DIFFERENT_WORK'], true)
                    && (trim($row['annotator_id']) === '' || (trim($row['evidence_url']) === '' && trim($row['labeling_notes']) === ''))) {
                    throw new InvalidArgumentException('A labeled pair needs an annotator ID and evidence or a written rationale.');
                }
                $seen[$pairId] = true;
                $rows[] = $row + ['key_a' => $a, 'key_b' => $b];
            }
        } finally {
            fclose($stream);
        }
        if ($rows === []) {
            throw new InvalidArgumentException('The dataset contains no pairs.');
        }

        $this->validateLabelConsistency($rows);

        return ['snapshot' => $snapshot, 'records' => $records, 'pairs' => $rows, 'manifest' => $manifest];
    }

    /** Reject an explicit DIFFERENT_WORK edge inside a manually labeled SAME_WORK component. */
    private function validateLabelConsistency(array $pairs): void
    {
        $parent = [];
        $root = function (string $key) use (&$parent): string {
            while (isset($parent[$key]) && $parent[$key] !== $key) {
                $key = $parent[$key];
            }

            return $key;
        };
        foreach ($pairs as $pair) {
            if ($pair['ground_truth_label'] === 'SAME_WORK') {
                $parent[$root($pair['key_a'])] = $root($pair['key_b']);
            }
        }
        foreach ($pairs as $pair) {
            if ($pair['ground_truth_label'] === 'DIFFERENT_WORK' && $root($pair['key_a']) === $root($pair['key_b'])) {
                throw new InvalidArgumentException('Contradictory manual labels: DIFFERENT_WORK inside a SAME_WORK component. Adjudicate before evaluation.');
            }
        }
    }
}
