<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $viewer = $request->user();
        $scope = $request->string('scope')->lower()->toString();
        $scope = in_array($scope, ['all', 'you', 'friends'], true) ? $scope : 'all';
        $friendIds = $viewer->following()
            ->whereNull('deactivated_at')
            ->pluck('users.id');

        $userIds = match ($scope) {
            'you' => collect([$viewer->id]),
            'friends' => $friendIds,
            default => $friendIds->prepend($viewer->id),
        };

        $activities = Activity::query()
            ->visibleToReaders()
            ->with(['user', 'literature.authors', 'literature.metadataOverride', 'literature.sourceMapping.canonicalWork.metadataOverride', 'review', 'discussion', 'comment'])
            ->whereIn('user_id', $userIds)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $activities->through(fn (Activity $activity): array => $this->presentActivity($activity));

        return Inertia::render('Activity/Index', [
            'viewer' => [
                'name' => $viewer->name,
                'username' => $viewer->username,
                'avatar_url' => $viewer->avatarUrl(),
                'initials' => $this->initials($viewer->name),
                'url' => route('profiles.show', $viewer),
            ],
            'navigation' => [
                'current' => 'activity',
                'user' => [
                    'name' => $viewer->name,
                    'username' => $viewer->username,
                    'avatar_url' => $viewer->avatarUrl(),
                    'initials' => $this->initials($viewer->name),
                    'url' => route('profiles.show', $viewer),
                ],
                'links' => [
                    ['key' => 'profile', 'label' => 'Profile', 'url' => route('profiles.show', $viewer)],
                    ['key' => 'activity', 'label' => 'Activity', 'url' => route('activity.index')],
                    ['key' => 'literature', 'label' => 'Literature', 'url' => route('profiles.literature', $viewer)],
                    ['key' => 'diary', 'label' => 'Diary', 'url' => route('diary.index')],
                    ['key' => 'reviews', 'label' => 'Reviews', 'url' => route('profiles.reviews', $viewer)],
                    ['key' => 'readlist', 'label' => 'Readlist', 'url' => route('profiles.readlist', $viewer)],
                ],
            ],
            'activities' => $activities,
            'scope' => $scope,
            'scopes' => [
                ['key' => 'all', 'label' => 'You + friends', 'short_label' => 'All', 'url' => route('activity.index')],
                ['key' => 'you', 'label' => 'Your activity', 'short_label' => 'You', 'url' => route('activity.index', ['scope' => 'you'])],
                ['key' => 'friends', 'label' => 'Following', 'short_label' => 'Friends', 'url' => route('activity.index', ['scope' => 'friends'])],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function presentActivity(Activity $activity): array
    {
        $title = $activity->literature->displayTitle();
        $rating = data_get($activity->metadata, 'rating');
        $containsSpoiler = (bool) data_get($activity->metadata, 'contains_spoiler', false);
        $excerpt = data_get($activity->metadata, 'review_excerpt') ?? data_get($activity->metadata, 'excerpt');
        $isExpanded = in_array($activity->type, [Activity::TYPE_RATED, Activity::TYPE_REVIEWED, Activity::TYPE_COMPLETED], true);

        return [
            'id' => $activity->id,
            'type' => $activity->type,
            'action' => match ($activity->type) {
                Activity::TYPE_ADDED_TO_READLIST => 'added to Readlist',
                Activity::TYPE_STARTED_READING => 'started reading',
                Activity::TYPE_COMPLETED => 'completed',
                Activity::TYPE_RATED => 'rated',
                Activity::TYPE_REVIEWED => 'reviewed',
                Activity::TYPE_DISCUSSION => 'started a discussion about',
                Activity::TYPE_COMMENT => data_get($activity->metadata, 'is_reply') ? 'replied to a discussion about' : 'commented on a discussion about',
                default => 'updated',
            },
            'is_expanded' => $isExpanded,
            'rating' => $rating === null ? null : (float) $rating,
            'contains_spoiler' => $containsSpoiler,
            'excerpt' => $excerpt,
            'discussion_title' => data_get($activity->metadata, 'title') ?? data_get($activity->metadata, 'discussion_title'),
            'occurred_at' => $activity->occurred_at->utc()->toIso8601String(),
            'user' => [
                'name' => $activity->user->name,
                'url' => route('profiles.show', $activity->user),
                'avatar_url' => $activity->user->avatarUrl(),
                'initial' => Str::upper(Str::substr($activity->user->name, 0, 1)),
            ],
            'literature' => [
                'title' => $title,
                'url' => route('literatures.show', $activity->literature),
                'cover_url' => $activity->literature->displayCoverUrl(),
                'initials' => Str::upper(Str::substr($title, 0, 2)),
            ],
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
}
