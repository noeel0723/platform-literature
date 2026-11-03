<?php

namespace App\Http\Controllers;

use App\Models\Literature;
use App\Models\User;
use App\Support\ProfilePagePresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReaderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, ProfilePagePresenter $presenter): Response
    {
        $query = $this->searchQuery($request);
        $viewer = $request->user();
        $canFollow = $viewer?->canUseInteractiveFeatures() ?? false;
        $users = $this->visibleUsers($viewer, $query);
        $admins = (clone $users)
            ->where('role', User::ROLE_ADMIN)
            ->withCount(['customLists as lists_count' => fn (Builder $lists) => $lists->where('is_private', false)])
            ->orderBy('name')->orderBy('users.id')
            ->paginate(10, ['*'], 'admins_page')->withQueryString();
        $regularReaders = $this->regularReaders(clone $users);
        $directoryTotal = (clone $regularReaders)->count();
        $directory = (clone $regularReaders)->limit(10)->get()
            ->map(fn (User $reader): array => $this->presentReader($reader, $viewer, $presenter));
        $readers = $regularReaders
            ->with(['favoriteLiteratures' => fn ($favorites) => $favorites
                ->whereIn('type', Literature::supportedTypes())
                ->with(['metadataOverride', 'sourceMapping.canonicalWork.metadataOverride'])
                ->limit(4)])
            ->limit(5)
            ->get();

        $readers = $readers->map(fn (User $reader): array => [
            ...$this->presentReader($reader, $viewer, $presenter),
            'favorites' => $reader->favoriteLiteratures->map(fn (Literature $literature): array => [
                'id' => $literature->id,
                'title' => $literature->displayTitle(),
                'cover_url' => $literature->displayCoverUrl(),
                'initials' => Str::upper(Str::substr($literature->displayTitle(), 0, 2)),
                'url' => route('literatures.show', $literature),
            ])->all(),
        ]);
        $admins->through(fn (User $admin): array => [
            ...$presenter->user($admin),
            'is_official' => true,
            'avatar_url' => $admin->avatarUrl() ?? asset('literahaven-logo.png'),
            'followers_count' => $admin->followers_count,
            'lists_count' => $admin->lists_count,
            'is_self' => $viewer?->is($admin) ?? false,
            'viewer_follows' => (bool) $admin->viewer_follows,
            'follows_viewer' => (bool) $admin->follows_viewer,
            'follow_url' => $canFollow && ! $viewer->is($admin) ? route('profiles.follow.store', $admin) : null,
            'unfollow_url' => $canFollow && ! $viewer->is($admin) ? route('profiles.follow.destroy', $admin) : null,
        ]);

        return Inertia::render('Readers/Index', [
            'readers' => ['data' => $readers->all(), 'total' => $readers->count()],
            'directory' => [
                'data' => $directory->all(),
                'total' => $directoryTotal,
                'more_url' => $directoryTotal > 0 ? route('readers.all', $query !== '' ? ['q' => $query] : []) : null,
            ],
            'admins' => $admins,
            'query' => $query,
            'routes' => [
                'index' => route('readers.index'),
                'login' => route('login'),
                'verification' => $viewer?->requiresEmailVerification() ? route('verification.notice') : null,
            ],
        ]);
    }

    public function all(Request $request, ProfilePagePresenter $presenter): Response
    {
        $query = $this->searchQuery($request);
        $viewer = $request->user();
        $readers = $this->regularReaders($this->visibleUsers($viewer, $query))
            ->paginate(20)->withQueryString()
            ->through(fn (User $reader): array => $this->presentReader($reader, $viewer, $presenter));

        return Inertia::render('Readers/All', [
            'readers' => $readers,
            'query' => $query,
            'routes' => [
                'index' => route('readers.all'),
                'overview' => route('readers.index'),
                'login' => route('login'),
                'verification' => $viewer?->requiresEmailVerification() ? route('verification.notice') : null,
            ],
        ]);
    }

    private function searchQuery(Request $request): string
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return Str::squish(ltrim(trim($validated['q'] ?? ''), '@'));
    }

    /** @return Builder<User> */
    private function visibleUsers(?User $viewer, string $query): Builder
    {
        return User::query()->select(['id', 'name', 'username', 'avatar_path'])
            ->whereNull('deactivated_at')
            ->when($query !== '', fn (Builder $users) => $users->where(fn (Builder $names) => $names
                ->whereLike('name', "%{$query}%")->orWhereLike('username', "%{$query}%")))
            ->when($viewer !== null, fn (Builder $users) => $users
                ->whereDoesntHave('blockedUsers', fn (Builder $blocked) => $blocked->whereKey($viewer->id))
                ->whereDoesntHave('blockers', fn (Builder $blockers) => $blockers->whereKey($viewer->id))
                ->withExists([
                    'followers as viewer_follows' => fn (Builder $followers) => $followers->whereKey($viewer->id),
                    'following as follows_viewer' => fn (Builder $following) => $following->whereKey($viewer->id),
                ]))
            ->withCount(['followers' => fn (Builder $followers) => $followers->whereNull('deactivated_at')]);
    }

    /** @param Builder<User> $users
     * @return Builder<User>
     */
    private function regularReaders(Builder $users): Builder
    {
        return $users->where('role', User::ROLE_USER)->withCount([
            'reviews' => fn (Builder $reviews) => $reviews->whereNull('hidden_at')->written(),
            'readingLists as completed_count' => fn (Builder $items) => $items->where('status', 'completed'),
            'customLists as lists_count' => fn (Builder $lists) => $lists->where('is_private', false),
        ])->orderByDesc('followers_count')->orderByDesc('reviews_count')->orderBy('name')->orderBy('users.id');
    }

    /** @return array<string, mixed> */
    private function presentReader(User $reader, ?User $viewer, ProfilePagePresenter $presenter): array
    {
        $canFollow = ($viewer?->canUseInteractiveFeatures() ?? false) && ! $viewer->is($reader);

        return [
            ...$presenter->user($reader),
            'followers_count' => $reader->followers_count,
            'reviews_count' => $reader->reviews_count,
            'completed_count' => $reader->completed_count,
            'lists_count' => $reader->lists_count,
            'is_self' => $viewer?->is($reader) ?? false,
            'viewer_follows' => (bool) $reader->viewer_follows,
            'follows_viewer' => (bool) $reader->follows_viewer,
            'follow_url' => $canFollow ? route('profiles.follow.store', $reader) : null,
            'unfollow_url' => $canFollow ? route('profiles.follow.destroy', $reader) : null,
        ];
    }
}
