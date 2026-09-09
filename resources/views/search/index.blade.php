<x-app-shell :title="$query === '' ? 'Search' : 'Search results for '.$query">
    <section class="mx-auto grid max-w-7xl gap-10 px-5 py-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_280px] lg:px-10 lg:py-14">
        <main class="min-w-0" aria-labelledby="search-results-heading">
            <div class="border-b border-ink-950/20 pb-3">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Literahaven search</p>
                <h1 id="search-results-heading" class="mt-1 font-serif text-3xl font-bold text-ink-950">{{ $query === '' ? 'Search across Literahaven' : 'Showing matches for “'.$query.'”' }}</h1>
            </div>

            @if ($query === '')
                <div class="border-b border-ink-950/10 py-14 text-center">
                    <p class="font-serif text-2xl font-bold text-ink-950">What are you looking for?</p>
                    <p class="mt-2 text-ink-950/55">Search for a title, author name, display name, or username.</p>
                </div>
            @elseif ($literatures->isEmpty() && $authors->isEmpty() && $members->isEmpty())
                <div class="border-b border-ink-950/10 py-14 text-center">
                    <p class="font-serif text-2xl font-bold text-ink-950">No results found.</p>
                    <p class="mt-2 text-ink-950/55">Check the spelling or try a shorter keyword.</p>
                </div>
            @else
                @if ($literatures->isNotEmpty())
                    <section id="literature-results" class="scroll-mt-24" aria-labelledby="literature-results-heading">
                        <h2 id="literature-results-heading" class="border-b border-ink-950/10 py-4 text-xs font-bold uppercase tracking-[0.18em] text-ink-950/50">Literature</h2>
                        @foreach ($literatures as $literature)
                            @php($displayTitle = $literature->displayTitle())
                            @php($displaySynopsis = in_array(Str::lower((string) $literature->language), ['', 'en', 'eng', 'english'], true) ? $literature->synopsis : null)
                            <article class="grid grid-cols-[72px_minmax(0,1fr)] gap-4 border-b border-ink-950/10 py-5 sm:grid-cols-[86px_minmax(0,1fr)] sm:gap-6" data-search-literature>
                                <a href="{{ route('literatures.show', $literature) }}" class="aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25">
                                    @if ($literature->cover_url)
                                        <img src="{{ $literature->cover_url }}" alt="Cover of {{ $displayTitle }}" class="size-full object-cover" loading="lazy">
                                    @else
                                        <span class="grid size-full place-items-center font-serif text-xl font-bold text-ink-950">{{ Str::upper(Str::substr($displayTitle, 0, 2)) }}</span>
                                    @endif
                                </a>
                                <div class="min-w-0 self-center">
                                    <h2 class="font-serif text-2xl font-bold text-ink-950 sm:text-3xl"><a href="{{ route('literatures.show', $literature) }}" class="hover:text-brand-coral">{{ $displayTitle }}</a> @if ($literature->publication_year)<span class="font-sans text-base font-normal text-ink-950/45">{{ $literature->publication_year }}</span>@endif</h2>
                                    <p class="mt-2 text-sm text-ink-950/55">By <span class="font-semibold text-ink-950/70">{{ $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable' }}</span></p>
                                    <p class="mt-2 line-clamp-2 text-sm leading-6 text-ink-950/55">{{ $displaySynopsis ?: 'English synopsis unavailable.' }}</p>
                                    <span class="mt-3 inline-block text-xs font-bold uppercase tracking-wider text-brand-coral">{{ $literature->typeLabel() }}</span>
                                </div>
                            </article>
                        @endforeach
                    </section>
                @endif

                @if ($authors->isNotEmpty())
                    <section id="author-results" class="scroll-mt-24 pt-8" aria-labelledby="author-results-heading">
                        <h2 id="author-results-heading" class="border-b border-ink-950/10 pb-4 text-xs font-bold uppercase tracking-[0.18em] text-ink-950/50">Authors</h2>
                        <div class="grid gap-px bg-ink-950/10 sm:grid-cols-2">
                            @foreach ($authors as $author)
                                <article class="flex min-w-0 gap-4 bg-brand-cream p-5" data-search-author>
                                    <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-lg font-bold text-brand-cream">
                                        @if ($author->image_url)
                                            <img src="{{ $author->image_url }}" alt="" class="size-full object-cover" loading="lazy">
                                        @else
                                            {{ Str::upper(Str::substr($author->name, 0, 1)) }}
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="truncate font-serif text-xl font-bold text-ink-950"><a href="{{ route('authors.show', $author) }}" class="hover:text-brand-coral">{{ $author->name }}</a></h3>
                                        <p class="mt-1 text-xs text-ink-950/45">{{ $author->literatures_count }} catalog {{ Str::plural('title', $author->literatures_count) }}</p>
                                        <p class="mt-2 line-clamp-2 text-sm leading-6 text-ink-950/55">{{ $author->literatures->map(fn ($literature) => $literature->displayTitle())->implode(', ') ?: 'No linked literature yet.' }}</p>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($members->isNotEmpty())
                    <section id="member-results" class="scroll-mt-24 pt-8" aria-labelledby="member-results-heading">
                        <h2 id="member-results-heading" class="border-b border-ink-950/10 pb-4 text-xs font-bold uppercase tracking-[0.18em] text-ink-950/50">Readers</h2>
                        <div class="grid gap-px bg-ink-950/10 sm:grid-cols-2">
                            @foreach ($members as $member)
                                <a href="{{ route('profiles.show', $member) }}" class="flex min-w-0 items-center gap-4 bg-brand-cream p-5 transition hover:bg-brand-yogurt/45" data-search-member>
                                    <span class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-lg font-bold text-brand-cream">
                                        @if ($member->avatarUrl())
                                            <img src="{{ $member->avatarUrl() }}" alt="" class="size-full object-cover" loading="lazy">
                                        @else
                                            {{ Str::upper(Str::substr($member->name, 0, 1)) }}
                                        @endif
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-serif text-xl font-bold text-ink-950">{{ $member->name }}</span>
                                        <span class="mt-0.5 block truncate text-sm text-ink-950/50">&#64;{{ $member->username }}</span>
                                        <span class="mt-2 block text-xs text-ink-950/45">{{ $member->completed_literature_count }} completed · {{ $member->reviews_count }} reviews</span>
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endif
        </main>

        <aside class="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-7">
            <div class="sticky top-24">
                <h2 class="border-b border-ink-950/20 pb-3 text-sm font-bold uppercase tracking-[0.16em] text-ink-950">Search results for</h2>
                <p class="mt-4 break-words font-serif text-2xl font-bold text-ink-950">{{ $query === '' ? 'Everything' : '“'.$query.'”' }}</p>
                <nav class="mt-5 grid border border-ink-950/10 bg-brand-cream/80 text-sm" aria-label="Search result categories">
                    <a href="#search-results-heading" class="flex justify-between bg-ink-950 px-4 py-3 font-bold text-brand-cream"><span>All</span><span>{{ $literatures->count() + $authors->count() + $members->count() }}</span></a>
                    <a href="#literature-results" class="flex justify-between border-b border-ink-950/10 px-4 py-3 text-ink-950/65 hover:bg-brand-yogurt/45"><span>Literature</span><span>{{ $literatures->count() }}</span></a>
                    <a href="#author-results" class="flex justify-between border-b border-ink-950/10 px-4 py-3 text-ink-950/65 hover:bg-brand-yogurt/45"><span>Authors</span><span>{{ $authors->count() }}</span></a>
                    <a href="#member-results" class="flex justify-between px-4 py-3 text-ink-950/65 hover:bg-brand-yogurt/45"><span>Readers</span><span>{{ $members->count() }}</span></a>
                </nav>
            </div>
        </aside>
    </section>
</x-app-shell>
