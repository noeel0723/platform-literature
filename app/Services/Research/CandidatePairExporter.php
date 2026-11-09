<?php

namespace App\Services\Research;

use App\Models\Literature;
use App\Services\Literature\LiteratureIdentityNormalizer;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CandidatePairExporter
{
    public const SOURCES = ['hardcover', 'google-books', 'anilist', 'kitsu', 'comic-vine'];

    /** Known static demo identities from CatalogSeeder, conservatively excluded even if later refreshed. */
    private const DEMO_IDENTITIES = [
        'anilist' => ['30012', '5114', '30035'],
        'comic-vine' => ['4050-1807'],
        'google-books' => ['isbn-9780261102217'],
    ];

    public function __construct(private LiteratureIdentityNormalizer $normalizer) {}

    public function export(string $directory, int $limit = 180, string $seed = 'literahaven-v1'): array
    {
        if ($limit < 1 || $limit > 2000) {
            throw new InvalidArgumentException('Pair target must be between 1 and 2000.');
        }
        $records = DB::connection()->transaction(function (): array {
            $records = [];
            Literature::query()->whereIn('type', Literature::supportedTypes())
                ->whereHas('apiSource', fn ($sources) => $sources->whereIn('key', self::SOURCES))
                ->with(['apiSource', 'authors.aliases', 'categories'])->chunkById(200, function ($literatures) use (&$records): void {
                    foreach ($literatures as $literature) {
                        if (trim($literature->external_id) === '' || in_array($literature->external_id, self::DEMO_IDENTITIES[$literature->apiSource->key] ?? [], true)) {
                            continue;
                        }
                        $attributes = array_intersect_key($literature->getAttributes(), array_flip([
                            'id', 'external_id', 'title', 'original_title', 'type', 'publication_year', 'identifier',
                            'knowledge_graph_id', 'knowledge_graph_types', 'knowledge_graph_url', 'knowledge_graph_score',
                            'tagline', 'synopsis', 'synopsis_source_name', 'synopsis_source_url', 'publisher', 'language',
                            'format', 'cover_url', 'backdrop_url', 'theme', 'created_at', 'updated_at',
                        ]));
                        foreach (['cover_url', 'backdrop_url', 'knowledge_graph_url', 'synopsis_source_url'] as $field) {
                            if (isset($attributes[$field])) {
                                $attributes[$field] = $this->safeUrl($attributes[$field]);
                            }
                        }
                        $records[] = [
                            'key' => EvaluationFiles::key($literature->apiSource->key, $literature->external_id),
                            'source' => ['id' => $literature->apiSource->id, 'key' => $literature->apiSource->key, 'name' => $literature->apiSource->name],
                            'attributes' => $attributes,
                            'authors' => $literature->authors->map(fn ($author): array => [
                                'id' => $author->id, 'name' => $author->name, 'normalized_name' => $author->normalized_name,
                                'external_entity_id' => $author->external_entity_id,
                                'role' => $author->pivot->role, 'position' => $author->pivot->position,
                                'aliases' => $author->aliases->map(fn ($alias): array => [
                                    'source' => $alias->source, 'external_id' => $alias->external_id,
                                    'name' => $alias->name, 'source_url' => $this->safeUrl($alias->source_url),
                                ])->sortBy(fn (array $alias): string => json_encode($alias))->values()->all(),
                            ])->all(),
                            'categories' => $literature->categories->sortBy('id')->map(fn ($category): array => [
                                'id' => $category->id, 'name' => $category->name, 'slug' => $category->slug,
                            ])->values()->all(),
                            'normalized_title' => $this->normalizer->normalizeTitle($literature->title),
                            'identifiers' => $this->normalizer->identifiers($literature),
                            'evidence_url' => $this->evidenceUrl($literature),
                        ];
                    }
                });
            usort($records, fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

            return $records;
        });
        [$pairs, $sampling] = $this->sample($records, $limit, $seed);
        $directory = EvaluationFiles::newDirectory($directory);
        EvaluationFiles::json($directory.'/snapshot.json', ['schema_version' => 1, 'records' => $records]);
        EvaluationFiles::csv($directory.'/candidate_pairs.csv', PairDataset::COLUMNS, $pairs);
        $manifest = [
            'schema_version' => 1, 'exported_at' => now()->toIso8601String(), 'seed' => $seed,
            'source_driver' => DB::connection()->getDriverName(), 'requested_pairs' => $limit,
            'exported_pairs' => count($pairs), 'snapshot_records' => count($records),
            'source_counts' => array_count_values(array_column(array_column($records, 'source'), 'key')),
            'type_counts' => array_count_values(array_column(array_column($records, 'attributes'), 'type')),
            'snapshot_sha256' => hash_file('sha256', $directory.'/snapshot.json'),
            'candidate_csv_sha256' => hash_file('sha256', $directory.'/candidate_pairs.csv'),
            'fingerprints' => EvaluationFiles::fingerprints(), 'sampling' => $sampling,
            'status' => 'UNLABELED_NOT_EVALUATED',
            'known_demo_identities_excluded' => self::DEMO_IDENTITIES,
            'provenance_scope' => 'Stored provider attribution; remote source identities have not been independently revalidated.',
            'limitations' => [
                'Selection uses source metadata only, never canonical_work_id, mapping_status, or resolver predictions.',
                'Stratified challenge sample, not a population-random sample; stratum names are NOT truth labels.',
                'Local post-ingestion metadata and frozen author identities, not original API responses.',
                'Existing title/identifier normalization is used for blocking; random cross-source controls mitigate but do not eliminate blocking bias.',
                'All ground truth must be assigned manually; candidate availability does not guarantee positive or hard-negative counts.',
            ],
        ];
        EvaluationFiles::json($directory.'/export_manifest.json', $manifest);

        return $manifest;
    }

    /** @return array{0: array, 1: array} */
    private function sample(array $records, int $limit, string $seed): array
    {
        $blocks = [];
        foreach ($records as $index => $record) {
            $keys = [];
            if ($record['normalized_title'] !== '') {
                $keys[] = 'title:'.$record['normalized_title'];
                foreach (array_unique(explode(' ', $record['normalized_title'])) as $token) {
                    if (mb_strlen($token) >= 4) {
                        $keys[] = 'token:'.$token;
                    }
                }
            }
            foreach ($record['authors'] as $author) {
                $keys[] = 'author:'.$author['id'];
            }
            foreach ($record['identifiers'] as $identifier) {
                if (! str_starts_with($identifier['scheme'], 'source:')) {
                    $keys[] = 'identifier:'.$identifier['scheme'].':'.$identifier['value'];
                }
            }
            foreach (array_unique($keys) as $key) {
                $blocks[$key][] = $index;
            }
        }
        $strata = array_fill_keys(['shared_identifier', 'equal_title_shared_author', 'equal_title_other_author', 'title_token_variant', 'shared_author_other_title', 'incomplete_metadata', 'random_cross_source'], []);
        $poolCap = max(1000, $limit * 20);
        $consider = function (int $a, int $b, bool $random = false) use (&$strata, $records, $seed, $poolCap): void {
            $left = $records[$a];
            $right = $records[$b];
            if ($left['source']['key'] === $right['source']['key']) {
                return;
            }
            $pairId = EvaluationFiles::pairId($left['key'], $right['key']);
            $identities = fn (array $record): array => array_map(fn (array $identifier): string => $identifier['scheme'].':'.$identifier['value'], array_filter($record['identifiers'], fn (array $identifier): bool => ! str_starts_with($identifier['scheme'], 'source:')));
            $sharedIdentifier = array_intersect($identities($left), $identities($right)) !== [];
            $sameTitle = $left['normalized_title'] !== '' && $left['normalized_title'] === $right['normalized_title'];
            $sharedAuthor = array_intersect(array_column($left['authors'], 'id'), array_column($right['authors'], 'id')) !== [];
            $incomplete = $left['authors'] === [] || $right['authors'] === [] || empty($left['attributes']['publication_year']) || empty($right['attributes']['publication_year']) || empty($left['attributes']['identifier']) || empty($right['attributes']['identifier']);
            $tags = [];
            if ($random) {
                $tags[] = 'random_cross_source';
            } elseif ($sharedIdentifier) {
                $tags[] = 'shared_identifier';
            } elseif ($sameTitle) {
                $tags[] = $sharedAuthor ? 'equal_title_shared_author' : 'equal_title_other_author';
            } else {
                $tags[] = $sharedAuthor ? 'shared_author_other_title' : 'title_token_variant';
            }
            if ($incomplete && ! $random) {
                $tags[] = 'incomplete_metadata';
            }
            foreach ($tags as $tag) {
                $strata[$tag][$pairId] = ['a' => min($a, $b), 'b' => max($a, $b), 'rank' => hash('sha256', $seed.'|'.$pairId), 'pair_id' => $pairId];
                if (count($strata[$tag]) > 2 * $poolCap) {
                    uasort($strata[$tag], fn (array $a, array $b): int => strcmp($a['rank'], $b['rank']));
                    $strata[$tag] = array_slice($strata[$tag], 0, $poolCap, true);
                }
            }
        };
        $truncated = 0;
        foreach ($blocks as $indices) {
            if (count($indices) < 2) {
                continue;
            }
            usort($indices, fn (int $a, int $b): int => strcmp(hash('sha256', $seed.'|'.$records[$a]['key']), hash('sha256', $seed.'|'.$records[$b]['key'])));
            if (count($indices) > 50) {
                $truncated++;
            }
            $indices = array_slice($indices, 0, 50);
            foreach ($indices as $position => $a) {
                foreach (array_slice($indices, $position + 1) as $b) {
                    $consider($a, $b);
                }
            }
        }
        $randomOrder = array_keys($records);
        usort($randomOrder, fn (int $a, int $b): int => strcmp(hash('sha256', $seed.'|control|'.$records[$a]['key']), hash('sha256', $seed.'|control|'.$records[$b]['key'])));
        foreach ($randomOrder as $position => $a) {
            foreach (array_slice($randomOrder, $position + 1, 12) as $b) {
                $consider($a, $b, true);
            }
        }
        foreach ($strata as &$pool) {
            usort($pool, fn (array $a, array $b): int => strcmp($a['rank'], $b['rank']));
            $pool = array_slice($pool, 0, $poolCap);
        }
        unset($pool);
        $poolCounts = array_map('count', $strata);
        $selected = [];
        $counts = array_fill_keys(array_keys($strata), 0);
        do {
            $added = false;
            foreach ($strata as $tag => &$pool) {
                while ($pool !== []) {
                    $candidate = array_shift($pool);
                    if (isset($selected[$candidate['pair_id']])) {
                        continue;
                    }
                    $a = $records[$candidate['a']];
                    $b = $records[$candidate['b']];
                    $row = ['pair_id' => $candidate['pair_id'], 'ground_truth_label' => 'UNLABELED', 'evidence_url' => '', 'labeling_notes' => '', 'annotator_id' => ''];
                    foreach (['a' => $a, 'b' => $b] as $side => $record) {
                        $row += [
                            'source_'.$side => $record['source']['key'], 'external_id_'.$side => $record['attributes']['external_id'],
                            'title_'.$side => $record['attributes']['title'], 'author_'.$side => implode(' | ', array_column($record['authors'], 'name')),
                            'year_'.$side => $record['attributes']['publication_year'] ?? '', 'type_'.$side => $record['attributes']['type'],
                            'identifier_'.$side => $record['attributes']['identifier'] ?? '', 'original_title_'.$side => $record['attributes']['original_title'] ?? '',
                            'evidence_url_'.$side => $record['evidence_url'],
                        ];
                    }
                    $selected[$candidate['pair_id']] = $row;
                    $counts[$tag]++;
                    $added = true;
                    break;
                }
                if (count($selected) >= $limit) {
                    break;
                }
            }
            unset($pool);
        } while ($added && count($selected) < $limit);
        $selectionStrata = $counts;

        return [array_values($selected), ['strategy' => 'metadata_blocks_and_hash_controls_round_robin_v1', 'block_cap' => 50, 'control_lookahead' => 12, 'truncated_blocks' => $truncated, 'pool_cap_per_stratum' => $poolCap, 'retained_per_stratum' => $poolCounts, 'selected_per_stratum' => $selectionStrata]];
    }

    private function evidenceUrl(Literature $literature): ?string
    {
        $id = rawurlencode($literature->external_id);

        return match ($literature->apiSource->key) {
            'google-books' => 'https://books.google.com/books?id='.$id,
            'anilist' => ctype_digit($literature->external_id) ? 'https://anilist.co/manga/'.$id : null,
            'kitsu' => ctype_digit($literature->external_id) ? 'https://kitsu.app/manga/'.$id : null,
            default => $this->safeUrl($literature->synopsis_source_url) ?? $this->safeUrl($literature->knowledge_graph_url),
        };
    }

    private function safeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['host']) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }
        parse_str($parts['query'] ?? '', $query);
        foreach (array_keys($query) as $key) {
            if (preg_match('/key|token|secret|password|signature|credential|auth/i', $key)) {
                unset($query[$key]);
            }
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '').($query === [] ? '' : '?'.http_build_query($query));
    }
}
