<x-app-shell :title="Str::headline($relationship).' - '.$user->name">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
            <a href="{{ route('profiles.show', $user) }}" class="text-sm font-bold text-ink-950/65 transition hover:text-brand-coral">&larr; Back to {{ $user->name }}'s profile</a>
            <p class="mt-8 text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader network</p>
            <h1 class="mt-3 font-serif text-5xl font-bold text-ink-950">{{ Str::headline($relationship) }}</h1>
            <p class="mt-3 leading-7 text-ink-950/60">
                @if ($relationship === 'followers')
                    Readers following {{ $user->name }}.
                @else
                    Readers followed by {{ $user->name }}.
                @endif
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($connections as $connection)
                @php
                    $initials = Str::of($connection->name)
                        ->squish()
                        ->explode(' ')
                        ->filter()
                        ->take(2)
                        ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
                        ->implode('');
                @endphp
                <a href="{{ route('profiles.show', $connection) }}" class="group flex items-center gap-4 border border-ink-950/10 bg-white/35 p-5 transition hover:border-brand-coral">
                    <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-xl font-bold text-brand-cream">
                        @if ($connection->avatarUrl())
                            <img src="{{ $connection->avatarUrl() }}" alt="" class="size-full object-cover">
                        @else
                            {{ $initials ?: 'LH' }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h2 class="truncate font-serif text-xl font-bold text-ink-950 transition group-hover:text-brand-coral">{{ $connection->name }}</h2>
                        <p class="truncate text-sm font-semibold text-ink-950/50">&#64;{{ $connection->username }}</p>
                        <p class="mt-2 text-xs uppercase tracking-wider text-ink-950/45">{{ $connection->followers_count }} followers &middot; {{ $connection->following_count }} following</p>
                    </div>
                </a>
            @empty
                <div class="border border-dashed border-ink-950/20 p-8 text-ink-950/55 sm:col-span-2 lg:col-span-3">No readers are listed here yet.</div>
            @endforelse
        </div>

        @if ($connections->hasPages())
            <div class="mt-10">{{ $connections->links() }}</div>
        @endif
    </section>
</x-app-shell>
