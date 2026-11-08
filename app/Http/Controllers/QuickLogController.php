<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickLogSearchRequest;
use App\Models\Literature;
use App\Models\ReadingList;
use App\Models\Review;
use App\Services\Literature\CanonicalLiteratureSearch;
use App\Services\Literature\CanonicalWorkIdentity;
use App\Support\ReviewDraftContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QuickLogController extends Controller
{
    public function __invoke(
        QuickLogSearchRequest $request,
        CanonicalLiteratureSearch $canonicalSearch,
        CanonicalWorkIdentity $canonicalIdentity,
        ReviewDraftContext $draftContext,
    ): JsonResponse {
        $query = Str::squish((string) $request->validated('q', ''));

        $literatures = $canonicalSearch->rankedQuery($query, preferPopularity: true)
            ->limit(8)
            ->get();
        $canonicalIds = $literatures->pluck('sourceMapping.canonical_work_id')->filter()->values();
        $literatureIds = $literatures->modelKeys();
        $reviews = Review::query()
            ->select(['id', 'user_id', 'literature_id', 'rating', 'body', 'contains_spoiler', 'created_at', 'updated_at'])
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
        $readingLists = ReadingList::query()
            ->select(['id', 'user_id', 'literature_id', 'status', 'completed_at'])
            ->whereBelongsTo($request->user())
            ->where(function (Builder $readingListQuery) use ($literatureIds, $canonicalIds): void {
                $readingListQuery->whereIn('literature_id', $literatureIds);

                if ($canonicalIds->isNotEmpty()) {
                    $readingListQuery->orWhereHas('literature.sourceMapping', fn (Builder $mapping) => $mapping
                        ->whereIn('canonical_work_id', $canonicalIds));
                }
            })
            ->with('literature.sourceMapping')
            ->latest('updated_at')
            ->get()
            ->keyBy(fn (ReadingList $readingList): string => $this->reviewKey($readingList->literature));

        $results = $literatures
            ->map(function (Literature $literature) use ($readingLists, $reviews, $canonicalIdentity, $draftContext, $request): array {
                $review = $reviews->get($this->reviewKey($literature));
                $readingList = $readingLists->get($this->reviewKey($literature));
                $reviewLiterature = $canonicalIdentity->representative($review?->literature ?? $literature);

                return [
                    'id' => $literature->id,
                    'preview_url' => route('literatures.preview', $reviewLiterature),
                    'review_draft_context' => $draftContext->present($request->user(), $literature, $review, $readingList?->completed_at?->toDateString()),
                    'title' => $literature->displayTitle(),
                    'year' => $literature->displayPublicationYear(),
                    'type' => $literature->typeLabel(),
                    'cover_url' => $literature->displayCoverUrl(),
                    'authors' => $literature->authors->pluck('name')->values()->all(),
                    'review_url' => route('reviews.update', $reviewLiterature),
                    'completed_at' => $readingList?->completed_at?->toDateString(),
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
