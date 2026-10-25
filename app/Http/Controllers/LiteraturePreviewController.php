<?php

namespace App\Http\Controllers;

use App\Models\CanonicalWorkLink;
use App\Models\Literature;
use App\Services\Literature\CanonicalWorkIdentity;
use App\Support\ReviewDraftContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiteraturePreviewController extends Controller
{
    public function __invoke(Request $request, Literature $literature, CanonicalWorkIdentity $identity, ReviewDraftContext $draftContext): JsonResponse
    {
        $literature = $identity->representative($literature);
        $literature->loadMissing(['authors', 'metadataOverride', 'sourceMapping.canonicalWork.metadataOverride']);
        $user = $request->user()?->hasVerifiedEmail() ? $request->user() : null;
        $equivalentIds = $user === null ? collect() : $identity->equivalentLiteratureIds($literature);
        $review = $user?->reviews()
            ->whereIn('literature_id', $equivalentIds)
            ->whereNull('hidden_at')
            ->latest('updated_at')
            ->first(['id', 'rating', 'body', 'contains_spoiler', 'updated_at']);
        $reading = $user?->readingLists()
            ->whereIn('literature_id', $equivalentIds)
            ->latest('updated_at')
            ->first(['status', 'completed_at']);
        $canonicalWork = $literature->sourceMapping?->canonicalWork;
        $lists = $user?->customLists()
            ->with(['items' => fn ($items) => $canonicalWork === null
                ? $items->whereRaw('1 = 0')
                : $items->where('canonical_work_id', $canonicalWork->id)])
            ->orderBy('title')
            ->get(['id', 'user_id', 'slug', 'title', 'is_private'])
            ->map(fn ($list): array => [
                'id' => $list->id,
                'title' => $list->title,
                'is_private' => $list->is_private,
                'contains' => $list->items->isNotEmpty(),
                'store_url' => route('custom-lists.items.store', $list),
                'destroy_url' => $list->items->isEmpty()
                    ? null
                    : route('custom-lists.items.destroy', [$list, $list->items->first()]),
            ]);
        $hasReadingLinks = $canonicalWork?->links()
            ->where('is_official', true)
            ->where('is_active', true)
            ->whereIn('link_type', array_keys(CanonicalWorkLink::TYPE_LABELS))
            ->whereNotNull('verified_at')
            ->get(['url'])
            ->contains(fn ($link): bool => filter_var($link->url, FILTER_VALIDATE_URL) !== false
                && in_array(parse_url($link->url, PHP_URL_SCHEME), ['http', 'https'], true)) ?? false;

        return response()->json([
            'literature' => [
                'id' => $literature->id,
                'preview_url' => route('literatures.preview', $literature),
                'review_draft_context' => $draftContext->present($user, $literature, $review, $reading?->completed_at?->toDateString()),
                'title' => $literature->displayTitle(),
                'type' => $literature->typeLabel(),
                'year' => $literature->displayPublicationYear(),
                'cover_url' => $literature->displayCoverUrl(),
                'authors' => $literature->authors->pluck('name')->all(),
                'completed_at' => $reading?->completed_at?->toDateString(),
                'review_url' => $user === null ? null : route('reviews.update', $literature),
                'review' => $review === null ? null : [
                    'rating' => $review->rating,
                    'body' => $review->body ?? '',
                    'contains_spoiler' => $review->contains_spoiler,
                ],
            ],
            'viewer' => [
                'authenticated' => $user !== null,
                'reading_status' => $reading?->status,
            ],
            'lists' => $lists?->values()->all() ?? [],
            'routes' => [
                'verification' => $request->user() !== null && ! $request->user()->hasVerifiedEmail() ? route('verification.notice') : null,
                'details' => route('literatures.show', $literature),
                'where_to_read' => $hasReadingLinks ? route('literatures.show', $literature).'#where-to-read' : null,
                'reading_update' => $user === null ? null : route('reading-list.update', $literature),
                'reading_destroy' => $user === null ? null : route('reading-list.destroy', $literature),
                'create_list' => $user === null ? null : route('custom-lists.create'),
                'quick_log_search' => $user === null ? null : route('quick-log.literatures'),
            ],
            'csrf_token' => $user === null ? null : csrf_token(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
