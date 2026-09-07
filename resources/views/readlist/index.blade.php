<x-app-shell :title="$user->name.' Readlist'">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader collection</p>
            <div class="mt-3 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-serif text-5xl font-bold text-ink-950">{{ $user->name }}'s Readlist</h1>
                    <p class="mt-3 max-w-2xl leading-7 text-ink-950/65">Literature saved to read later. Reading progress and completed titles stay in the Diary activity history.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('profiles.show', $user) }}" class="border border-ink-950/20 bg-brand-cream/70 px-5 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral">Back to profile</a>
                    @if (auth()->id() === $user->id)
                        <a href="{{ route('literatures.index') }}" class="bg-ink-950 px-5 py-3 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Find literature</a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
        <div class="flex items-end justify-between gap-4 border-b border-ink-950/15 pb-3">
            <h2 class="font-serif text-3xl font-bold text-ink-950">Saved for later</h2>
            <span class="text-sm text-ink-950/50">{{ $readlist->total() }} {{ Str::plural('title', $readlist->total()) }}</span>
        </div>

        @if ($readlist->isEmpty())
            <div class="mt-6 border border-dashed border-ink-950/20 bg-white/25 px-6 py-14 text-center">
                <p class="font-serif text-2xl font-bold text-ink-950">This Readlist is empty.</p>
                @if (auth()->id() === $user->id)
                    <p class="mt-2 text-ink-950/60">Open the catalog and use the Readlist action on a literature page.</p>
                @else
                    <p class="mt-2 text-ink-950/60">This reader has not saved any literature for later.</p>
                @endif
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                @foreach ($readlist as $item)
                    @php($literature = $item->literature)
                    <article class="group min-w-0" data-readlist-item>
                        <a href="{{ route('literatures.show', $literature) }}" class="block">
                            <div class="relative aspect-[2/3] overflow-hidden border border-ink-950/15 bg-brand-sky/20 shadow-[0_8px_24px_rgba(47,58,85,0.08)] transition duration-200 group-hover:-translate-y-1 group-hover:border-brand-coral">
                                @if ($literature->cover_url)
                                    <img src="{{ $literature->cover_url }}" alt="Cover of {{ $literature->original_title ?? $literature->title }}" class="size-full object-cover" loading="lazy">
                                @else
                                    <div class="grid size-full place-items-center p-4 text-center font-serif text-3xl font-bold text-ink-950">{{ Str::upper(Str::substr($literature->original_title ?? $literature->title, 0, 2)) }}</div>
                                @endif
                                <span class="absolute left-2 top-2 bg-ink-950/90 px-2 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-brand-cream">Readlist</span>
                            </div>
                            <h3 class="mt-3 truncate font-bold text-ink-950 transition group-hover:text-brand-coral">{{ $literature->original_title ?? $literature->title }}</h3>
                            <p class="mt-1 truncate text-sm text-ink-950/55">{{ $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable' }}</p>
                        </a>
                        <time datetime="{{ $item->updated_at->utc()->toIso8601String() }}" data-local-datetime class="mt-2 block text-xs text-ink-950/45">Saved {{ $item->updated_at->utc()->format('M j, Y') }} (UTC)</time>
                    </article>
                @endforeach
            </div>

            @if ($readlist->hasPages())
                <div class="mt-10">{{ $readlist->links() }}</div>
            @endif
        @endif
    </section>
</x-app-shell>
