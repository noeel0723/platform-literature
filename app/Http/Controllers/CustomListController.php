<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomListRequest;
use App\Http\Requests\UpdateCustomListRequest;
use App\Models\CustomList;
use App\Models\CustomListComment;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use App\Services\Lists\CustomListManager;
use App\Services\Lists\CustomListReadingProgress;
use App\Support\ProfilePagePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomListController extends Controller
{
    public function discover(Request $request, ProfilePagePresenter $presenter): Response
    {
        $viewer = $request->user();
        $lists = CustomList::query()
            ->where('is_private', false)
            ->whereHas('user', fn (Builder $users) => $users
                ->whereNull('deactivated_at')
                ->when($viewer !== null, fn (Builder $users) => $users
                    ->whereDoesntHave('blockedUsers', fn (Builder $blocked) => $blocked->whereKey($viewer->id))
                    ->whereDoesntHave('blockers', fn (Builder $blockers) => $blockers->whereKey($viewer->id))))
            ->with([
                'user:id,name,username,avatar_path,role',
                'items' => fn ($items) => $items->with([
                    'literature.authors',
                    'literature.metadataOverride',
                    'literature.sourceMapping.canonicalWork.metadataOverride',
                    'canonicalWork.preferredLiterature.authors',
                    'canonicalWork.preferredLiterature.metadataOverride',
                    'canonicalWork.preferredLiterature.sourceMapping.canonicalWork.metadataOverride',
                ])->limit(5),
            ])
            ->withCount('items')
            ->latest('updated_at')
            ->latest('id');
        $present = fn (CustomList $list): array => [
            ...$presenter->customList($list, false),
            'owner' => [
                ...$presenter->user($list->user),
                'avatar_url' => $list->user->avatarUrl() ?? ($list->user->isAdmin() ? asset('literahaven-logo.png') : null),
            ],
        ];
        $officialLists = (clone $lists)
            ->whereHas('user', fn (Builder $users) => $users->where('role', User::ROLE_ADMIN))
            ->paginate(6, ['*'], 'official_page')->withQueryString()->fragment('official-lists')
            ->through($present);
        $readerLists = (clone $lists)
            ->whereHas('user', fn (Builder $users) => $users->where('role', User::ROLE_USER))
            ->paginate(6, ['*'], 'readers_page')->withQueryString()->fragment('readers-lists')
            ->through($present);

        return Inertia::render('Lists/Index', [
            'officialLists' => $officialLists,
            'readerLists' => $readerLists,
            'routes' => [
                'create' => $viewer?->can('create', CustomList::class) ? route('custom-lists.create') : null,
                'verification' => $viewer?->requiresEmailVerification() ? route('verification.notice') : null,
            ],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, User $user, ProfilePagePresenter $presenter): Response
    {
        $isOwner = $request->user()?->canUseInteractiveFeatures() && $request->user()->is($user);
        $lists = $user->customLists()
            ->when(! $isOwner, fn ($query) => $query->where('is_private', false))
            ->with(['items' => fn ($query) => $query
                ->with([
                    'literature.authors',
                    'literature.metadataOverride',
                    'literature.sourceMapping.canonicalWork.metadataOverride',
                    'canonicalWork.preferredLiterature.authors',
                    'canonicalWork.preferredLiterature.metadataOverride',
                    'canonicalWork.preferredLiterature.sourceMapping.canonicalWork.metadataOverride',
                ])
                ->limit(5)])
            ->withCount('items')
            ->latest('updated_at')
            ->paginate(12);

        $lists->through(fn (CustomList $list): array => $presenter->customList($list, $isOwner));

        return Inertia::render('Profile/Lists', [
            'profile' => $presenter->user($user),
            'navigation' => $presenter->navigation($user, 'lists', $request->user()),
            'lists' => $lists,
            'isOwner' => $isOwner,
            'createUrl' => route('custom-lists.create'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', CustomList::class);

        return Inertia::render('Lists/Edit', [
            'list' => null,
            'formUrl' => route('custom-lists.store'),
            'profileUrl' => route('profiles.lists', $request->user()),
            'searchUrl' => route('quick-log.literatures'),
            'items' => [],
            'addUrl' => null,
            'reorderUrl' => null,
            'showUrl' => null,
            'successMessage' => session('success'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomListRequest $request, CustomListManager $manager): RedirectResponse
    {
        Gate::authorize('create', CustomList::class);
        $list = $manager->create($request->user(), $request->validated());

        if ($request->validated('return_to') === 'literature' && $request->validated('literature_id')) {
            $literature = Literature::query()->findOrFail($request->validated('literature_id'));

            return redirect()->route('literatures.show', $literature)->with('success', 'List created and literature added.');
        }

        return redirect()->route('custom-lists.edit', $list)->with('success', 'List created. Add literature below.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, CustomList $customList, ProfilePagePresenter $presenter, CustomListReadingProgress $readingProgress): Response
    {
        Gate::authorize('view', $customList);
        $customList->load('user');
        $items = $customList->items()
            ->with([
                'literature.authors',
                'literature.metadataOverride',
                'literature.sourceMapping.canonicalWork.metadataOverride',
                'canonicalWork.preferredLiterature.authors',
                'canonicalWork.preferredLiterature.metadataOverride',
                'canonicalWork.preferredLiterature.sourceMapping.canonicalWork.metadataOverride',
            ])
            ->paginate(24)->withQueryString();
        $viewer = $request->user();
        $progress = $readingProgress->forViewer($customList, $viewer, $items->total(), $items->getCollection()->modelKeys());
        $completedIds = array_flip($progress['item_ids'] ?? []);
        $firstLiterature = $customList->items()->with([
            'literature.metadataOverride',
            'literature.sourceMapping.canonicalWork.metadataOverride',
            'canonicalWork.preferredLiterature.metadataOverride',
            'canonicalWork.preferredLiterature.sourceMapping.canonicalWork.metadataOverride',
        ])->first()?->displayLiterature();
        $hero = $firstLiterature === null ? null : [
            'title' => $firstLiterature->displayTitle(),
            'year' => $firstLiterature->displayPublicationYear(),
            'url' => route('literatures.show', $firstLiterature),
            'backdrop_url' => $firstLiterature->displayBackdropUrl(),
            'cover_url' => $firstLiterature->displayCoverUrl(),
        ];
        $items->through(function (CustomListItem $item) use ($presenter, $customList, $completedIds): array {
            $literature = $item->displayLiterature();

            return [
                'id' => $item->id,
                'position' => $item->position,
                'completed' => isset($completedIds[$item->id]),
                'literature' => $literature === null ? null : $presenter->literature($literature),
                'remove_url' => route('custom-lists.items.destroy', [$customList, $item]),
            ];
        });

        $commentParameters = $items->currentPage() > 1 ? ['page' => $items->currentPage()] : [];
        $comments = $customList->comments()
            ->whereHas('user', fn (Builder $users) => $users->whereNull('deactivated_at')
                ->when($viewer !== null, fn (Builder $users) => $users
                    ->whereDoesntHave('blockedUsers', fn (Builder $blocked) => $blocked->whereKey($viewer->id))
                    ->whereDoesntHave('blockers', fn (Builder $blockers) => $blockers->whereKey($viewer->id))))
            ->with('user:id,name,username,avatar_path')
            ->latest('created_at')->latest('id')
            ->paginate(20, ['*'], 'comments_page')->withQueryString()->fragment('comments')
            ->through(fn (CustomListComment $comment): array => [
                'id' => $comment->id,
                'body' => $comment->body,
                'created_at' => $comment->created_at->utc()->toIso8601String(),
                'user' => $presenter->user($comment->user),
                'delete_url' => $viewer?->can('deleteComment', [$customList, $comment])
                    ? route('custom-lists.comments.destroy', [$customList, $comment, ...$commentParameters]) : null,
            ]);
        $canComment = $viewer?->can('comment', $customList) ?? false;
        $likeParameters = $commentParameters;
        if ($comments->currentPage() > 1) {
            $likeParameters['comments_page'] = $comments->currentPage();
        }
        $canLike = $viewer?->can('like', $customList) ?? false;

        return Inertia::render('Lists/Show', [
            'list' => [
                'id' => $customList->id,
                'title' => $customList->title,
                'description' => $customList->description,
                'is_private' => $customList->is_private,
                'is_ranked' => $customList->is_ranked,
                'owner' => $presenter->user($customList->user),
                'is_official' => $customList->user->isAdmin(),
                'updated_at' => $customList->updated_at->utc()->toIso8601String(),
                'hero' => $hero,
                'progress' => $progress === null ? null : array_diff_key($progress, ['item_ids' => true]),
                'likes_count' => $customList->likes()->whereHas('user', fn (Builder $users) => $users->whereNull('deactivated_at'))->count(),
                'is_liked' => $viewer !== null && $customList->likes()->where('user_id', $viewer->id)->exists(),
                'like_url' => $canLike ? route('custom-lists.likes.store', [$customList, ...$likeParameters]) : null,
                'unlike_url' => $canLike ? route('custom-lists.likes.destroy', [$customList, ...$likeParameters]) : null,
                'items' => $items,
                'edit_url' => route('custom-lists.edit', $customList),
                'delete_url' => route('custom-lists.destroy', $customList),
                'reorder_url' => route('custom-lists.items.reorder', $customList),
            ],
            'canEdit' => $request->user()?->can('update', $customList) ?? false,
            'comments' => $comments,
            'commenting' => [
                'authenticated' => $viewer !== null,
                'store_url' => $canComment ? route('custom-lists.comments.store', [$customList, ...$commentParameters]) : null,
                'verification_url' => $viewer?->requiresEmailVerification() ? route('verification.notice') : null,
            ],
            'successMessage' => session('success'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CustomList $customList, ProfilePagePresenter $presenter): Response
    {
        Gate::authorize('update', $customList);
        $customList->load(['items' => fn ($query) => $query->with([
            'literature.authors',
            'literature.metadataOverride',
            'literature.sourceMapping.canonicalWork.metadataOverride',
            'canonicalWork.preferredLiterature.authors',
            'canonicalWork.preferredLiterature.metadataOverride',
            'canonicalWork.preferredLiterature.sourceMapping.canonicalWork.metadataOverride',
        ])]);
        $literatureIds = $customList->items
            ->map(fn (CustomListItem $item): ?int => $item->displayLiterature()?->id)
            ->filter()
            ->values();
        $averageRatings = Review::query()
            ->whereIn('literature_id', $literatureIds)
            ->whereNull('hidden_at')
            ->selectRaw('literature_id, AVG(rating) as average_rating')
            ->groupBy('literature_id')
            ->pluck('average_rating', 'literature_id');
        $items = $customList->items->map(function (CustomListItem $item) use ($customList, $presenter, $averageRatings): ?array {
            $literature = $item->displayLiterature();

            if ($literature === null) {
                return null;
            }

            return [
                'id' => $item->id,
                'position' => $item->position,
                'literature' => $presenter->literature($literature),
                'average_rating' => $averageRatings->has($literature->id)
                    ? (float) $averageRatings->get($literature->id)
                    : null,
                'remove_url' => route('custom-lists.items.destroy', [$customList, $item]),
            ];
        })->filter()->values();

        return Inertia::render('Lists/Edit', [
            'list' => [
                'title' => $customList->title,
                'description' => $customList->description ?? '',
                'is_private' => $customList->is_private,
                'is_ranked' => $customList->is_ranked,
            ],
            'formUrl' => route('custom-lists.update', $customList),
            'profileUrl' => route('profiles.lists', $customList->user),
            'searchUrl' => route('quick-log.literatures'),
            'items' => $items,
            'addUrl' => route('custom-lists.items.store', $customList),
            'reorderUrl' => route('custom-lists.items.reorder', $customList),
            'showUrl' => route('custom-lists.show', $customList),
            'successMessage' => session('success'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCustomListRequest $request, CustomList $customList, CustomListManager $manager): RedirectResponse
    {
        $manager->update($customList, $request->validated());

        return redirect()->route('custom-lists.show', $customList)->with('success', 'List updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomList $customList): RedirectResponse
    {
        Gate::authorize('delete', $customList);
        $owner = $customList->user;
        $customList->delete();

        return redirect()->route('profiles.lists', $owner)->with('success', 'List deleted.');
    }
}
