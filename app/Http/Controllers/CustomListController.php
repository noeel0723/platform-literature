<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomListRequest;
use App\Http\Requests\UpdateCustomListRequest;
use App\Models\CustomList;
use App\Models\CustomListItem;
use App\Models\Literature;
use App\Models\Review;
use App\Models\User;
use App\Services\Lists\CustomListManager;
use App\Support\ProfilePagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, User $user, ProfilePagePresenter $presenter): Response
    {
        $lists = $user->customLists()
            ->when(! $request->user()?->is($user), fn ($query) => $query->where('is_private', false))
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

        $isOwner = $request->user()?->is($user) ?? false;
        $lists->through(fn (CustomList $list): array => $this->presentList($list, $presenter, $isOwner));

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
    public function show(Request $request, CustomList $customList, ProfilePagePresenter $presenter): Response
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
            ])
            ->paginate(24);
        $items->through(function (CustomListItem $item) use ($presenter, $customList): array {
            $literature = $item->displayLiterature();

            return [
                'id' => $item->id,
                'position' => $item->position,
                'literature' => $literature === null ? null : $presenter->literature($literature),
                'remove_url' => route('custom-lists.items.destroy', [$customList, $item]),
            ];
        });

        return Inertia::render('Lists/Show', [
            'list' => [
                'id' => $customList->id,
                'title' => $customList->title,
                'description' => $customList->description,
                'is_private' => $customList->is_private,
                'is_ranked' => $customList->is_ranked,
                'owner' => $presenter->user($customList->user),
                'items' => $items,
                'edit_url' => route('custom-lists.edit', $customList),
                'delete_url' => route('custom-lists.destroy', $customList),
                'reorder_url' => route('custom-lists.items.reorder', $customList),
            ],
            'canEdit' => $request->user()?->can('update', $customList) ?? false,
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

    /** @return array<string, mixed> */
    private function presentList(CustomList $list, ProfilePagePresenter $presenter, bool $isOwner): array
    {
        return [
            'id' => $list->id,
            'title' => $list->title,
            'description' => $list->description,
            'is_private' => $list->is_private,
            'is_ranked' => $list->is_ranked,
            'items_count' => $list->items_count,
            'url' => route('custom-lists.show', $list),
            'edit_url' => $isOwner ? route('custom-lists.edit', $list) : null,
            'updated_at' => $list->updated_at->utc()->toIso8601String(),
            'previews' => $list->items->map(function (CustomListItem $item) use ($presenter): ?array {
                $literature = $item->displayLiterature();

                return $literature === null ? null : $presenter->literature($literature);
            })->filter()->values()->all(),
        ];
    }
}
