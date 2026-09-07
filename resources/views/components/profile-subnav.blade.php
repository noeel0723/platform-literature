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
    $itemClass = 'relative shrink-0 px-1 py-4 text-sm font-bold transition after:absolute after:inset-x-0 after:bottom-0 after:h-0.5';
    $inactiveClass = 'text-ink-950/55 hover:text-ink-950 after:bg-transparent';
    $activeClass = 'text-ink-950 after:bg-brand-coral';
@endphp

<nav {{ $attributes->class(['flex min-h-16 flex-col border border-ink-950/10 bg-brand-cream/80 sm:flex-row sm:items-center']) }} aria-label="Profile navigation" data-profile-subnav>
    <a href="{{ route('profiles.show', $user) }}" class="flex shrink-0 items-center gap-3 border-b border-ink-950/10 px-4 py-3 text-ink-950 sm:w-52 sm:border-b-0 sm:border-r">
        <span class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-sm font-bold text-brand-cream">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="size-full object-cover">
            @else
                {{ $initials ?: 'LH' }}
            @endif
        </span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-bold">{{ $user->name }}</span>
            <span class="block truncate text-xs font-medium text-ink-950/45">&#64;{{ $user->username }}</span>
        </span>
    </a>

    <div class="flex min-w-0 flex-1 gap-6 overflow-x-auto px-5 sm:justify-center" data-profile-subnav-links>
        <a href="{{ route('profiles.show', $user) }}" class="{{ $itemClass }} {{ $current === 'profile' ? $activeClass : $inactiveClass }}" @if ($current === 'profile') aria-current="page" @endif>Profile</a>
        @if ($isOwner)
            <a href="{{ route('diary.index') }}" class="{{ $itemClass }} {{ $current === 'diary' ? $activeClass : $inactiveClass }}" @if ($current === 'diary') aria-current="page" @endif>Diary</a>
        @endif
        <a href="{{ route('profiles.reviews', $user) }}" class="{{ $itemClass }} {{ $current === 'reviews' ? $activeClass : $inactiveClass }}" @if ($current === 'reviews') aria-current="page" @endif>Reviews</a>
        <a href="{{ route('profiles.readlist', $user) }}" class="{{ $itemClass }} {{ $current === 'readlist' ? $activeClass : $inactiveClass }}" @if ($current === 'readlist') aria-current="page" @endif>Readlist</a>
        <a href="{{ route('profiles.show', $user) }}#favorites" class="{{ $itemClass }} {{ $inactiveClass }}">Favorites</a>
        <a href="{{ route('profiles.show', $user) }}#recently-completed" class="{{ $itemClass }} {{ $inactiveClass }}">Completed</a>
    </div>
</nav>
