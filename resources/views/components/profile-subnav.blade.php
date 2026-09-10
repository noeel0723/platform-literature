@props(['user', 'current' => 'profile'])

@php
    $isOwner = auth()->id() === $user->id;
    $initials = Str::of($user->name)
        ->squish()
        ->explode(' ')
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
        ->implode('');
    $links = [
        ['key' => 'profile', 'label' => 'Profile', 'url' => route('profiles.show', $user)],
    ];

    if ($isOwner) {
        $links[] = ['key' => 'activity', 'label' => 'Activity', 'url' => route('activity.index')];
    }

    $links[] = ['key' => 'literature', 'label' => 'Literature', 'url' => route('profiles.literature', $user)];

    if ($isOwner) {
        $links[] = ['key' => 'diary', 'label' => 'Diary', 'url' => route('diary.index')];
    }

    $links[] = ['key' => 'reviews', 'label' => 'Reviews', 'url' => route('profiles.reviews', $user)];
    $links[] = ['key' => 'readlist', 'label' => 'Readlist', 'url' => route('profiles.readlist', $user)];

    $navigation = [
        'current' => $current,
        'user' => [
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatarUrl(),
            'initials' => $initials ?: 'LH',
            'url' => route('profiles.show', $user),
        ],
        'links' => $links,
    ];
@endphp

<script type="application/json" data-react-profile-subnav-props>{{ Illuminate\Support\Js::encode(['navigation' => $navigation]) }}</script>
<div {{ $attributes }} data-react-profile-subnav></div>
