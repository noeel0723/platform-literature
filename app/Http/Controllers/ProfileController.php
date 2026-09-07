<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Author;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user): View
    {
        $user->load(['favoriteLiteratures.authors', 'favoriteAuthors'])
            ->loadCount([
                'readingLists',
                'reviews' => fn ($query) => $query->whereNull('hidden_at'),
                'discussions' => fn ($query) => $query->whereNull('hidden_at'),
                'followers',
                'following',
                'readingLists as completed_literature_count' => fn ($query) => $query->where('status', 'completed'),
                'readingLists as readlist_count' => fn ($query) => $query->where('status', 'want_to_read'),
            ]);

        $readlistPreview = $user->readingLists()
            ->where('status', 'want_to_read')
            ->with('literature.authors')
            ->latest('updated_at')
            ->limit(4)
            ->get();

        $recentReviews = $user->reviews()
            ->whereNull('hidden_at')
            ->with('literature')
            ->latest('updated_at')
            ->limit(4)
            ->get();

        $recentCompletions = $user->readingLists()
            ->where('status', 'completed')
            ->with('literature')
            ->latest('completed_at')
            ->limit(4)
            ->get();

        $isFollowing = request()->user()?->isFollowing($user) ?? false;

        return view('profiles.show', compact('user', 'readlistPreview', 'recentReviews', 'recentCompletions', 'isFollowing'));
    }

    public function edit(Request $request): View
    {
        $user = $request->user()->load(['favoriteLiteratures', 'favoriteAuthors']);

        return view('profiles.edit', [
            'user' => $user,
            'literatures' => Literature::query()->orderByRaw('COALESCE(original_title, title)')->get(['id', 'title', 'original_title']),
            'authors' => Author::query()->orderBy('name')->get(['id', 'name']),
            'favoriteLiteratureIds' => $user->favoriteLiteratures->pluck('id')->all(),
            'favoriteAuthorIds' => $user->favoriteAuthors->pluck('id')->all(),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profileData = $request->safe()->only(['name', 'username', 'location', 'bio']);

        if ($request->hasFile('avatar')) {
            $newAvatarPath = $request->file('avatar')->storePublicly('avatars', 'public');

            if (! is_string($newAvatarPath)) {
                abort(500, 'The profile photo could not be stored.');
            }

            if ($user->avatar_path !== null) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            $profileData['avatar_path'] = $newAvatarPath;
        } elseif ($request->boolean('remove_avatar') && $user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
            $profileData['avatar_path'] = null;
        }

        $user->update($profileData);

        $user->favoriteLiteratures()->sync($this->positionedIds($request->validated('favorite_literature_ids', [])));
        $user->favoriteAuthors()->sync($this->positionedIds($request->validated('favorite_author_ids', [])));

        return redirect()->route('profiles.show', $user)
            ->with('success', 'Your profile has been updated.');
    }

    public function followers(User $user): View
    {
        return $this->connections($user, 'followers');
    }

    public function following(User $user): View
    {
        return $this->connections($user, 'following');
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{position: int}>
     */
    private function positionedIds(array $ids): array
    {
        return collect($ids)
            ->mapWithKeys(fn (int $id, int $index): array => [$id => ['position' => $index + 1]])
            ->all();
    }

    private function connections(User $user, string $relationship): View
    {
        $connections = $user->{$relationship}()
            ->withCount(['followers', 'following'])
            ->orderBy('name')
            ->paginate(24);

        return view('profiles.connections', [
            'user' => $user,
            'connections' => $connections,
            'relationship' => $relationship,
        ]);
    }
}
