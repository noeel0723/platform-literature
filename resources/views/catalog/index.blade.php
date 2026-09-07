<x-app-shell title="Catalog">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-5 py-6 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
            <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
                <span class="shrink-0 text-xs font-bold uppercase tracking-[0.2em] text-ink-950/45">Browse by format</span>
                <nav class="flex gap-2 overflow-x-auto pb-1" aria-label="Literature format filters">
                    <a href="{{ route('literatures.index', ['q' => $query]) }}" class="whitespace-nowrap border px-3 py-2 text-xs font-semibold uppercase tracking-wider transition {{ $selectedType === '' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/20 text-ink-950/70 hover:border-ink-950 hover:bg-brand-sky/25' }}">All</a>
                    @foreach ($types as $value => $label)
                        <a href="{{ route('literatures.index', ['q' => $query, 'type' => $value]) }}" class="whitespace-nowrap border px-3 py-2 text-xs font-semibold uppercase tracking-wider transition {{ $selectedType === $value ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/20 text-ink-950/70 hover:border-ink-950 hover:bg-brand-sky/25' }}">{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            <form action="{{ route('literatures.index') }}" method="GET" role="search" class="flex h-10 w-full overflow-hidden rounded-full border border-ink-950/20 bg-white/50 sm:w-72 lg:w-80">
                @if ($selectedType !== '')
                    <input type="hidden" name="type" value="{{ $selectedType }}">
                @endif
                <label for="catalog-page-search" class="sr-only">Find literature</label>
                <input id="catalog-page-search" name="q" value="{{ $query }}" placeholder="Find literature..." class="min-w-0 flex-1 bg-transparent px-4 text-sm text-ink-950 outline-none placeholder:text-ink-950/40 focus:bg-white/50">
                <button type="submit" class="grid size-10 shrink-0 place-items-center bg-ink-950 text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream" aria-label="Search catalog">
                    <svg aria-hidden="true" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                </button>
            </form>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16" aria-labelledby="catalog-results-title">
        <div class="mb-7 flex flex-col gap-3 border-b border-ink-950/15 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Latest matches</p>
                <h2 id="catalog-results-title" class="mt-1 font-serif text-3xl font-bold text-ink-950">
                    @if ($query !== '')
                        Results for “{{ $query }}”
                    @elseif ($selectedType !== '')
                        Latest {{ $types[$selectedType] ?? 'literature' }}
                    @else
                        Search the catalog
                    @endif
                </h2>
            </div>
            @if ($query !== '' || $selectedType !== '')
                <p class="text-sm text-ink-950/50">Showing {{ $literatures->count() }} of the newest matches</p>
            @endif
        </div>

        @if ($sourceWarning !== null)
            <div role="status" class="mb-8 border border-brand-coral/35 bg-white/35 px-4 py-3 text-sm leading-6 text-ink-950/70">
                <span class="font-bold text-ink-950">The local catalog remains available.</span>
                {{ $sourceWarning }}
            </div>
        @endif

        @if ($query === '' && $selectedType === '')
            <div class="border border-dashed border-ink-950/20 bg-white/25 px-6 py-14 text-center">
                <p class="font-serif text-2xl font-bold text-ink-950">What would you like to read next?</p>
                <p class="mx-auto mt-2 max-w-xl leading-7 text-ink-950/60">Enter a title, author, or genre above. Only the four newest matching works will be displayed.</p>
            </div>
        @elseif ($literatures->isEmpty())
            <div class="border border-ink-950/10 bg-white/35 px-6 py-14 text-center">
                <p class="font-serif text-2xl font-bold text-ink-950">No matching titles found.</p>
                <p class="mt-2 text-ink-950/60">Try another keyword or choose a different format.</p>
                <a href="{{ route('literatures.index') }}" class="mt-6 inline-block bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Clear search</a>
            </div>
        @else
            <div class="grid grid-cols-2 gap-x-4 gap-y-9 sm:gap-x-6 lg:grid-cols-4 lg:gap-x-7" data-catalog-results>
                @foreach ($literatures as $literature)
                    <x-literature-card :$literature />
                @endforeach
            </div>
        @endif
    </section>
</x-app-shell>
