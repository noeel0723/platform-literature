@props(['user'])

@php
    $initials = collect(preg_split('/\s+/', trim($user->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<div class="account-menu relative" data-account-menu data-open="false">
    <button
        type="button"
        class="flex h-10 items-center gap-2 rounded-full px-2 text-left text-brand-plate transition hover:bg-brand-plate/12 focus-visible:bg-brand-plate/12"
        data-account-menu-button
        aria-expanded="false"
        aria-controls="account-menu-panel"
    >
        <span class="grid size-7 shrink-0 place-items-center overflow-hidden rounded-full border border-brand-plate/45 bg-brand-blueberry text-[0.65rem] font-bold text-brand-plate">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="size-full object-cover">
            @else
                {{ $initials ?: '?' }}
            @endif
        </span>
        <span class="max-w-32 truncate text-xs font-bold uppercase tracking-[0.12em]">{{ $user->username }}</span>
        <svg aria-hidden="true" class="size-3.5 shrink-0 transition-transform" data-account-menu-chevron viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"></path></svg>
    </button>

    <div
        id="account-menu-panel"
        class="account-menu-panel absolute right-0 top-full z-50 mt-2 w-56 overflow-hidden rounded-xl border border-brand-blueberry/12 bg-brand-plate p-2 text-sm text-ink-950 shadow-2xl shadow-brand-blueberry/20"
        data-account-menu-panel
    >
        <div class="border-b border-brand-blueberry/10 px-3 py-2.5">
            <p class="truncate font-bold text-ink-950">{{ $user->name }}</p>
            <p class="mt-0.5 truncate text-xs text-ink-950/55">&#64;{{ $user->username }}</p>
        </div>

        <nav class="py-1" aria-label="Account navigation">
            <a href="{{ route('profiles.show', $user) }}" class="account-menu-link">Profile</a>
            <a href="{{ route('activity.index') }}" class="account-menu-link">Activity</a>
            <a href="{{ route('profiles.literature', $user) }}" class="account-menu-link">Literature</a>
            <a href="{{ route('profiles.reviews', $user) }}" class="account-menu-link">Reviews</a>
            <a href="{{ route('profiles.readlist', $user) }}" class="account-menu-link">Readlist</a>
        </nav>

        <div class="border-t border-brand-blueberry/10 pt-1">
            <a href="{{ route('profiles.edit') }}" class="account-menu-link">Edit profile</a>
            @if ($user->isAdmin())
                <a href="{{ route('admin.moderation.index') }}" class="account-menu-link">Moderation</a>
            @endif
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button class="account-menu-link w-full text-left">Log out</button>
            </form>
        </div>
    </div>
</div>
