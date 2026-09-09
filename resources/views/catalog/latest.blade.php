<x-app-shell :title="$query !== '' ? 'All matches for '.$query : 'Latest literature'">
    <section class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14" aria-labelledby="latest-literature-title">
        <div class="mb-6 flex flex-col gap-3 border-b border-ink-950/15 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">All latest matches</p>
                <h1 id="latest-literature-title" class="mt-1 text-2xl font-bold text-ink-950 sm:text-3xl">
                    @if ($query !== '')
                        Results for “{{ $query }}”
                    @elseif ($selectedType !== '')
                        Latest {{ $types[$selectedType] ?? 'literature' }}
                    @else
                        Latest literature
                    @endif
                </h1>
            </div>
            <p class="text-sm text-ink-950/50">{{ $literatures->total() }} matches · 15 per page</p>
        </div>

        @if ($literatures->isEmpty())
            <div class="border border-ink-950/10 bg-white/35 px-6 py-14 text-center">
                <p class="text-xl font-bold text-ink-950">No matching literature is available.</p>
                <a href="{{ route('literatures.index', array_filter(['q' => $query, 'type' => $selectedType])) }}" class="mt-5 inline-flex min-h-10 items-center bg-ink-950 px-5 text-sm font-bold text-brand-cream transition hover:bg-brand-coral">Back to catalog</a>
            </div>
        @else
            <div class="grid grid-cols-2 gap-x-3 gap-y-7 sm:grid-cols-3 sm:gap-x-4 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6" data-latest-literature-grid>
                @foreach ($literatures as $literature)
                    <x-literature-card :$literature compact />
                @endforeach
            </div>

            <nav class="mt-9 flex items-center justify-between border-t border-ink-950/15 pt-5" aria-label="Latest literature pagination">
                @if ($literatures->onFirstPage())
                    <span class="inline-flex min-h-10 items-center border border-ink-950/10 px-5 text-sm font-semibold text-ink-950/30" aria-disabled="true">Previous</span>
                @else
                    <a href="{{ $literatures->previousPageUrl() }}" rel="prev" class="inline-flex min-h-10 items-center border border-ink-950/20 bg-white/45 px-5 text-sm font-semibold text-ink-950 transition hover:border-brand-blue hover:bg-brand-blue hover:text-brand-cream">Previous</a>
                @endif

                <span class="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/45">Page {{ $literatures->currentPage() }} of {{ $literatures->lastPage() }}</span>

                @if ($literatures->hasMorePages())
                    <a href="{{ $literatures->nextPageUrl() }}" rel="next" class="inline-flex min-h-10 items-center border border-ink-950/20 bg-white/45 px-5 text-sm font-semibold text-ink-950 transition hover:border-brand-blue hover:bg-brand-blue hover:text-brand-cream">Next</a>
                @else
                    <span class="inline-flex min-h-10 items-center border border-ink-950/10 px-5 text-sm font-semibold text-ink-950/30" aria-disabled="true">Next</span>
                @endif
            </nav>
        @endif
    </section>
</x-app-shell>
