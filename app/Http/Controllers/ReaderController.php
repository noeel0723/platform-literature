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
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = Str::squish(ltrim(trim($validated['q'] ?? ''), '@'));
        $viewer = $request->user();
        $canFollow = $viewer?->canUseInteractiveFeatures() ?? false;
        $readers = User::query()
            ->select(['id', 'name', 'username', 'avatar_path'])
            ->whereNull('deactivated_at')
            ->when($query !== '', fn (Builder $users) => $users->where(fn (Builder $names) => $names
                ->whereLike('name', "%{$query}%")
                ->orWhereLike('username', "%{$query}%")))
            ->when($viewer !== null, fn (Builder $users) => $users
                ->whereDoesntHave('blockedUsers', fn (Builder $blocked) => $blocked->whereKey($viewer->id))
                ->whereDoesntHave('blockers', fn (Builder $blockers) => $blockers->whereKey($viewer->id))
                ->withExists([
                    'followers as viewer_follows' => fn (Builder $followers) => $followers->whereKey($viewer->id),
                    'following as follows_viewer' => fn (Builder $following) => $following->whereKey($viewer->id),
                ]))
            ->withCount([
                'followers' => fn (Builder $followers) => $followers->whereNull('deactivated_at'),
                'reviews' => fn (Builder $reviews) => $reviews->whereNull('hidden_at')->written(),
            ])
            ->with(['favoriteLiteratures' => fn ($favorites) => $favorites
                ->whereIn('type', Literature::supportedTypes())
                ->with(['metadataOverride', 'sourceMapping.canonicalWork.metadataOverride'])
                ->limit(4)])
            ->orderByDesc('followers_count')
            ->orderByDesc('reviews_count')
            ->orderBy('name')
            ->orderBy('users.id')
            ->paginate(20)
            ->withQueryString();

        $readers->through(fn (User $reader): array => [
            ...$presenter->user($reader),
            'followers_count' => $reader->followers_count,
            'reviews_count' => $reader->reviews_count,
            'is_self' => $viewer?->is($reader) ?? false,
            'viewer_follows' => (bool) $reader->viewer_follows,
            'follows_viewer' => (bool) $reader->follows_viewer,
            'follow_url' => $canFollow && ! $viewer->is($reader) ? route('profiles.follow.store', $reader) : null,
            'unfollow_url' => $canFollow && ! $viewer->is($reader) ? route('profiles.follow.destroy', $reader) : null,
            'favorites' => $reader->favoriteLiteratures->map(fn (Literature $literature): array => [
                'id' => $literature->id,
                'title' => $literature->displayTitle(),
                'cover_url' => $literature->displayCoverUrl(),
                'initials' => Str::upper(Str::substr($literature->displayTitle(), 0, 2)),
                'url' => route('literatures.show', $literature),
            ])->all(),
        ]);

        return Inertia::render('Readers/Index', [
            'readers' => $readers,
            'query' => $query,
            'routes' => [
                'index' => route('readers.index'),
                'login' => route('login'),
                'verification' => $viewer?->requiresEmailVerification() ? route('verification.notice') : null,
            ],
        ]);
    }
}
