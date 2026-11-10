<?php

namespace App\Services\Research;

use InvalidArgumentException;
use RuntimeException;

final class LabelingWorkspace
{
    public function __construct(private StudyDataset $datasets) {}

    public function read(string $name): array
    {
        $study = $this->datasets->load($name);
        $path = $study['directory'].'/progress.json';
        $state = is_file($path) ? StudyFiles::readJson($path) : [
            'snapshot_sha256' => $study['manifest']['snapshot_sha256'], 'candidate_sha256' => $study['manifest']['candidate_csv_sha256'],
            'revision' => 0, 'decisions' => [], 'audit' => [],
        ];
        if ($state['snapshot_sha256'] !== $study['manifest']['snapshot_sha256'] || $state['candidate_sha256'] !== $study['manifest']['candidate_csv_sha256']) {
            throw new InvalidArgumentException('Progress belongs to another dataset.');
        }
        foreach ($state['decisions'] as $id => $decision) {
            if (! isset($study['pairs'][$id])) {
                throw new InvalidArgumentException('Unknown progress pair.');
            }
            $this->datasets->validateDecision($decision);
            $study['pairs'][$id] = array_replace($study['pairs'][$id], $decision);
        }

        return ['study' => $study, 'state' => $state];
    }

    public function save(string $name, string $id, array $input, int $revision, bool $attested): array
    {
        $directory = StudyFiles::directory('exports', $name);
        if (is_link($directory.'/.progress.lock')) {
            throw new InvalidArgumentException('Unsafe progress lock.');
        }
        $lock = fopen($directory.'/.progress.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock progress.');
        }
        try {
            ['study' => $study, 'state' => $state] = $this->read($name);
            if ($revision !== $state['revision']) {
                throw new RuntimeException('Progress was updated in another tab. Reload before saving.', 409);
            }
            if (! isset($study['pairs'][$id]) || array_diff(array_keys($input), ['ground_truth_label', 'evidence_url', 'labeling_notes', 'annotator_id']) !== []) {
                throw new InvalidArgumentException('Unknown pair or immutable fields in request.');
            }
            $definitive = in_array($input['ground_truth_label'] ?? '', ['SAME_WORK', 'DIFFERENT_WORK'], true);
            if ($definitive && ! $attested) {
                throw new InvalidArgumentException('Confirm that you personally checked this pair.');
            }
            $decision = $input + ['evidence_url' => '', 'labeling_notes' => '', 'annotator_id' => ''];
            $decision['human_verified_at'] = $definitive ? now()->utc()->toIso8601String() : '';
            $decision['label_origin'] = $definitive ? 'HUMAN_MANUAL' : '';
            $this->datasets->validateDecision($decision);
            $state['decisions'][$id] = $decision;
            $state['revision']++;
            $state['audit'][] = ['pair_id' => $id, 'revision' => $state['revision'], 'saved_at' => now()->utc()->toIso8601String(), 'decision' => $decision];
            StudyFiles::json($directory.'/progress.json', $state);

            return ['revision' => $state['revision'], 'decision' => $decision];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function export(string $name): string
    {
        ['study' => $study] = $this->read($name);
        $this->datasets->validateConsistency($study['pairs']);
        $text = StudyFiles::csvText(StudyDataset::COLUMNS, array_values($study['pairs']));
        StudyFiles::atomic($study['directory'].'/labels.csv', $text);

        return $text;
    }

    /** Blind view model: no canonical identities, model predictions, or sampling categories. */
    public function view(string $name): array
    {
        ['study' => $study, 'state' => $state] = $this->read($name);
        $pairs = [];
        foreach ($study['pairs'] as $id => $row) {
            $item = ['pair_id' => $id, 'selected' => in_array($id, $study['selected_ids'], true), 'decision' => array_intersect_key($row, array_flip(StudyDataset::EDITABLE))];
            foreach (['a', 'b'] as $side) {
                $item[$side] = [];
                foreach (['source', 'external_id', 'title', 'original_title', 'author', 'year', 'type', 'identifier', 'cover_url', 'evidence_url'] as $field) {
                    $item[$side][$field] = $row[$field.'_'.$side];
                }
            }
            $pairs[] = $item;
        }

        return ['name' => $name, 'checksum' => $study['manifest']['snapshot_sha256'], 'revision' => $state['revision'], 'pairs' => $pairs];
    }
}
