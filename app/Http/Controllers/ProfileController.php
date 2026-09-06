<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Author;
use App\Models\Literature;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user): View
    {
        $user->load(['favoriteLiteratures.authors', 'favoriteAuthors'])
            ->loadCount([
                'readingLists',
                'reviews',
                'discussions',
                'readingLists as completed_literature_count' => fn ($query) => $query->where('status', 'completed'),
            ]);

        $recentReviews = $user->reviews()
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

        return view('profiles.show', compact('user', 'recentReviews', 'recentCompletions'));
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
        $user->update($request->safe()->only(['name', 'username', 'location', 'bio']));

        $user->favoriteLiteratures()->sync($this->positionedIds($request->validated('favorite_literature_ids', [])));
        $user->favoriteAuthors()->sync($this->positionedIds($request->validated('favorite_author_ids', [])));

        return redirect()->route('profiles.show', $user)
            ->with('success', 'Your profile has been updated.');
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
}
