<dialog id="quick-log-search-dialog" aria-labelledby="quick-log-search-title" data-review-dialog data-quick-log-search-dialog data-search-url="{{ route('quick-log.literatures') }}" class="review-dialog m-auto max-h-[min(42rem,90vh)] w-[min(680px,calc(100%_-_2rem))] overflow-hidden rounded-xl border border-ink-950/15 bg-brand-cream p-0 text-ink-950 shadow-[0_24px_70px_rgba(16,47,98,0.3)]">
    <div class="flex items-center justify-between border-b border-brand-plate/15 bg-brand-blueberry px-5 py-4 text-brand-cream sm:px-6">
        <div>
            <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-brand-sky">Quick log</p>
            <h2 id="quick-log-search-title" class="mt-0.5 font-serif text-xl font-bold">Choose a literature</h2>
        </div>
        <button type="button" data-dialog-close class="grid size-9 place-items-center rounded-full border border-brand-cream/25 text-xl leading-none transition hover:border-brand-sky hover:text-brand-sky" aria-label="Close literature search">&times;</button>
    </div>

    <div class="p-5 sm:p-6">
        <label for="quick-log-search" class="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/55">Search your catalog</label>
        <div class="mt-2 flex items-center gap-3 rounded-lg border border-ink-950/15 bg-white/75 px-4 focus-within:border-brand-berry focus-within:ring-3 focus-within:ring-brand-sky/20">
            <svg aria-hidden="true" class="size-4 shrink-0 text-ink-950/45" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
            <input id="quick-log-search" type="search" autocomplete="off" placeholder="Search by title or author..." class="h-12 min-w-0 flex-1 border-0 bg-transparent text-sm outline-none" data-quick-log-input>
        </div>

        <p class="mt-3 text-xs text-ink-950/45">Select a result to add or update your rating and review.</p>

        <div class="mt-4 max-h-[24rem] overflow-y-auto rounded-lg border border-ink-950/10" data-quick-log-results aria-live="polite">
            <p class="px-4 py-8 text-center text-sm text-ink-950/50" data-quick-log-state>Loading recent literature...</p>
        </div>
    </div>
</dialog>

<dialog id="quick-log-review-dialog" aria-labelledby="quick-log-review-title" data-review-dialog data-quick-log-review-dialog class="review-dialog m-auto max-h-[min(46rem,92vh)] w-[min(840px,calc(100%_-_2rem))] overflow-y-auto rounded-xl border border-ink-950/15 bg-brand-cream p-0 text-ink-950 shadow-[0_24px_70px_rgba(16,47,98,0.3)]">
    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-brand-plate/15 bg-brand-blueberry px-5 py-4 text-brand-cream sm:px-6">
        <button type="button" data-quick-log-back class="inline-flex items-center gap-2 rounded-full border border-brand-cream/25 px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition hover:border-brand-sky hover:text-brand-sky">
            <span aria-hidden="true">&larr;</span> Back
        </button>
        <h2 id="quick-log-review-title" class="text-xs font-bold uppercase tracking-[0.16em] text-brand-sky">Rate &amp; review</h2>
        <button type="button" data-dialog-close class="grid size-9 place-items-center rounded-full border border-brand-cream/25 text-xl leading-none transition hover:border-brand-sky hover:text-brand-sky" aria-label="Close review form">&times;</button>
    </div>

    <form method="POST" class="grid gap-6 p-5 sm:p-7 md:grid-cols-[132px_minmax(0,1fr)]" data-quick-log-review-form>
        @csrf
        @method('PUT')

        <div class="mx-auto w-28 md:mx-0 md:w-full">
            <div class="aspect-[2/3] overflow-hidden rounded-md border border-ink-950/15 bg-brand-sky/20 shadow-[0_8px_22px_rgba(16,47,98,0.10)]">
                <img alt="" class="hidden size-full object-cover" data-quick-log-cover>
                <span class="grid size-full place-items-center font-serif text-3xl font-bold text-ink-950" data-quick-log-cover-fallback>LH</span>
            </div>
            <p class="mt-2 truncate text-center text-[0.65rem] font-bold uppercase tracking-[0.14em] text-ink-950/45" data-quick-log-type>Literature</p>
        </div>

        <div class="min-w-0">
            <h2 class="font-serif text-2xl font-bold leading-tight text-ink-950 sm:text-3xl" data-quick-log-title>Literature title</h2>
            <p class="mt-1 truncate text-sm text-ink-950/55" data-quick-log-meta>Author unavailable</p>

            <fieldset class="mt-5">
                <legend class="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/55">Your rating</legend>
                <input id="quick-log-rating" name="rating" type="hidden" required>
                <div class="mt-1.5 flex flex-wrap items-center gap-3">
                    <div data-star-rating data-rating-input="quick-log-rating" class="flex" role="radiogroup" aria-label="Choose a rating from 0.5 to 5 stars">
                        @foreach (range(1, 10) as $halfStep)
                            @php($ratingValue = $halfStep / 2)
                            <button type="button" data-rating-value="{{ $ratingValue }}" class="h-10 w-4.5 overflow-hidden text-left text-3xl leading-10 text-ink-950/15 transition focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral" role="radio" aria-checked="false" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                <span class="block w-9 {{ $halfStep % 2 === 0 ? '-translate-x-1/2' : '' }}">&#9733;</span>
                            </button>
                        @endforeach
                    </div>
                    <output data-rating-output for="quick-log-rating" class="text-xs font-bold text-ink-950/55">Choose a rating</output>
                </div>
                <p class="mt-1.5 hidden text-xs font-semibold text-red-700" data-quick-log-rating-error>Please choose a rating before saving.</p>
            </fieldset>

            <div class="mt-5">
                <label for="quick-log-body" class="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/55">Review <span class="font-normal normal-case tracking-normal text-ink-950/40">(optional)</span></label>
                <textarea id="quick-log-body" name="body" rows="5" maxlength="5000" placeholder="What stayed with you after reading?" class="mt-2 w-full resize-y rounded-lg border border-ink-950/15 bg-white/70 px-4 py-3 text-sm leading-6 outline-none focus:border-brand-berry focus:ring-3 focus:ring-brand-sky/20" data-quick-log-body></textarea>
            </div>

            <label class="mt-4 flex items-start gap-2.5 text-xs leading-5 text-ink-950/60">
                <input name="contains_spoiler" type="checkbox" value="1" class="mt-0.5 size-4 accent-brand-coral" data-quick-log-spoiler>
                Hide this review behind a spoiler warning.
            </label>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-ink-950/10 pt-5">
                <p class="max-w-sm text-xs leading-5 text-ink-950/45">Saving a rating also marks this literature as completed.</p>
                <button class="rounded-full bg-brand-stem px-6 py-2.5 text-sm font-bold text-brand-cream shadow-[0_7px_18px_rgba(1,75,170,0.18)] transition hover:bg-brand-coral" data-quick-log-submit>Publish review</button>
            </div>
        </div>
    </form>
</dialog>
