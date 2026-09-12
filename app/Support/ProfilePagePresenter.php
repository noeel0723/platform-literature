<?php

namespace App\Support;

use App\Models\Literature;
use App\Models\User;

class ProfilePagePresenter
{
    /** @return array<string, mixed> */
    public function navigation(User $user, string $current, ?User $viewer): array
    {
        $isOwner = $viewer?->is($user) ?? false;
        $isFriend = $viewer !== null
            && ! $isOwner
            && $viewer->isFollowing($user)
            && $user->isFollowing($viewer);
        $links = [
            ['key' => 'profile', 'label' => 'Profile', 'url' => route('profiles.show', $user)],
            ['key' => 'stats', 'label' => 'Stats', 'url' => route('profiles.stats', $user)],
        ];

        if ($isOwner || $isFriend) {
            $links[] = [
                'key' => 'activity',
                'label' => 'Activity',
                'url' => $isOwner ? route('activity.index') : route('profiles.activity', $user),
            ];
        }

        $links[] = ['key' => 'literature', 'label' => 'Literature', 'url' => route('profiles.literature', $user)];

        if ($isOwner || $isFriend) {
            $links[] = [
                'key' => 'diary',
                'label' => 'Diary',
                'url' => $isOwner ? route('diary.index') : route('profiles.diary', $user),
            ];
        }

        $links[] = ['key' => 'reviews', 'label' => 'Reviews', 'url' => route('profiles.reviews', $user)];
        $links[] = ['key' => 'readlist', 'label' => 'Readlist', 'url' => route('profiles.readlist', $user)];
        $links[] = ['key' => 'lists', 'label' => 'Lists', 'url' => route('profiles.lists', $user)];
        $links[] = ['key' => 'connections', 'label' => 'Connections', 'url' => route('profiles.connections', $user)];

        return [
            'current' => $current,
            'user' => $this->user($user),
            'links' => $links,
        ];
    }

    /** @return array<string, mixed> */
    public function user(User $user): array
    {
        return [
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatarUrl(),
            'initials' => $this->initials($user->name),
            'url' => route('profiles.show', $user),
        ];
    }

    /** @return array<string, mixed> */
    public function literature(Literature $literature): array
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
}
