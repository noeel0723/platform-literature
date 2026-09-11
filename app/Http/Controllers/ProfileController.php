<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Activity;
use App\Models\Author;
use App\Models\Literature;
use App\Models\Report;
use App\Models\User;
use App\Services\Literature\CanonicalLiteratureSearch;
use App\Support\ProfilePagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(User $user, ProfilePagePresenter $presenter): Response
    {
        $user->load([
            'favoriteLiteratures.authors',
            'favoriteLiteratures.metadataOverride',
            'favoriteLiteratures.sourceMapping.canonicalWork.metadataOverride',
            'favoriteAuthors',
        ])
            ->loadCount([
                'reviews' => fn ($query) => $query->whereNull('hidden_at'),
                'followers',
                'following',
                'readingLists as completed_literature_count' => fn ($query) => $query->where('status', 'completed'),
                'readingLists as readlist_count' => fn ($query) => $query->where('status', 'want_to_read'),
            ]);

        $readlistPreview = $user->readingLists()
            ->where('status', 'want_to_read')
            ->with(['literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride'])
            ->latest('updated_at')
            ->limit(4)
            ->get();

        $recentReviews = $user->reviews()
            ->whereNull('hidden_at')
            ->with(['literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride'])
            ->withCount('likes')
            ->latest('updated_at')
            ->limit(4)
            ->get();

        $recentCompletions = $user->readingLists()
            ->where('status', 'completed')
            ->with([
                'literature.authors',
                'literature.metadataOverride',
                'literature.sourceMapping.canonicalWork.metadataOverride',
                'literature.reviews' => fn ($reviews) => $reviews
                    ->whereBelongsTo($user)
                    ->whereNull('hidden_at'),
            ])
            ->latest('completed_at')
            ->limit(4)
            ->get();

        $recentActivities = Activity::query()
            ->visibleToReaders()
            ->whereBelongsTo($user)
            ->with(['literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride', 'review', 'discussion', 'comment'])
            ->latest('occurred_at')
            ->limit(5)
            ->get();

        $ratingDistribution = $user->reviews()
            ->whereNull('hidden_at')
            ->select('rating')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->map(fn ($total): int => (int) $total);

        $isFollowing = request()->user()?->isFollowing($user) ?? false;

        $viewer = request()->user();
        $isOwner = $viewer?->is($user) ?? false;
        $isFriend = $viewer !== null
            && ! $isOwner
            && $isFollowing
            && $user->isFollowing($viewer);
        $ratingMaximum = max(1, (int) ($ratingDistribution->max() ?? 0));

        return Inertia::render('Profile/Show', [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar_url' => $user->avatarUrl(),
                'initials' => $this->initials($user->name),
                'bio' => $user->bio,
                'location' => $user->location,
                'member_since' => $user->created_at->format('F Y'),
                'is_owner' => $isOwner,
                'is_following' => $isFollowing,
                'is_friend' => $isFriend,
                'viewer_authenticated' => $viewer !== null,
                'stats' => [
                    'literature' => $user->completed_literature_count,
                    'reviews' => $user->reviews_count,
                    'following' => $user->following_count,
                    'followers' => $user->followers_count,
                    'readlist' => $user->readlist_count,
                ],
            ],
            'navigation' => $presenter->navigation($user, 'profile', $viewer),
            'favoriteLiteratures' => $user->favoriteLiteratures
                ->map(fn (Literature $literature): array => $this->presentLiterature($literature))
                ->values()
                ->all(),
            'favoriteAuthors' => $user->favoriteAuthors->map(fn (Author $author): array => [
                'id' => $author->id,
                'name' => $author->name,
                'url' => route('authors.show', $author),
                'image_url' => $author->image_url,
                'initials' => $this->initials($author->name),
            ])->values()->all(),
            'recentCompletions' => $recentCompletions->map(fn ($readingList): array => [
                'literature' => $this->presentLiterature($readingList->literature),
                'rating' => $readingList->literature->reviews->first()?->rating,
                'completed_at' => $readingList->completed_at?->utc()->toIso8601String(),
            ])->values()->all(),
            'recentReviews' => $recentReviews->map(fn ($review): array => [
                'id' => $review->id,
                'literature' => $this->presentLiterature($review->literature),
                'rating' => $review->rating,
                'body' => $review->body,
                'contains_spoiler' => $review->contains_spoiler,
                'likes_count' => $review->likes_count,
                'updated_at' => $review->updated_at->utc()->toIso8601String(),
            ])->values()->all(),
            'readlistPreview' => $readlistPreview->map(fn ($item): array => $this->presentLiterature($item->literature))->values()->all(),
            'recentActivities' => $recentActivities->map(fn (Activity $activity): array => [
                'id' => $activity->id,
                'label' => $this->activityLabel($activity),
                'occurred_at' => $activity->occurred_at->utc()->toIso8601String(),
                'literature' => [
                    'title' => $activity->literature->displayTitle(),
                    'url' => route('literatures.show', $activity->literature),
                ],
            ])->values()->all(),
            'ratingDistribution' => collect(range(1, 10))->map(function (int $slot) use ($ratingDistribution, $ratingMaximum): array {
                $rating = number_format($slot / 2, 1, '.', '');
                $count = (int) ($ratingDistribution[$rating] ?? 0);

                return [
                    'rating' => $rating,
                    'count' => $count,
                    'height' => $count === 0 ? 6 : max(14, (int) round(($count / $ratingMaximum) * 100)),
                ];
            })->all(),
            'reportReasons' => Report::REASON_LABELS,
            'routes' => [
                'edit' => $isOwner ? route('profiles.edit') : null,
                'login' => route('login'),
                'follow' => route('profiles.follow.store', $user),
                'unfollow' => route('profiles.follow.destroy', $user),
                'followers' => route('profiles.followers', $user),
                'following' => route('profiles.following', $user),
                'reviews' => route('profiles.reviews', $user),
                'readlist' => route('profiles.readlist', $user),
                'diary' => $isOwner
                    ? route('diary.index')
                    : ($isFriend ? route('profiles.diary', $user) : null),
                'activity' => $isOwner
                    ? route('activity.index')
                    : ($isFriend ? route('profiles.activity', $user) : null),
                'report' => route('reports.store'),
            ],
        ]);
    }

    public function edit(
        Request $request,
        ProfilePagePresenter $presenter,
        CanonicalLiteratureSearch $canonicalSearch,
    ): Response {
        $user = $request->user()->load([
            'favoriteLiteratures.authors',
            'favoriteLiteratures.metadataOverride',
            'favoriteLiteratures.sourceMapping.canonicalWork.metadataOverride',
            'favoriteAuthors',
        ]);
        $literatureOptions = $canonicalSearch->query()
            ->orderByRaw('COALESCE(original_title, title)')
            ->get()
            ->concat($user->favoriteLiteratures)
            ->unique('id')
            ->sortBy(fn (Literature $literature): string => $literature->displayTitle(), SORT_NATURAL | SORT_FLAG_CASE);

        return Inertia::render('Profile/Edit', [
            'profile' => [
                ...$presenter->user($user),
                'location' => $user->location,
                'bio' => $user->bio,
                'has_avatar' => $user->avatar_path !== null,
            ],
            'navigation' => $presenter->navigation($user, 'profile', $user),
            'literatures' => $literatureOptions
                ->map(fn (Literature $literature): array => $presenter->literature($literature))
                ->values()
                ->all(),
            'authors' => Author::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'image_url'])
                ->map(fn (Author $author): array => [
                    'id' => $author->id,
                    'name' => $author->name,
                    'image_url' => $author->image_url,
                    'initials' => $this->initials($author->name),
                ])
                ->values()
                ->all(),
            'favoriteLiteratureIds' => $this->paddedFavoriteIds($user->favoriteLiteratures->pluck('id')->all()),
            'favoriteAuthorIds' => $this->paddedFavoriteIds($user->favoriteAuthors->pluck('id')->all()),
            'routes' => [
                'profile' => route('profiles.show', $user),
                'update' => route('profiles.update'),
            ],
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

    public function followers(User $user, ProfilePagePresenter $presenter): Response
    {
        return $this->connections($user, 'followers', $presenter);
    }

    public function following(User $user, ProfilePagePresenter $presenter): Response
    {
        return $this->connections($user, 'following', $presenter);
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

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int|string>
     */
    private function paddedFavoriteIds(array $ids): array
    {
        return collect($ids)
            ->map(fn (int $id): int|string => $id)
            ->pad(4, '')
            ->take(4)
            ->values()
            ->all();
    }

    private function connections(User $user, string $relationship, ProfilePagePresenter $presenter): Response
    {
        $connections = $user->{$relationship}()
            ->withCount(['followers', 'following'])
            ->orderBy('name')
            ->orderBy('users.id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (User $connection): array => [
                ...$presenter->user($connection),
                'followers_count' => $connection->followers_count,
                'following_count' => $connection->following_count,
            ]);

        return Inertia::render('Profile/Connections', [
            'profile' => $presenter->user($user),
            'navigation' => $presenter->navigation($user, 'profile', request()->user()),
            'connections' => $connections,
            'relationship' => $relationship,
            'routes' => [
                'profile' => route('profiles.show', $user),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentLiterature(Literature $literature): array
    {
        $title = $literature->displayTitle();

        return [
            'id' => $literature->id,
            'title' => $title,
            'url' => route('literatures.show', $literature),
            'cover_url' => $literature->displayCoverUrl(),
            'author' => $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable',
            'year' => $literature->displayPublicationYear(),
            'initials' => mb_strtoupper(mb_substr($title, 0, 2)),
        ];
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: 'LH';
    }

    private function activityLabel(Activity $activity): string
    {
        return match ($activity->type) {
            Activity::TYPE_ADDED_TO_READLIST => 'Added to Readlist',
            Activity::TYPE_STARTED_READING => 'Started',
            Activity::TYPE_COMPLETED => 'Completed',
            Activity::TYPE_RATED => 'Rated',
            Activity::TYPE_REVIEWED => 'Reviewed',
            Activity::TYPE_DISCUSSION => 'Discussed',
            Activity::TYPE_COMMENT => 'Commented on',
            default => 'Updated',
        };
    }
}
