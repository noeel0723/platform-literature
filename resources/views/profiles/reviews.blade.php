<x-app-shell :title="$user->name.' Reviews'">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <x-profile-subnav :$user current="reviews" />
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-10 px-5 py-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_280px] lg:px-10 lg:py-14">
        <main class="min-w-0" aria-labelledby="profile-reviews-heading">
            <div class="flex items-end justify-between gap-4 border-b border-ink-950/20 pb-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Reader notes</p>
                    <h1 id="profile-reviews-heading" class="mt-1 font-serif text-3xl font-bold text-ink-950">{{ $user->name }}'s Reviews</h1>
                </div>
                <span class="text-sm text-ink-950/50">{{ $totalReviews }} {{ Str::plural('review', $totalReviews) }}</span>
            </div>

            <div data-profile-review-list>
                @forelse ($reviews as $review)
                    @php
                        $literature = $review->literature;
                        $reviewTitle = $literature->original_title ?? $literature->title;
                        $spoilerId = 'review-spoiler-'.$review->id;
                    @endphp
                    <article class="grid grid-cols-[72px_minmax(0,1fr)] gap-4 border-b border-ink-950/12 py-6 sm:grid-cols-[90px_minmax(0,1fr)] sm:gap-6" data-profile-review>
                        <a href="{{ route('literatures.show', $literature) }}" class="block aspect-[2/3] self-start overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25 shadow-[0_6px_16px_rgba(47,58,85,0.07)]">
                            @if ($literature->cover_url)
                                <img src="{{ $literature->cover_url }}" alt="Cover of {{ $reviewTitle }}" class="size-full object-cover" loading="lazy">
                            @else
                                <span class="grid size-full place-items-center font-serif text-xl font-bold text-ink-950">{{ Str::upper(Str::substr($reviewTitle, 0, 2)) }}</span>
                            @endif
                        </a>

                        <div class="min-w-0">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                                <h2 class="min-w-0 font-serif text-2xl font-bold text-ink-950 sm:text-3xl">
                                    <a href="{{ route('literatures.show', $literature) }}" class="hover:text-brand-coral">{{ $reviewTitle }}</a>
                                    @if ($literature->publication_year)
                                        <span class="font-sans text-base font-normal text-ink-950/45">{{ $literature->publication_year }}</span>
                                    @endif
                                </h2>
                                @if (auth()->id() === $user->id)
                                    <a href="{{ route('literatures.show', $literature) }}?review=edit" class="shrink-0 text-xs font-bold uppercase tracking-wider text-brand-coral hover:underline">Edit review</a>
                                @endif
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-950/50">
                                <x-star-rating :rating="$review->rating" size="sm" />
                                <span class="font-bold text-ink-950/65">{{ number_format($review->rating, 1) }}</span>
                                <time datetime="{{ $review->updated_at->utc()->toIso8601String() }}" data-local-datetime>{{ $review->updated_at->utc()->format('M j, Y') }} (UTC)</time>
                            </div>

                            @if ($review->body)
                                @if ($review->contains_spoiler)
                                    <button type="button" data-spoiler-reveal aria-controls="{{ $spoilerId }}" class="mt-4 border border-ink-950/15 px-3 py-2 text-xs font-bold uppercase tracking-wider text-ink-950 transition hover:border-brand-coral">Reveal spoiler review</button>
                                    <p id="{{ $spoilerId }}" hidden class="mt-4 whitespace-pre-line font-serif text-lg leading-7 text-ink-950/75">{{ $review->body }}</p>
                                @else
                                    <p class="mt-4 whitespace-pre-line font-serif text-lg leading-7 text-ink-950/75">{{ $review->body }}</p>
                                @endif
                            @else
                                <p class="mt-4 text-sm italic text-ink-950/45">Rating only.</p>
                            @endif

                            <p class="mt-4 text-sm text-ink-950/45">{{ $review->likes_count }} {{ Str::plural('like', $review->likes_count) }}</p>
                        </div>
                    </article>
                @empty
                    <div class="border-b border-ink-950/12 py-14 text-center">
                        <p class="font-serif text-2xl font-bold text-ink-950">No reviews yet.</p>
                        <p class="mt-2 text-sm text-ink-950/55">Written reviews and rating-only entries will appear here.</p>
                    </div>
                @endforelse
            </div>

            @if ($reviews->hasPages())
                <div class="mt-8">{{ $reviews->links() }}</div>
            @endif
        </main>

        <aside class="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-7">
            <div class="sticky top-24 space-y-6">
                <section class="border border-ink-950/10 bg-brand-cream/80 p-5" aria-labelledby="review-summary-heading" data-review-summary>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Review summary</p>
                    <h2 id="review-summary-heading" class="mt-2 font-serif text-2xl font-bold text-ink-950">Reader average</h2>
                    <div class="mt-5 flex items-end gap-3">
                        <strong class="font-serif text-5xl leading-none text-ink-950">{{ $totalReviews > 0 ? number_format($averageRating, 1) : '—' }}</strong>
                        <span class="pb-1 text-sm text-ink-950/45">out of 5</span>
                    </div>
                    @if ($totalReviews > 0)
                        <x-star-rating :rating="$averageRating" class="mt-3" />
                    @endif
                    <dl class="mt-6 border-t border-ink-950/10 pt-4">
                        <div class="flex items-center justify-between text-sm"><dt class="text-ink-950/55">Reviews</dt><dd class="font-bold text-ink-950">{{ $totalReviews }}</dd></div>
                    </dl>
                </section>

                <p class="text-sm leading-6 text-ink-950/50">This page collects the reader's visible ratings and reviews in one place. Spoilers remain hidden until opened.</p>
            </div>
        </aside>
    </section>
</x-app-shell>
