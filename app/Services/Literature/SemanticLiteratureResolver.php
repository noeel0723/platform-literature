<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\CanonicalWorkIdentifier;
use App\Models\Literature;
use App\Models\LiteratureSourceMapping;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SemanticLiteratureResolver
{
    public function __construct(
        private LiteratureIdentityNormalizer $identities,
        private CanonicalLiteratureProjector $projector,
    ) {}

    public function resolve(Literature $literature): LiteratureSourceMapping
    {
        return DB::transaction(fn (): LiteratureSourceMapping => $this->resolveWithinTransaction($literature));
    }

    private function resolveWithinTransaction(Literature $literature): LiteratureSourceMapping
    {
        $literature->loadMissing(['apiSource', 'authors']);
        $identifiers = $this->identities->identifiers($literature);
        $existingMapping = LiteratureSourceMapping::query()
            ->whereBelongsTo($literature)
            ->lockForUpdate()
            ->first();

        if ($existingMapping !== null) {
            $existingMapping->update([
                'api_source_id' => $literature->api_source_id,
                'source_external_id' => $literature->external_id,
                'field_provenance' => $this->fieldProvenance($literature),
            ]);
            $this->enrichCanonicalWork($existingMapping->canonicalWork, $literature);
            $this->storeIdentifiers($existingMapping->canonicalWork, $identifiers);
            $this->projector->refresh($existingMapping->canonicalWork);

            return $existingMapping->refresh();
        }

        $identifierCandidates = $this->identifierCandidates($identifiers);
        $canonicalWork = null;
        $matchMethod = 'created';
        $mappingStatus = LiteratureSourceMapping::STATUS_NEW;
        $confidence = 1.0;

        if ($identifierCandidates->count() === 1) {
            $candidate = $identifierCandidates->firstOrFail();

            if ($candidate->type === $literature->type) {
                $canonicalWork = $candidate;
                $matchMethod = 'external_identifier';
                $mappingStatus = LiteratureSourceMapping::STATUS_MATCHED;
            }
        }

        if ($identifierCandidates->count() > 1 || ($identifierCandidates->isNotEmpty() && $canonicalWork === null)) {
            $mappingStatus = LiteratureSourceMapping::STATUS_MATCHED;
            $matchMethod = 'identifier_conflict_separate';
            $confidence = 0.25;
        }

        if ($identifierCandidates->isEmpty()) {
            $composite = $this->compositeCandidate($literature);
            $canonicalWork = $composite['work'];
            $matchMethod = $composite['method'];
            $mappingStatus = $composite['status'];
            $confidence = $composite['confidence'];
        }

        $canonicalWork ??= $this->createCanonicalWork($literature);
        $this->enrichCanonicalWork($canonicalWork, $literature);

        $mapping = LiteratureSourceMapping::query()->create([
            'canonical_work_id' => $canonicalWork->id,
            'literature_id' => $literature->id,
            'api_source_id' => $literature->api_source_id,
            'source_external_id' => $literature->external_id,
            'match_method' => $matchMethod,
            'mapping_status' => $mappingStatus,
            'confidence' => $confidence,
            'candidate_work_ids' => null,
            'field_provenance' => $this->fieldProvenance($literature),
        ]);

        $this->storeIdentifiers($canonicalWork, $identifiers);
        $this->projector->refresh($canonicalWork);

        return $mapping->load('canonicalWork');
    }

    /**
     * @param  list<array{scheme: string, value: string, source_key: string|null}>  $identifiers
     * @return Collection<int, CanonicalWork>
     */
    private function identifierCandidates(array $identifiers): Collection
    {
        if ($identifiers === []) {
            return new Collection;
        }

        $identifierRecords = CanonicalWorkIdentifier::query()
            ->where(function ($query) use ($identifiers): void {
                foreach ($identifiers as $identifier) {
                    $query->orWhere(function ($query) use ($identifier): void {
                        $query
                            ->where('scheme', $identifier['scheme'])
                            ->where('value', $identifier['value']);
                    });
                }
            })
            ->get();

        return CanonicalWork::query()
            ->whereKey($identifierRecords->pluck('canonical_work_id')->unique())
            ->get();
    }

    /**
     * @return array{work: CanonicalWork|null, method: string, status: string, confidence: float}
     */
    private function compositeCandidate(Literature $literature): array
    {
        $normalizedTitle = $this->identities->normalizeTitle($literature->title);
        $primaryAuthorId = $literature->authors->first()?->id;
        $titleCandidates = CanonicalWork::query()
            ->where('normalized_title', $normalizedTitle)
            ->where('type', $literature->type)
            ->get();

        if ($primaryAuthorId !== null) {
            $authorCandidates = $titleCandidates
                ->where('primary_author_id', $primaryAuthorId)
                ->values();
            $exactYearCandidates = $literature->publication_year === null
                ? $authorCandidates
                : $authorCandidates->where('publication_year', $literature->publication_year)->values();

            if ($exactYearCandidates->count() === 1) {
                return [
                    'work' => $exactYearCandidates->first(),
                    'method' => 'title_author_year',
                    'status' => LiteratureSourceMapping::STATUS_MATCHED,
                    'confidence' => $literature->publication_year === null ? 0.86 : 0.92,
                ];
            }

            if ($exactYearCandidates->isEmpty() && $authorCandidates->count() === 1) {
                $candidate = $authorCandidates->firstOrFail();

                if (
                    $candidate->publication_year === null
                    || $literature->publication_year === null
                    || $literature->type === 'novel'
                ) {
                    return [
                        'work' => $candidate,
                        'method' => $literature->type === 'novel'
                            ? 'title_author_edition'
                            : 'title_author',
                        'status' => LiteratureSourceMapping::STATUS_MATCHED,
                        'confidence' => $literature->type === 'novel' ? 0.90 : 0.86,
                    ];
                }
            }
        }

        if ($titleCandidates->isNotEmpty()) {
            return [
                'work' => null,
                'method' => 'ambiguous_title_separate',
                'status' => LiteratureSourceMapping::STATUS_MATCHED,
                'confidence' => 0.45,
            ];
        }

        return [
            'work' => null,
            'method' => 'created',
            'status' => LiteratureSourceMapping::STATUS_NEW,
            'confidence' => 1.0,
        ];
    }

    private function createCanonicalWork(Literature $literature): CanonicalWork
    {
        return CanonicalWork::query()->create([
            'primary_author_id' => $literature->authors->first()?->id,
            'canonical_title' => $literature->title,
            'normalized_title' => $this->identities->normalizeTitle($literature->title),
            'type' => $literature->type,
            'publication_year' => $literature->publication_year,
        ]);
    }

    private function enrichCanonicalWork(CanonicalWork $canonicalWork, Literature $literature): void
    {
        $canonicalWork->fill([
            'primary_author_id' => $canonicalWork->primary_author_id ?? $literature->authors->first()?->id,
            'publication_year' => $canonicalWork->publication_year ?? $literature->publication_year,
        ]);
        $canonicalWork->save();
    }

    /** @param list<array{scheme: string, value: string, source_key: string|null}> $identifiers */
    private function storeIdentifiers(CanonicalWork $canonicalWork, array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            $existing = CanonicalWorkIdentifier::query()
                ->where('scheme', $identifier['scheme'])
                ->where('value', $identifier['value'])
                ->first();

            if ($existing !== null && $existing->canonical_work_id !== $canonicalWork->id) {
                continue;
            }

            CanonicalWorkIdentifier::query()->firstOrCreate(
                [
                    'scheme' => $identifier['scheme'],
                    'value' => $identifier['value'],
                ],
                [
                    'canonical_work_id' => $canonicalWork->id,
                    'source_key' => $identifier['source_key'],
                ],
            );
        }
    }

    /** @return array<string, string> */
    private function fieldProvenance(Literature $literature): array
    {
        $fields = [
            'title',
            'original_title',
            'type',
            'publication_year',
            'tagline',
            'synopsis',
            'publisher',
            'language',
            'format',
            'identifier',
            'cover_url',
            'backdrop_url',
            'knowledge_graph_id',
        ];

        return collect($fields)
            ->filter(fn (string $field): bool => filled($literature->getAttribute($field)))
            ->mapWithKeys(fn (string $field): array => [$field => $literature->apiSource->key])
            ->all();
    }
}
