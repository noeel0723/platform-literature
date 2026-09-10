<?php

namespace App\Services\Literature;

use App\Models\CanonicalWork;
use App\Models\LiteratureSourceMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResolveLiteratureMappingReview
{
    public function __construct(private CanonicalLiteratureProjector $projector) {}

    public function resolve(
        LiteratureSourceMapping $mapping,
        string $action,
        ?int $candidateWorkId = null,
    ): void {
        DB::transaction(function () use ($mapping, $action, $candidateWorkId): void {
            $mapping = LiteratureSourceMapping::query()
                ->with(['canonicalWork', 'literature'])
                ->lockForUpdate()
                ->findOrFail($mapping->id);

            if ($mapping->mapping_status !== LiteratureSourceMapping::STATUS_NEEDS_REVIEW) {
                throw ValidationException::withMessages([
                    'mapping' => 'This catalog match has already been reviewed.',
                ]);
            }

            if ($action === 'keep_separate') {
                $mapping->update([
                    'mapping_status' => LiteratureSourceMapping::STATUS_MATCHED,
                    'match_method' => 'admin_distinct',
                    'confidence' => 1,
                    'candidate_work_ids' => null,
                ]);
                $this->projector->refresh($mapping->canonicalWork);

                return;
            }

            $candidateIds = collect($mapping->candidate_work_ids ?? [])->map(fn (mixed $id): int => (int) $id);

            if ($candidateWorkId === null || ! $candidateIds->contains($candidateWorkId)) {
                throw ValidationException::withMessages([
                    'canonical_work_id' => 'Choose one of the suggested canonical works.',
                ]);
            }

            $target = CanonicalWork::query()->lockForUpdate()->findOrFail($candidateWorkId);
            $current = $mapping->canonicalWork;

            if ($target->id === $current->id || $target->type !== $mapping->literature->type) {
                throw ValidationException::withMessages([
                    'canonical_work_id' => 'The selected canonical work is not a valid match.',
                ]);
            }

            LiteratureSourceMapping::query()
                ->whereBelongsTo($current, 'canonicalWork')
                ->update(['canonical_work_id' => $target->id]);
            $mapping->update([
                'mapping_status' => LiteratureSourceMapping::STATUS_MATCHED,
                'match_method' => 'admin_review',
                'confidence' => 1,
                'candidate_work_ids' => null,
            ]);
            $current->identifiers()->update(['canonical_work_id' => $target->id]);

            $this->projector->refresh($target);
            $this->projector->refresh($current);
        });
    }
}
