<x-app-shell :title="$user->name.' Literature'">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <x-profile-subnav :$user current="literature" />
        </div>
    </section>

    <main class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14" aria-labelledby="completed-literature-heading">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-ink-950/20 pb-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Completed shelf</p>
                <h1 id="completed-literature-heading" class="mt-1 font-serif text-3xl font-bold text-ink-950">{{ $user->name }}'s Literature</h1>
            </div>
            <span class="text-sm text-ink-950/50">{{ $completedLiterature->total() }} completed</span>
        </div>

        @if ($completedLiterature->isEmpty())
            <div class="mt-6 border border-dashed border-ink-950/20 bg-white/25 px-6 py-14 text-center">
                <p class="font-serif text-2xl font-bold text-ink-950">No completed literature yet.</p>
                <p class="mt-2 text-ink-950/55">Titles marked Completed will appear on this shelf.</p>
            </div>
        @else
            <div class="mt-6 grid grid-cols-3 gap-x-3 gap-y-7 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8" data-profile-literature-grid>
                @foreach ($completedLiterature as $item)
                    @php
                        $literature = $item->literature;
                        $title = $literature->original_title ?? $literature->title;
                        $rating = $literature->reviews->first()?->rating;
                    @endphp
                    <article class="group min-w-0" data-profile-literature-item>
                        <a href="{{ route('literatures.show', $literature) }}" class="block">
                            <div class="relative aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/20 shadow-[0_5px_14px_rgba(47,58,85,0.07)] transition duration-200 group-hover:-translate-y-1 group-hover:border-brand-coral">
                                @if ($literature->cover_url)
                                    <img src="{{ $literature->cover_url }}" alt="Cover of {{ $title }}" class="size-full object-cover" loading="lazy">
                                @else
                                    <span class="grid size-full place-items-center p-3 text-center font-serif text-xl font-bold text-ink-950">{{ Str::upper(Str::substr($title, 0, 2)) }}</span>
                                @endif
                            </div>
                            <h2 class="mt-2 truncate text-xs font-bold text-ink-950 transition group-hover:text-brand-coral">{{ $title }}</h2>
                        </a>
                        <div class="mt-1 flex min-h-4 items-center justify-between gap-1 text-[0.65rem] text-ink-950/45">
                            @if ($rating)
                                <x-star-rating :$rating size="sm" />
                            @else
                                <span>{{ $literature->publication_year ?: '—' }}</span>
                            @endif
                            @if ($rating && $literature->publication_year)
                                <span>{{ $literature->publication_year }}</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($completedLiterature->hasPages())
                <div class="mt-10">{{ $completedLiterature->links() }}</div>
            @endif
        @endif
    </main>
</x-app-shell>
