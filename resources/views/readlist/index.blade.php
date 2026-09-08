<x-app-shell :title="$user->name.' Readlist'">
    <x-profile-subnav :$user current="readlist" />

    <section class="mx-auto grid max-w-7xl gap-10 px-5 py-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-10 lg:py-14">
        <main class="min-w-0" aria-labelledby="readlist-heading">
            <div class="flex items-end justify-between gap-4 border-b border-ink-950/20 pb-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Saved shelf</p>
                    <h1 id="readlist-heading" class="mt-1 font-serif text-3xl font-bold text-ink-950">{{ $user->name }}'s Readlist</h1>
                </div>
                <span class="shrink-0 text-sm text-ink-950/50">{{ $readlist->total() }} {{ Str::plural('title', $readlist->total()) }}</span>
            </div>

        @if ($readlist->isEmpty())
            <div class="mt-6 border border-dashed border-ink-950/20 bg-white/25 px-6 py-14 text-center">
                <p class="font-serif text-2xl font-bold text-ink-950">This Readlist is empty.</p>
                <p class="mt-2 text-ink-950/60">{{ $isOwner ? 'Use the panel beside this shelf to save your next read.' : 'This reader has not saved any literature for later.' }}</p>
            </div>
        @else
            <div class="mt-6 grid grid-cols-2 gap-x-3 gap-y-7 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                @foreach ($readlist as $item)
                    @php($literature = $item->literature)
                    <article class="group min-w-0" data-readlist-item>
                        <a href="{{ route('literatures.show', $literature) }}" class="block">
                            <div class="relative aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/20 shadow-[0_7px_18px_rgba(47,58,85,0.08)] transition duration-200 group-hover:-translate-y-1 group-hover:border-brand-coral">
                                @if ($literature->cover_url)
                                    <img src="{{ $literature->cover_url }}" alt="Cover of {{ $literature->original_title ?? $literature->title }}" class="size-full object-cover" loading="lazy">
                                @else
                                    <div class="grid size-full place-items-center p-4 text-center font-serif text-2xl font-bold text-ink-950">{{ Str::upper(Str::substr($literature->original_title ?? $literature->title, 0, 2)) }}</div>
                                @endif
                            </div>
                            <h2 class="mt-2 truncate text-sm font-bold text-ink-950 transition group-hover:text-brand-coral">{{ $literature->original_title ?? $literature->title }}</h2>
                            <p class="mt-0.5 truncate text-xs text-ink-950/50">{{ $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable' }}</p>
                        </a>
                        <time datetime="{{ $item->updated_at->utc()->toIso8601String() }}" data-local-datetime data-time-prefix="Saved " class="mt-1.5 block text-[0.68rem] text-ink-950/40">Saved {{ $item->updated_at->utc()->format('M j, Y') }}</time>
                    </article>
                @endforeach
            </div>

            @if ($readlist->hasPages())
                <div class="mt-10">{{ $readlist->links() }}</div>
            @endif
        @endif
        </main>

        <aside class="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-7">
            @if ($isOwner)
                <section class="sticky top-24" aria-labelledby="add-to-readlist-heading" data-readlist-add-panel>
                    <div class="border border-ink-950/10 bg-brand-cream/80 p-5 shadow-[0_8px_28px_rgba(47,58,85,0.06)]">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Quick add</p>
                        <h2 id="add-to-readlist-heading" class="mt-2 font-serif text-2xl font-bold text-ink-950">Add literature</h2>
                        <p class="mt-2 text-sm leading-6 text-ink-950/55">Search the latest catalog entries and save one without leaving this shelf.</p>

                        <label for="readlist-suggestion-search" class="sr-only">Filter literature suggestions</label>
                        <div class="mt-5 flex border border-ink-950/15 bg-white/65 focus-within:border-brand-coral">
                            <input id="readlist-suggestion-search" type="search" placeholder="Search literature..." autocomplete="off" class="min-w-0 flex-1 bg-transparent px-3 py-2.5 text-sm outline-none placeholder:text-ink-950/35" data-readlist-suggestion-search>
                            <span class="grid w-10 place-items-center text-ink-950/50" aria-hidden="true">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                            </span>
                        </div>

                        <div class="mt-4 max-h-[28rem] space-y-1 overflow-y-auto pr-1" data-readlist-suggestions>
                            @forelse ($readlistSuggestions as $literature)
                                @php($suggestionTitle = $literature->original_title ?? $literature->title)
                                <article class="flex items-center gap-3 border-b border-ink-950/10 py-2.5 last:border-b-0" data-readlist-suggestion data-suggestion-literature-id="{{ $literature->id }}" data-search-text="{{ Str::lower($suggestionTitle.' '.$literature->authors->pluck('name')->implode(' ')) }}">
                                    <a href="{{ route('literatures.show', $literature) }}" class="h-16 w-11 shrink-0 overflow-hidden rounded-sm border border-ink-950/10 bg-brand-sky/25">
                                        @if ($literature->cover_url)
                                            <img src="{{ $literature->cover_url }}" alt="" class="size-full object-cover" loading="lazy">
                                        @else
                                            <span class="grid size-full place-items-center font-serif text-xs font-bold text-ink-950">{{ Str::upper(Str::substr($suggestionTitle, 0, 2)) }}</span>
                                        @endif
                                    </a>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('literatures.show', $literature) }}" class="block truncate text-sm font-bold text-ink-950 hover:text-brand-coral">{{ $suggestionTitle }}</a>
                                        <p class="mt-0.5 truncate text-xs text-ink-950/45">{{ $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable' }}</p>
                                    </div>
                                    <form action="{{ route('reading-list.update', $literature) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="status" value="want_to_read">
                                        <input type="hidden" name="return_to" value="readlist">
                                        <button type="submit" class="grid size-9 shrink-0 place-items-center rounded-full border border-ink-950/15 text-xl text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-brand-cream" aria-label="Add {{ $suggestionTitle }} to Readlist">+</button>
                                    </form>
                                </article>
                            @empty
                                <p class="py-5 text-sm leading-6 text-ink-950/50">Every current catalog title already has a reading status.</p>
                            @endforelse
                        </div>
                        <p class="hidden py-5 text-sm text-ink-950/50" data-readlist-no-results>No matching literature in these suggestions.</p>

                        <a href="{{ route('literatures.index') }}" class="mt-5 block border border-ink-950/15 px-4 py-2.5 text-center text-sm font-bold text-ink-950 transition hover:border-brand-coral hover:bg-brand-yogurt/45">Browse the full catalog</a>
                    </div>

                    <div class="mt-6 border-t border-ink-950/15 pt-5">
                        <h3 class="text-sm font-bold uppercase tracking-[0.14em] text-ink-950">How to add</h3>
                        <p class="mt-2 text-sm leading-6 text-ink-950/55">Use Quick add here, or choose <strong class="text-ink-950">Readlist</strong> from the action panel on any literature detail page.</p>
                    </div>
                </section>
            @else
                <section class="border-t border-ink-950/15 pt-5" aria-labelledby="public-readlist-help">
                    <h2 id="public-readlist-help" class="text-sm font-bold uppercase tracking-[0.14em] text-ink-950">About Readlists</h2>
                    <p class="mt-3 text-sm leading-6 text-ink-950/55">A Readlist is a personal shelf for literature a reader wants to explore later.</p>
                    @guest
                        <a href="{{ route('login') }}" class="mt-5 inline-block bg-ink-950 px-4 py-2.5 text-sm font-bold text-brand-cream">Log in to build yours</a>
                    @endguest
                </section>
            @endif
        </aside>
    </section>
</x-app-shell>
