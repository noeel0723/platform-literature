<x-app-shell :title="$user->name.' Reviews'">
    <x-profile-subnav :$user current="reviews" />

    <section class="mx-auto grid max-w-7xl gap-7 px-5 py-8 sm:px-8 lg:grid-cols-[minmax(0,1fr)_220px] lg:px-10 lg:py-10">
        <main class="min-w-0" aria-labelledby="profile-reviews-heading">
            <div class="flex items-end justify-between gap-4 border-b border-ink-950/20 pb-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Reader notes</p>
                    <h1 id="profile-reviews-heading" class="mt-1 font-serif text-2xl font-bold text-ink-950">{{ $user->name }}'s Reviews</h1>
                </div>
                <span class="text-sm text-ink-950/50">{{ $totalReviews }} {{ Str::plural('review', $totalReviews) }}</span>
            </div>

            <div data-profile-review-list data-density="compact">
                @forelse ($reviews as $review)
                    @php
                        $literature = $review->literature;
                        $reviewTitle = $literature->displayTitle();
                        $spoilerId = 'review-spoiler-'.$review->id;
                    @endphp
                    <article class="grid grid-cols-[52px_minmax(0,1fr)] gap-3 border-b border-ink-950/12 py-4 sm:grid-cols-[60px_minmax(0,1fr)] sm:gap-4" data-profile-review>
                        <a href="{{ route('literatures.show', $literature) }}" class="block aspect-[2/3] self-start overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25 shadow-[0_3px_10px_rgba(47,58,85,0.06)]">
                            @if ($literature->displayCoverUrl())
                                <img src="{{ $literature->displayCoverUrl() }}" alt="Cover of {{ $reviewTitle }}" class="size-full object-cover" loading="lazy">
                            @else
                                <span class="grid size-full place-items-center font-serif text-sm font-bold text-ink-950">{{ Str::upper(Str::substr($reviewTitle, 0, 2)) }}</span>
                            @endif
                        </a>

                        <div class="min-w-0">
                            <div class="flex min-w-0 items-start justify-between gap-3">
                                <h2 class="min-w-0 truncate font-serif text-lg font-bold leading-6 text-ink-950 sm:text-xl">
                                    <a href="{{ route('literatures.show', $literature) }}" class="hover:text-brand-coral" title="{{ $reviewTitle }}">{{ $reviewTitle }}</a>
                                    @if ($literature->displayPublicationYear())
                                        <span class="font-sans text-xs font-normal text-ink-950/45">{{ $literature->displayPublicationYear() }}</span>
                                    @endif
                                </h2>
                                @if (auth()->id() === $user->id)
                                    <a href="{{ route('literatures.show', $literature) }}?review=edit" class="shrink-0 text-[0.65rem] font-bold uppercase tracking-wider text-brand-coral hover:underline">Edit review</a>
                                @endif
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-ink-950/50">
                                <x-star-rating :rating="$review->rating" size="sm" />
                                <span class="font-bold text-ink-950/65">{{ number_format($review->rating, 1) }}</span>
                                <time datetime="{{ $review->updated_at->utc()->toIso8601String() }}" data-local-datetime>{{ $review->updated_at->utc()->format('M j, Y') }}</time>
                            </div>

                            @if ($review->body)
                                @if ($review->contains_spoiler)
                                    <button type="button" data-spoiler-reveal aria-controls="{{ $spoilerId }}" class="mt-2 border border-ink-950/15 px-2.5 py-1.5 text-[0.65rem] font-bold uppercase tracking-wider text-ink-950 transition hover:border-brand-coral">Reveal spoiler review</button>
                                    <p id="{{ $spoilerId }}" hidden class="mt-2 whitespace-pre-line font-serif text-base leading-6 text-ink-950/75">{{ $review->body }}</p>
                                @else
                                    <p class="mt-2 whitespace-pre-line font-serif text-base leading-6 text-ink-950/75">{{ $review->body }}</p>
                                @endif
                            @else
                                <p class="mt-2 text-xs italic text-ink-950/45">Rating only.</p>
                            @endif

                            <p class="mt-2 text-xs text-ink-950/45">{{ $review->likes_count }} {{ Str::plural('like', $review->likes_count) }}</p>
                        </div>
                    </article>
                @empty
                    <div class="border-b border-ink-950/12 py-10 text-center">
                        <p class="font-serif text-2xl font-bold text-ink-950">No reviews yet.</p>
                        <p class="mt-2 text-sm text-ink-950/55">Written reviews and rating-only entries will appear here.</p>
                    </div>
                @endforelse
            </div>

            @if ($reviews->hasPages())
                <div class="mt-6">{{ $reviews->links() }}</div>
            @endif
        </main>

        <aside class="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-5">
            <div class="sticky top-24 space-y-4">
                <section class="border border-ink-950/10 bg-brand-cream/80 p-4" aria-labelledby="review-summary-heading" data-review-summary>
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Review summary</p>
                    <h2 id="review-summary-heading" class="mt-1 font-serif text-lg font-bold text-ink-950">Reader average</h2>
                    <div class="mt-3 flex items-end gap-2">
                        <strong class="font-serif text-3xl leading-none text-ink-950">{{ $totalReviews > 0 ? number_format($averageRating, 1) : '—' }}</strong>
                        <span class="pb-0.5 text-xs text-ink-950/45">out of 5</span>
                    </div>
                    @if ($totalReviews > 0)
                        <x-star-rating :rating="$averageRating" size="sm" class="mt-2" />
                    @endif
                    <dl class="mt-4 border-t border-ink-950/10 pt-3">
                        <div class="flex items-center justify-between text-xs"><dt class="text-ink-950/55">Reviews</dt><dd class="font-bold text-ink-950">{{ $totalReviews }}</dd></div>
                    </dl>
                </section>

                <p class="text-xs leading-5 text-ink-950/50">Visible ratings and reviews in one place. Spoilers stay hidden until opened.</p>
            </div>
        </aside>
    </section>
</x-app-shell>
