<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickLogSearchRequest;
use App\Models\Literature;
use App\Models\Review;
use App\Services\Literature\CanonicalLiteratureSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QuickLogController extends Controller
{
    public function __invoke(
        QuickLogSearchRequest $request,
        CanonicalLiteratureSearch $canonicalSearch,
    ): JsonResponse {
        $query = Str::squish((string) $request->validated('q', ''));
        $normalizedQuery = Str::lower($query);

        $literatures = $canonicalSearch->query($query)
            ->select(['id', 'api_source_id', 'slug', 'title', 'original_title', 'type', 'publication_year', 'cover_url', 'updated_at'])
            ->with(['authors:id,name', 'sourceMapping:id,canonical_work_id,literature_id', 'metadataOverride'])
            ->when($query !== '', function (Builder $literatures) use ($normalizedQuery): void {
                $literatures->orderByRaw(
                    'CASE WHEN LOWER(title) = ? OR LOWER(original_title) = ? THEN 0 ELSE 1 END',
                    [$normalizedQuery, $normalizedQuery],
                );
            })
            ->latest('updated_at')
            ->latest('id')
            ->limit(8)
            ->get();
        $canonicalIds = $literatures->pluck('sourceMapping.canonical_work_id')->filter()->values();
        $literatureIds = $literatures->modelKeys();
        $reviews = Review::query()
            ->select(['id', 'user_id', 'literature_id', 'rating', 'body', 'contains_spoiler', 'created_at'])
            ->whereBelongsTo($request->user())
            ->whereNull('hidden_at')
            ->where(function (Builder $reviewQuery) use ($literatureIds, $canonicalIds): void {
                $reviewQuery->whereIn('literature_id', $literatureIds);

                if ($canonicalIds->isNotEmpty()) {
                    $reviewQuery->orWhereHas('literature.sourceMapping', fn (Builder $mapping) => $mapping
                        ->whereIn('canonical_work_id', $canonicalIds));
                }
            })
            ->with('literature.sourceMapping')
            ->latest('created_at')
            ->get()
            ->keyBy(fn (Review $review): string => $this->reviewKey($review->literature));

        $results = $literatures
            ->map(function (Literature $literature) use ($reviews): array {
                $review = $reviews->get($this->reviewKey($literature));
                $reviewLiterature = $review?->literature ?? $literature;

                return [
                    'id' => $literature->id,
                    'title' => $literature->displayTitle(),
                    'year' => $literature->displayPublicationYear(),
                    'type' => $literature->typeLabel(),
                    'cover_url' => $literature->displayCoverUrl(),
                    'authors' => $literature->authors->pluck('name')->values()->all(),
                    'review_url' => route('reviews.update', $reviewLiterature),
                    'review' => $review === null ? null : [
                        'rating' => $review->rating,
                        'body' => $review->body,
                        'contains_spoiler' => $review->contains_spoiler,
                    ],
                ];
            });

        return response()->json(['data' => $results]);
    }

    private function reviewKey(Literature $literature): string
    {
        return $literature->sourceMapping?->canonical_work_id === null
            ? 'literature:'.$literature->id
            : 'canonical:'.$literature->sourceMapping->canonical_work_id;
    }
}
