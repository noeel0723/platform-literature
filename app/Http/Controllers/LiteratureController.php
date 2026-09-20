<?php

namespace App\Http\Controllers;

use App\Exceptions\LiteratureSourceUnavailable;
use App\Models\Comment;
use App\Models\Discussion;
use App\Models\Literature;
use App\Models\LiteratureRelation;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Services\Literature\CanonicalLiteratureSearch;
use App\Services\Literature\CanonicalWorkIdentity;
use App\Services\Literature\CatalogResultRanker;
use App\Services\Literature\CatalogSyncService;
use App\Services\Literature\LiteraturePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LiteratureController extends Controller
{
    public function __construct(private LiteraturePresenter $presenter) {}

    public function index(
        Request $request,
        CatalogSyncService $catalogSync,
        CatalogResultRanker $resultRanker,
        CanonicalLiteratureSearch $canonicalSearch,
    ): Response {
        $query = trim((string) $request->query('q', ''));
        $selectedType = trim((string) $request->query('type', ''));
        $selectedType = array_key_exists($selectedType, Literature::TYPE_LABELS) ? $selectedType : '';
        $displayLimit = 4;
        $sourceLimit = 20;
        $unavailableSources = [];

        if ($query !== '' && in_array($selectedType, ['', 'novel'], true)) {
            try {
                $catalogSync->syncNovels(
                    $query,
                    $selectedType === '' ? 'all' : $selectedType,
                    $sourceLimit,
                );
            } catch (LiteratureSourceUnavailable $exception) {
                $unavailableSources[] = $exception->source;
            }
        }

        if ($query !== '' && in_array($selectedType, ['', 'manga', 'manhwa'], true)) {
            try {
                $catalogSync->syncAniList(
                    $query,
                    $selectedType === '' ? 'all' : $selectedType,
                    $sourceLimit,
                );
            } catch (LiteratureSourceUnavailable $exception) {
                $unavailableSources[] = $exception->source;
            }
        }

        if ($query !== '' && in_array($selectedType, ['', 'western-comic'], true)) {
            try {
                $catalogSync->syncComicVine($query, $sourceLimit);
            } catch (LiteratureSourceUnavailable $exception) {
                $unavailableSources[] = $exception->source;
            }
        }

        $sourceWarning = $unavailableSources === []
            ? null
            : implode(' and ', array_unique($unavailableSources))
                .' cannot be reached right now. Results from the local catalog are still available.';

        $literatures = collect();

        if ($query !== '' || $selectedType !== '') {
            $matches = $canonicalSearch->query($query, $selectedType)
                ->latest('updated_at')
                ->latest('id')
                ->limit(100)
                ->get();

            $rankedMatches = $resultRanker->rank($matches, $query);
            $canExpand = $rankedMatches->count() > $displayLimit;
            $literatures = $rankedMatches
                ->take($displayLimit)
                ->map(fn (Literature $literature): array => $this->presenter->present($literature));
        } else {
            $canExpand = $canonicalSearch->query()
                ->latest('updated_at')
                ->latest('id')
                ->limit($displayLimit + 1)
                ->get()
                ->count() > $displayLimit;
            $literatures = collect(Literature::supportedTypes())
                ->map(fn (string $type) => $canonicalSearch->query('', $type)
                    ->latest('updated_at')
                    ->latest('id')
                    ->first())
                ->filter()
                ->take($displayLimit)
                ->map(fn (Literature $literature): array => $this->presenter->present($literature));
        }

        return Inertia::render('Catalog/Index', [
            'literatures' => $literatures->values()->all(),
            'query' => $query,
            'selectedType' => $selectedType,
            'canExpand' => $canExpand,
            'sourceWarning' => $sourceWarning,
            'types' => Literature::TYPE_LABELS,
            'routes' => [
                'catalog' => route('literatures.index'),
                'latest' => route('literatures.latest', array_filter([
                    'q' => $query,
                    'type' => $selectedType,
                ])),
            ],
        ]);
    }

    public function latest(Request $request, CanonicalLiteratureSearch $canonicalSearch): Response
    {
        $query = trim((string) $request->query('q', ''));
        $selectedType = trim((string) $request->query('type', ''));
        $selectedType = array_key_exists($selectedType, Literature::TYPE_LABELS) ? $selectedType : '';
        $literatures = $canonicalSearch->query($query, $selectedType)
            ->latest('updated_at')
            ->latest('id')
            ->paginate(18)
            ->withQueryString()
            ->through(fn (Literature $literature): array => $this->presenter->present($literature));

        return Inertia::render('Catalog/Latest', [
            'literatures' => $literatures,
            'query' => $query,
            'selectedType' => $selectedType,
            'types' => Literature::TYPE_LABELS,
            'routes' => [
                'catalog' => route('literatures.index', array_filter([
                    'q' => $query,
                    'type' => $selectedType,
                ])),
            ],
        ]);
    }

    public function show(Literature $literature, CanonicalWorkIdentity $identity): Response
    {
        $userId = request()->user()?->id ?? 0;

        $literature->load([
            'apiSource',
            'authors',
            'categories',
            'metadataOverride',
            'sourceMapping.canonicalWork.metadataOverride',
            'sourceMapping.canonicalWork.literatures.categories',
            'outgoingRelations.relatedLiterature.apiSource',
            'outgoingRelations.relatedLiterature.authors',
            'outgoingRelations.relatedLiterature.categories',
            'reviews' => fn ($reviews) => $reviews
                ->whereNull('hidden_at')
                ->with([
                    'user',
                    'likes' => fn ($likes) => $likes->where('user_id', $userId),
                ])
                ->withCount('likes'),
        ]);

        $readingList = request()->user()?->readingLists()
            ->whereBelongsTo($literature)
            ->with('progress')
            ->first();
        $currentReview = request()->user()?->reviews()
            ->whereBelongsTo($literature)
            ->first();
        $viewerLists = collect();

        if (request()->user() !== null) {
            $canonicalWork = $identity->canonicalWork($literature);
            $viewerLists = request()->user()->customLists()
                ->with(['items' => fn ($items) => $items->where('canonical_work_id', $canonicalWork->id)])
                ->orderBy('title')
                ->get()
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
        }
        $discussions = $literature->discussions()
            ->whereNull('hidden_at')
            ->with([
                'user',
                'likes' => fn ($likes) => $likes->where('user_id', $userId),
                'topLevelComments.user',
                'topLevelComments.replies.user',
            ])
            ->withCount([
                'comments' => fn ($comments) => $comments->whereNull('hidden_at'),
                'likes',
            ])
            ->latest()
            ->limit(20)
            ->get();
        $relationshipGroups = collect(LiteratureRelation::TYPE_LABELS)
            ->map(function (string $label, string $type) use ($literature): ?array {
                $relations = $literature->outgoingRelations
                    ->where('relation_type', $type)
                    ->filter(fn (LiteratureRelation $relation): bool => $relation->relatedLiterature !== null)
                    ->unique('related_literature_id');

                if ($relations->isEmpty()) {
                    return null;
                }

                return [
                    'type' => $type,
                    'label' => $label,
                    'items' => $relations->map(function (LiteratureRelation $relation): array {
                        return [
                            ...$this->presenter->present($relation->relatedLiterature),
                            'relation_source' => $relation->source ?? 'Internal catalog',
                        ];
                    })->values(),
                ];
            })
            ->filter()
            ->values();
        $authorIds = $literature->authors->pluck('id');
        $relatedIds = $literature->outgoingRelations->pluck('related_literature_id');
        $authorDiscoveries = $authorIds->isEmpty()
            ? collect()
            : Literature::query()
                ->with(['apiSource', 'authors', 'categories'])
                ->canonicalRepresentatives()
                ->where('id', '!=', $literature->id)
                ->whereNotIn('id', $relatedIds)
                ->whereHas('authors', fn (Builder $authors) => $authors->whereIn('authors.id', $authorIds))
                ->orderByDesc('publication_year')
                ->limit(4)
                ->get()
                ->map(fn (Literature $candidate): array => $this->presenter->present($candidate));
        $presentedLiterature = $this->presenter->present($literature);
        $presentedLiterature['genre_links'] = collect($presentedLiterature['genre_links'])
            ->map(fn (array $genre): array => [
                ...$genre,
                'url' => route('literature.browse', ['genre' => $genre['slug']]),
            ])
            ->all();

        return Inertia::render('Catalog/Show', [
            'literature' => $presentedLiterature,
            'viewer' => [
                'authenticated' => request()->user() !== null,
                'id' => request()->user()?->id,
                'is_admin' => request()->user()?->isAdmin() ?? false,
                'reading_status' => $readingList?->status,
                'completed_at' => $readingList?->completed_at?->toDateString(),
                'current_review' => $currentReview === null ? null : [
                    'id' => $currentReview->id,
                    'rating' => $currentReview->rating,
                    'body' => $currentReview->body ?? '',
                    'contains_spoiler' => $currentReview->contains_spoiler,
                ],
                'custom_lists' => $viewerLists->all(),
            ],
            'ratingSummary' => [
                'average' => $literature->reviews->avg('rating'),
                'count' => $literature->reviews->count(),
            ],
            'reviews' => $literature->reviews
                ->sortByDesc('created_at')
                ->map(fn (Review $review): array => $this->presentReview($review, $userId))
                ->values()
                ->all(),
            'discussions' => $discussions
                ->map(fn (Discussion $discussion): array => $this->presentDiscussion($discussion, $userId))
                ->values()
                ->all(),
            'discussionCount' => $literature->discussions()->whereNull('hidden_at')->count(),
            'relationshipGroups' => $relationshipGroups->map(fn (array $group): array => [
                ...$group,
                'items' => $group['items']->all(),
            ])->all(),
            'authorDiscoveries' => $authorDiscoveries->values()->all(),
            'reportReasons' => Report::REASON_LABELS,
            'successMessage' => session('success'),
            'routes' => [
                'catalog' => route('literatures.index'),
                'login' => route('login'),
                'admin_edit_metadata' => request()->user()?->isAdmin()
                    ? route('admin.literatures.metadata.edit', $literature)
                    : null,
                'reading_update' => route('reading-list.update', $literature),
                'reading_destroy' => route('reading-list.destroy', $literature),
                'review_update' => route('reviews.update', $literature),
                'review_destroy' => route('reviews.destroy', $literature),
                'discussion_store' => route('discussions.store', $literature),
                'report_store' => route('reports.store'),
                'custom_list_store' => route('custom-lists.store'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentReview(Review $review, int $userId): array
    {
        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'body' => $review->body,
            'contains_spoiler' => $review->contains_spoiler,
            'likes_count' => $review->likes_count,
            'is_liked' => $review->likes->isNotEmpty(),
            'created_at' => $review->created_at->utc()->toIso8601String(),
            'user' => $this->presentUser($review->user),
            'can_report' => $userId > 0 && $userId !== $review->user_id,
            'like_url' => route('reviews.likes.store', $review),
            'unlike_url' => route('reviews.likes.destroy', $review),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDiscussion(Discussion $discussion, int $userId): array
    {
        return [
            'id' => $discussion->id,
            'title' => $discussion->title,
            'body' => $discussion->body,
            'contains_spoiler' => $discussion->contains_spoiler,
            'comments_count' => $discussion->comments_count,
            'likes_count' => $discussion->likes_count,
            'is_liked' => $discussion->likes->isNotEmpty(),
            'created_at' => $discussion->created_at->utc()->toIso8601String(),
            'user' => $this->presentUser($discussion->user),
            'can_report' => $userId > 0 && $userId !== $discussion->user_id,
            'like_url' => route('discussions.likes.store', $discussion),
            'unlike_url' => route('discussions.likes.destroy', $discussion),
            'comment_url' => route('discussions.comments.store', $discussion),
            'comments' => $discussion->topLevelComments
                ->map(fn (Comment $comment): array => $this->presentComment($comment, $userId))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentComment(Comment $comment, int $userId): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'contains_spoiler' => $comment->contains_spoiler,
            'created_at' => $comment->created_at->utc()->toIso8601String(),
            'user' => $this->presentUser($comment->user),
            'can_report' => $userId > 0 && $userId !== $comment->user_id,
            'replies' => $comment->replies
                ->map(fn (Comment $reply): array => $this->presentComment($reply, $userId))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'url' => route('profiles.show', $user),
            'avatar_url' => $user->avatarUrl(),
            'initial' => Str::upper(Str::substr($user->name, 0, 1)),
        ];
    }
}
