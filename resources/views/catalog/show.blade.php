<x-app-shell :title="$literature['title']">
    @php
        $coverTheme = match ($literature['theme']) {
            'coral' => 'from-brand-coral via-brand-cream to-brand-sky text-ink-950',
            'sky' => 'from-brand-sky via-brand-cream to-ink-950 text-ink-950',
            'cream' => 'from-brand-cream via-brand-sky to-brand-cream text-ink-950',
            'deep' => 'from-ink-950 via-brand-cream to-brand-coral text-ink-950',
            'mixed' => 'from-brand-cream via-brand-coral to-brand-sky text-ink-950',
            default => 'from-brand-cream via-brand-sky to-brand-coral text-ink-950',
        };
    @endphp

    <section class="relative isolate overflow-hidden border-b border-ink-950/10 bg-brand-cream">
        @if ($literature['cover_url'] !== null)
            <div class="absolute inset-0 -z-20 overflow-hidden" aria-hidden="true">
                <img src="{{ $literature['cover_url'] }}" alt="" class="size-full scale-110 object-cover object-center opacity-40 blur-sm">
            </div>
            <div class="absolute inset-0 -z-10 bg-linear-to-t from-brand-cream via-brand-cream/75 to-brand-cream/20" aria-hidden="true"></div>
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-brand-cream/95 via-transparent to-brand-cream/80" aria-hidden="true"></div>
        @else
            <div class="catalog-grid absolute inset-0 -z-10" aria-hidden="true"></div>
        @endif

        <div class="relative mx-auto max-w-7xl px-5 pb-14 pt-8 sm:px-8 lg:px-10 lg:pb-20 lg:pt-10">
            <a href="{{ route('literatures.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-ink-950/75 transition hover:text-brand-coral">
                <span aria-hidden="true">&larr;</span> Back to catalog
            </a>

            <div class="mt-24 grid gap-8 md:grid-cols-[220px_1fr] lg:mt-40 lg:grid-cols-[250px_minmax(0,1fr)_300px] lg:items-end">
                <div class="relative aspect-[2/3] overflow-hidden border border-ink-950/20 bg-linear-to-br {{ $coverTheme }} shadow-[0_14px_36px_rgba(47,58,85,0.12)]">
                    @if ($literature['cover_url'] !== null)
                        <img src="{{ $literature['cover_url'] }}" alt="Cover of {{ $literature['title'] }}" class="absolute inset-0 size-full object-cover">
                        <div class="absolute inset-0 bg-linear-to-t from-ink-950/80 via-transparent to-ink-950/25"></div>
                    @else
                        <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(115deg,transparent_20%,rgba(255,255,255,.35)_50%,transparent_80%)]"></div>
                    @endif
                    <div class="absolute inset-x-0 top-0 flex justify-between p-4 text-xs font-bold uppercase tracking-wider {{ $literature['cover_url'] !== null ? 'text-brand-cream' : '' }}">
                        <span>{{ $literature['type_label'] }}</span>
                        <span>{{ $literature['year'] }}</span>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 p-6 {{ $literature['cover_url'] !== null ? 'text-brand-cream' : '' }}">
                        @if ($literature['cover_url'] === null)
                            <span class="font-serif text-6xl font-bold leading-none">{{ $literature['initials'] }}</span>
                        @endif
                        <p class="mt-4 border-t border-current/40 pt-4 text-xs font-bold uppercase tracking-[0.18em]">{{ $literature['source'] }}</p>
                    </div>
                </div>

                <div class="self-end pb-2">
                    <div class="flex flex-wrap items-center gap-3 text-sm font-semibold uppercase tracking-wider text-ink-950/60">
                        <span class="bg-brand-coral px-2.5 py-1 text-brand-cream">{{ $literature['type_label'] }}</span>
                        <span>{{ $literature['year'] }}</span>
                    </div>
                    <h1 class="mt-4 font-serif text-5xl font-bold leading-none tracking-tight text-ink-950 sm:text-6xl">{{ $literature['title'] }}</h1>
                    @if ($literature['edition_title'])
                        <p class="mt-3 text-sm text-ink-950/55">Edition title: <span class="font-semibold text-ink-950/75">{{ $literature['edition_title'] }}</span></p>
                    @endif
                    <p class="mt-4 text-lg text-ink-950/60">By <span class="font-semibold text-ink-950">{{ $literature['author'] }}</span></p>
                    <p class="mt-7 max-w-2xl text-lg font-medium uppercase leading-7 tracking-[0.08em] text-ink-950/70">{{ $literature['tagline'] }}</p>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-ink-950/75">{{ $literature['synopsis'] }}</p>
                </div>

                <aside class="self-end overflow-hidden border border-ink-950/15 bg-brand-cream/95 shadow-[0_12px_32px_rgba(47,58,85,0.08)] backdrop-blur-md md:col-span-2 lg:col-span-1 lg:mb-2" aria-label="Your literature actions">
                    <div data-community-rating class="border-b border-ink-950/10 p-5 text-center">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-ink-950/55">Community rating</p>
                        <div class="mt-3 flex items-center justify-center gap-3">
                            <x-star-rating :rating="$averageRating ?? 0" />
                            <span class="font-serif text-2xl font-bold text-ink-950">{{ $averageRating ? number_format($averageRating, 1) : '—' }}</span>
                        </div>
                        <p class="mt-2 text-xs text-ink-950/45">Average from {{ $reviews->count() }} {{ Str::plural('reader', $reviews->count()) }}</p>
                    </div>

                    @auth
                        @php($isCompleted = $readingList?->status === 'completed')
                        @php($isInReadlist = $readingList?->status === 'want_to_read')
                        <div data-literature-actions class="grid grid-cols-3 divide-x divide-ink-950/10 border-b border-ink-950/10">
                            <form data-reading-toggle="completed" data-active="{{ $isCompleted ? 'true' : 'false' }}" action="{{ $isCompleted ? route('reading-list.destroy', $literature['slug']) : route('reading-list.update', $literature['slug']) }}" method="POST">
                                @csrf
                                @if ($isCompleted)
                                    @method('DELETE')
                                @else
                                    @method('PUT')
                                    <input type="hidden" name="status" value="completed">
                                @endif
                                <button class="size-full px-2 py-5 text-center font-bold text-ink-950 transition {{ $isCompleted ? 'bg-brand-sky/30' : 'hover:bg-brand-sky/35' }}" aria-pressed="{{ $isCompleted ? 'true' : 'false' }}" title="{{ $isCompleted ? 'Remove completed status' : 'Mark as completed' }}">
                                    <svg class="mx-auto size-8 {{ $isCompleted ? 'text-brand-coral' : 'text-ink-950' }}" aria-hidden="true" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="16" cy="16" r="13"></circle>
                                        <path d="m10 16 4 4 8-9"></path>
                                    </svg>
                                    <span class="mt-2 block text-xs sm:text-sm">Completed</span>
                                </button>
                            </form>

                            <button type="button" data-dialog-open="review-dialog" class="px-2 py-5 text-center font-bold text-ink-950 transition hover:bg-brand-coral hover:text-brand-cream">
                                <span class="block text-3xl leading-none" aria-hidden="true">★</span>
                                <span class="mt-2 block text-xs sm:text-sm">{{ $currentReview ? 'Edit Review' : 'Rate & Review' }}</span>
                            </button>

                            <form data-reading-toggle="readlist" data-active="{{ $isInReadlist ? 'true' : 'false' }}" action="{{ $isInReadlist ? route('reading-list.destroy', $literature['slug']) : route('reading-list.update', $literature['slug']) }}" method="POST">
                                @csrf
                                @if ($isInReadlist)
                                    @method('DELETE')
                                @else
                                    @method('PUT')
                                    <input type="hidden" name="status" value="want_to_read">
                                @endif
                                <button class="size-full px-2 py-5 text-center font-bold text-ink-950 transition {{ $isInReadlist ? 'bg-brand-sky/30' : 'hover:bg-brand-sky/35' }}" aria-pressed="{{ $isInReadlist ? 'true' : 'false' }}" title="{{ $isInReadlist ? 'Remove from Readlist' : 'Add to Readlist' }}">
                                    <svg class="mx-auto size-8 {{ $isInReadlist ? 'fill-brand-coral text-brand-coral' : 'text-ink-950' }}" aria-hidden="true" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M9 5h14a2 2 0 0 1 2 2v20l-9-5-9 5V7a2 2 0 0 1 2-2Z"></path>
                                    </svg>
                                    <span class="mt-2 block text-xs sm:text-sm">Readlist</span>
                                </button>
                            </form>
                        </div>

                        <div data-your-rating class="border-b border-ink-950/10 p-5 text-center">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-ink-950/55">Your Rating</p>
                            <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
                                <div data-star-rating data-rating-input="review-rating" data-rating-dialog="review-dialog" class="flex" role="radiogroup" aria-label="Choose your rating from 0.5 to 5 stars">
                                    @foreach (range(1, 10) as $halfStep)
                                        @php($ratingValue = $halfStep / 2)
                                        <button type="button" data-rating-value="{{ $ratingValue }}" class="h-12 w-5 overflow-hidden text-left text-4xl leading-12 text-ink-950/15 transition focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral" role="radio" aria-checked="false" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                            <span class="block w-10 {{ $halfStep % 2 === 0 ? '-translate-x-1/2' : '' }}">&#9733;</span>
                                        </button>
                                    @endforeach
                                </div>
                                <output data-rating-output for="review-rating" class="min-w-16 text-sm font-bold text-ink-950/60">Choose a rating</output>
                            </div>
                            <p class="mt-2 text-xs text-ink-950/45">Hover to preview. Click a star to continue in the review form.</p>
                        </div>
                    @else
                        <div class="p-5">
                            <p class="text-sm leading-6 text-ink-950/65">Log in to rate, review, and track this literature.</p>
                            <a href="{{ route('login') }}" class="mt-4 block bg-ink-950 px-4 py-3 text-center font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Log in</a>
                        </div>
                    @endauth
                </aside>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
        <nav class="flex gap-6 overflow-x-auto border-b border-ink-950/10 text-sm font-bold uppercase tracking-[0.14em] text-ink-950/60" aria-label="Literature details">
            <a href="#summary" class="border-b-2 border-brand-coral pb-4 text-ink-950">Summary</a>
            <a href="#authors" class="pb-4 text-ink-950/70 hover:text-brand-coral">Authors</a>
            <a href="#genres" class="pb-4 text-ink-950/70 hover:text-brand-coral">Genre</a>
            <a href="#relationships" class="pb-4 text-ink-950/70 hover:text-brand-coral">Discovery</a>
            <a href="#reviews" class="pb-4 text-ink-950/70 hover:text-brand-coral">Reviews</a>
            <a href="#discussions" class="pb-4 text-ink-950/70 hover:text-brand-coral">Discussions</a>
        </nav>

        <div class="mt-10">
            <div id="summary" class="border border-ink-950/10 bg-white/40 p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">About this work</p>
                <h2 class="mt-3 font-serif text-3xl font-bold text-ink-950">Metadata summary</h2>
                <p class="mt-5 max-w-3xl text-base leading-8 text-ink-950/70">{{ $literature['synopsis'] }}</p>
                @if ($literature['synopsis_source_name'] && $literature['synopsis_source_url'])
                    <p class="mt-4 text-xs leading-5 text-ink-950/50">
                        Supplemental summary from
                        <a href="{{ $literature['synopsis_source_url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold underline decoration-brand-coral underline-offset-4">{{ $literature['synopsis_source_name'] }}</a>
                        under the <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4">CC BY-SA</a> license.
                    </p>
                @endif

                <div id="authors" class="mt-10 border-t border-ink-950/10 pt-7">
                    <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-ink-950/60">Authors and creators</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($literature['authors'] as $author)
                            <span class="bg-brand-sky/25 px-3 py-2 font-semibold text-ink-950">{{ $author }}</span>
                        @endforeach
                    </div>
                </div>

                <div id="genres" class="mt-8 border-t border-ink-950/10 pt-7">
                    <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-ink-950/60">Genre</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($literature['genres'] as $genre)
                            <span class="border border-ink-950/15 px-3 py-2 text-ink-950/70">{{ $genre }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </section>

    <section id="relationships" class="scroll-mt-24 border-t border-ink-950/10 bg-white/20">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <div class="grid gap-5 border-b border-ink-950/15 pb-7 lg:grid-cols-[1fr_.75fr] lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Increment 4 / Literature Discovery</p>
                    <h2 class="mt-3 font-serif text-4xl font-bold text-ink-950">Relationship Explorer</h2>
                </div>
                <p class="max-w-2xl leading-7 text-ink-950/65 lg:justify-self-end">Explore sequels, prequels, adaptations, side stories, and related editions without losing the connection between formats.</p>
            </div>

            @forelse ($relationshipGroups as $group)
                <section class="mt-10" aria-labelledby="relationship-{{ $group['type'] }}">
                    <div class="mb-5 flex items-center justify-between gap-4">
                        <h3 id="relationship-{{ $group['type'] }}" class="font-serif text-2xl font-bold text-ink-950">{{ $group['label'] }}</h3>
                        <span class="text-xs font-bold uppercase tracking-[0.16em] text-ink-950/45">{{ $group['items']->count() }} {{ Str::plural('work', $group['items']->count()) }}</span>
                    </div>

                    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($group['items'] as $relatedLiterature)
                            <div class="min-w-0">
                                <div class="mb-2 flex items-center justify-between gap-2 border-l-4 border-brand-coral bg-brand-cream px-3 py-2 text-[0.65rem] font-bold uppercase tracking-[0.13em] text-ink-950">
                                    <span>{{ $group['label'] }}</span>
                                    <span class="truncate text-ink-950/45">{{ $relatedLiterature['relation_source'] }}</span>
                                </div>
                                <x-literature-card :literature="$relatedLiterature" />
                            </div>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="mt-9 border border-dashed border-ink-950/20 bg-brand-cream/45 p-7 sm:p-9">
                    <p class="font-serif text-2xl font-bold text-ink-950">No confirmed relationships yet</p>
                    <p class="mt-3 max-w-2xl leading-7 text-ink-950/60">Relationship data appears after this work is refreshed from a supported source or linked through internal catalog curation.</p>
                </div>
            @endforelse

            <section class="mt-12 border-t border-ink-950/10 pt-9" aria-labelledby="more-by-authors">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Author discovery</p>
                        <h3 id="more-by-authors" class="mt-2 font-serif text-3xl font-bold text-ink-950">More by these authors</h3>
                    </div>
                    <p class="text-sm text-ink-950/55">Other catalog entries matched by creator identity.</p>
                </div>

                @if ($authorDiscoveries->isNotEmpty())
                    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
                        @foreach ($authorDiscoveries as $discovery)
                            <x-literature-card :literature="$discovery" />
                        @endforeach
                    </div>
                @else
                    <p class="border border-dashed border-ink-950/20 p-6 text-ink-950/60">No other works by the same authors are available in the local catalog yet.</p>
                @endif
            </section>
        </div>
    </section>

    <section id="reviews" class="border-t border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <div class="flex flex-col gap-5 border-b border-ink-950/15 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="font-serif text-4xl font-bold text-ink-950">Ratings &amp; reviews</h2>
                    <p class="mt-3 max-w-2xl leading-7 text-ink-950/65">Rate in half-star steps, write a review, and protect other readers by marking spoilers.</p>
                </div>
                <div class="flex flex-wrap items-center gap-4 sm:justify-end">
                    <div class="text-left sm:text-right">
                        <div class="flex items-center gap-3 sm:justify-end">
                            <x-star-rating :rating="$averageRating ?? 0" />
                            <p class="font-serif text-3xl font-bold text-ink-950">{{ $averageRating ? number_format($averageRating, 1) : '—' }}</p>
                        </div>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/50">{{ $reviews->count() }} {{ Str::plural('rating', $reviews->count()) }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-9">
                <h3 class="font-serif text-2xl font-bold text-ink-950">Reader reviews</h3>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    @forelse ($reviews as $review)
                            <article id="review-{{ $review->id }}" class="scroll-mt-28 border border-ink-950/10 bg-white/35 p-5 sm:p-6">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <a href="{{ route('profiles.show', $review->user) }}" class="font-bold text-ink-950 transition hover:text-brand-coral">{{ $review->user->name }}</a>
                                        <time datetime="{{ $review->created_at->utc()->toIso8601String() }}" data-local-datetime class="mt-1 block text-xs font-semibold uppercase tracking-wider text-ink-950/45">{{ $review->created_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                                    </div>
                                    <div class="text-right">
                                        <x-star-rating :rating="$review->rating" />
                                        <p class="mt-1 text-xs font-semibold text-ink-950/50">{{ number_format($review->rating, 1) }} / 5</p>
                                    </div>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-ink-950/10 pt-4">
                                    <span class="text-xs font-bold uppercase tracking-wider text-ink-950/45">{{ $review->likes_count }} {{ Str::plural('like', $review->likes_count) }}</span>
                                    @auth
                                        @php($reviewIsLiked = $review->likes->isNotEmpty())
                                        <form action="{{ $reviewIsLiked ? route('reviews.likes.destroy', $review) : route('reviews.likes.store', $review) }}" method="POST">
                                            @csrf
                                            @if ($reviewIsLiked) @method('DELETE') @endif
                                            <button class="border px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition {{ $reviewIsLiked ? 'border-brand-coral bg-brand-coral text-brand-cream' : 'border-ink-950/20 text-ink-950 hover:border-brand-coral' }}" aria-pressed="{{ $reviewIsLiked ? 'true' : 'false' }}">{{ $reviewIsLiked ? 'Liked' : 'Like' }}</button>
                                        </form>
                                        @if (auth()->id() !== $review->user_id)
                                            <x-report-form target-type="review" :target-id="$review->id" />
                                        @endif
                                    @endauth
                                </div>

                                @if ($review->body)
                                    @if ($review->contains_spoiler)
                                        <button type="button" class="mt-5 border border-ink-950/20 px-4 py-2 text-sm font-bold text-ink-950 transition hover:border-brand-coral" data-spoiler-reveal aria-controls="review-body-{{ $review->id }}">Reveal spoiler review</button>
                                        <p id="review-body-{{ $review->id }}" hidden class="mt-5 whitespace-pre-line leading-7 text-ink-950/70">{{ $review->body }}</p>
                                    @else
                                        <p class="mt-5 whitespace-pre-line leading-7 text-ink-950/70">{{ $review->body }}</p>
                                    @endif
                                @else
                                    <p class="mt-5 text-sm italic text-ink-950/50">Rating only.</p>
                                @endif
                            </article>
                    @empty
                        <div class="border border-dashed border-ink-950/20 p-7 text-ink-950/60">No reviews yet. Be the first reader to share a rating.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    @auth
        <dialog id="review-dialog" data-review-dialog data-auto-open="{{ $errors->has('rating') || $errors->has('body') ? 'true' : 'false' }}" class="review-dialog m-auto max-h-[90vh] w-[min(920px,calc(100%_-_2rem))] overflow-y-auto border border-ink-950/20 bg-brand-cream p-0 text-ink-950 shadow-[0_18px_48px_rgba(47,58,85,0.18)]">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-ink-950/10 bg-ink-950 px-5 py-4 text-brand-cream sm:px-7">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-sky">Your reading experience</p>
                    <h2 class="mt-1 font-serif text-2xl font-bold">Rate &amp; review</h2>
                </div>
                <button type="button" data-dialog-close class="grid size-10 place-items-center border border-brand-cream/30 text-2xl leading-none transition hover:border-brand-coral hover:text-brand-coral" aria-label="Close review dialog">&times;</button>
            </div>

            <form action="{{ route('reviews.update', $literature['slug']) }}" method="POST" class="grid gap-7 p-5 sm:p-7 md:grid-cols-[180px_minmax(0,1fr)]">
                @csrf
                @method('PUT')

                <div>
                    <div class="aspect-[2/3] overflow-hidden border border-ink-950/15 bg-linear-to-br {{ $coverTheme }} shadow-[0_8px_24px_rgba(47,58,85,0.10)]">
                        @if ($literature['cover_url'] !== null)
                            <img src="{{ $literature['cover_url'] }}" alt="Cover of {{ $literature['title'] }}" class="size-full object-cover">
                        @else
                            <div class="grid size-full place-items-center p-4 text-center font-serif text-5xl font-bold">{{ $literature['initials'] }}</div>
                        @endif
                    </div>
                    <p class="mt-3 text-center text-xs font-bold uppercase tracking-[0.14em] text-ink-950/55">{{ $literature['type_label'] }}</p>
                </div>

                <div class="min-w-0">
                    <h3 class="font-serif text-3xl font-bold leading-tight text-ink-950">{{ $literature['title'] }}</h3>
                    <p class="mt-1 text-sm text-ink-950/55">{{ $literature['author'] }}@if ($literature['year']) &middot; {{ $literature['year'] }} @endif</p>

                    <fieldset class="mt-6">
                        <legend class="text-sm font-bold uppercase tracking-[0.16em] text-ink-950">Your Rating</legend>
                        <input id="review-rating" name="rating" type="hidden" required value="{{ old('rating', $currentReview?->rating) }}">
                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            <div data-star-rating data-rating-input="review-rating" class="flex" role="radiogroup" aria-label="Choose a rating from 0.5 to 5 stars">
                                @foreach (range(1, 10) as $halfStep)
                                    @php($ratingValue = $halfStep / 2)
                                    <button type="button" data-rating-value="{{ $ratingValue }}" class="h-14 w-6 overflow-hidden text-left text-5xl leading-14 text-ink-950/15 transition focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-coral" role="radio" aria-checked="false" aria-label="{{ number_format($ratingValue, 1) }} out of 5 stars">
                                        <span class="block w-12 {{ $halfStep % 2 === 0 ? '-translate-x-1/2' : '' }}">&#9733;</span>
                                    </button>
                                @endforeach
                            </div>
                            <output data-rating-output for="review-rating" class="min-w-16 text-sm font-bold text-ink-950/60">Choose a rating</output>
                        </div>
                        <p class="mt-2 text-xs text-ink-950/50">Hover to preview a rating, then click to select it. Half-star ratings such as 1.5, 2.5, or 4.5 are supported.</p>
                        @error('rating') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </fieldset>

                    <div class="mt-6">
                        <label for="review-body" class="text-sm font-bold text-ink-950">Review <span class="font-normal text-ink-950/50">(optional)</span></label>
                        <textarea id="review-body" name="body" rows="7" maxlength="5000" placeholder="What stayed with you after reading?" class="mt-2 w-full resize-y border border-ink-950/20 bg-white/55 px-4 py-3 leading-7 outline-none focus:border-brand-coral">{{ old('body', $currentReview?->body) }}</textarea>
                        @error('body') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <label class="mt-5 flex items-start gap-3 text-sm leading-6 text-ink-950/70">
                        <input name="contains_spoiler" type="checkbox" value="1" class="mt-1 size-4 accent-brand-coral" @checked(old('contains_spoiler', $currentReview?->contains_spoiler))>
                        This review contains spoilers. Hide its text until another reader chooses to reveal it.
                    </label>

                    <div class="mt-7 flex flex-wrap items-center justify-end gap-3 border-t border-ink-950/10 pt-5">
                        @if ($currentReview)
                            <button type="submit" form="delete-review-form" class="border border-red-700/30 px-5 py-3 font-bold text-red-800 transition hover:border-red-700 hover:bg-red-700 hover:text-white sm:mr-auto">Delete review</button>
                        @endif
                        <button type="button" data-dialog-close class="border border-ink-950/20 px-5 py-3 font-bold text-ink-950 transition hover:border-brand-coral">Cancel</button>
                        <button class="bg-ink-950 px-6 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">{{ $currentReview ? 'Update review' : 'Publish review' }}</button>
                    </div>
                </div>
            </form>

            @if ($currentReview)
                <form id="delete-review-form" action="{{ route('reviews.destroy', $literature['slug']) }}" method="POST" data-confirm-submit="Delete your rating and review? This action cannot be undone." class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </dialog>
    @endauth

    <section id="discussions" class="border-t border-ink-950/10 bg-white/20">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <div class="flex flex-col gap-4 border-b border-ink-950/15 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Increment 3 / Community</p>
                    <h2 class="mt-3 font-serif text-4xl font-bold text-ink-950">Discussions &amp; comments</h2>
                    <p class="mt-3 max-w-2xl leading-7 text-ink-950/65">Start a focused conversation about this work, respond to other readers, and mark spoilers before publishing.</p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs font-bold uppercase tracking-wider text-ink-950/50">{{ $discussionCount }} {{ Str::plural('discussion', $discussionCount) }}</p>
                    @if ($discussionCount > $discussions->count())
                        <p class="mt-1 text-xs text-ink-950/45">Showing the latest {{ $discussions->count() }}</p>
                    @endif
                </div>
            </div>

            <div class="mt-9 grid gap-10 lg:grid-cols-[.7fr_1.3fr]">
                <div>
                    @auth
                        <form action="{{ route('discussions.store', $literature['slug']) }}" method="POST" class="grid gap-5 border border-ink-950/15 bg-brand-cream/70 p-6 sm:p-8">
                            @csrf

                            <div>
                                <label for="discussion-title" class="text-sm font-bold text-ink-950">Discussion title</label>
                                <input id="discussion-title" name="title" value="{{ old('title') }}" required minlength="3" maxlength="150" placeholder="What would you like to discuss?" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                                @error('title') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="discussion-body" class="text-sm font-bold text-ink-950">Opening post</label>
                                <textarea id="discussion-body" name="discussion_body" rows="7" required minlength="10" maxlength="5000" placeholder="Add context so other readers can join the conversation..." class="mt-2 w-full resize-y border border-ink-950/20 bg-white/60 px-4 py-3 leading-7 outline-none focus:border-brand-coral">{{ old('discussion_body') }}</textarea>
                                @error('discussion_body') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <label class="flex items-start gap-3 text-sm leading-6 text-ink-950/70">
                                <input name="discussion_contains_spoiler" type="checkbox" value="1" class="mt-1 size-4 accent-brand-coral" @checked(old('discussion_contains_spoiler'))>
                                This discussion contains spoilers. Hide the opening post until readers reveal it.
                            </label>

                            <button class="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Start discussion</button>
                        </form>
                    @else
                        <div class="border border-ink-950/15 bg-brand-cream/70 p-7">
                            <p class="font-serif text-2xl font-bold text-ink-950">Join the discussion</p>
                            <p class="mt-3 leading-7 text-ink-950/65">Log in to start a discussion, leave a comment, or reply to another reader.</p>
                            <a href="{{ route('login') }}" class="mt-6 inline-block bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Log in</a>
                        </div>
                    @endauth
                </div>

                <div class="grid content-start gap-5">
                    @forelse ($discussions as $discussion)
                        <article id="discussion-{{ $discussion->id }}" class="scroll-mt-28 border border-ink-950/10 bg-white/45 p-5 sm:p-7">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <a href="{{ route('profiles.show', $discussion->user) }}" class="font-bold text-ink-950 transition hover:text-brand-coral">{{ $discussion->user->name }}</a>
                                    <time datetime="{{ $discussion->created_at->utc()->toIso8601String() }}" data-local-datetime class="mt-1 block text-xs font-semibold uppercase tracking-wider text-ink-950/45">{{ $discussion->created_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                                </div>
                                <span class="text-xs font-bold uppercase tracking-wider text-ink-950/45">{{ $discussion->comments_count }} {{ Str::plural('comment', $discussion->comments_count) }}</span>
                            </div>

                            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-ink-950/10 pt-4">
                                <span class="text-xs font-bold uppercase tracking-wider text-ink-950/45">{{ $discussion->likes_count }} {{ Str::plural('like', $discussion->likes_count) }}</span>
                                @auth
                                    @php($discussionIsLiked = $discussion->likes->isNotEmpty())
                                    <form action="{{ $discussionIsLiked ? route('discussions.likes.destroy', $discussion) : route('discussions.likes.store', $discussion) }}" method="POST">
                                        @csrf
                                        @if ($discussionIsLiked) @method('DELETE') @endif
                                        <button class="border px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition {{ $discussionIsLiked ? 'border-brand-coral bg-brand-coral text-brand-cream' : 'border-ink-950/20 text-ink-950 hover:border-brand-coral' }}" aria-pressed="{{ $discussionIsLiked ? 'true' : 'false' }}">{{ $discussionIsLiked ? 'Liked' : 'Like' }}</button>
                                    </form>
                                    @if (auth()->id() !== $discussion->user_id)
                                        <x-report-form target-type="discussion" :target-id="$discussion->id" />
                                    @endif
                                @endauth
                            </div>

                            <h3 class="mt-5 font-serif text-2xl font-bold text-ink-950">{{ $discussion->title }}</h3>
                            @if ($discussion->contains_spoiler)
                                <button type="button" class="mt-5 border border-ink-950/20 px-4 py-2 text-sm font-bold text-ink-950 transition hover:border-brand-coral" data-spoiler-reveal aria-controls="discussion-body-{{ $discussion->id }}">Reveal spoiler discussion</button>
                                <p id="discussion-body-{{ $discussion->id }}" hidden class="mt-5 whitespace-pre-line leading-7 text-ink-950/70">{{ $discussion->body }}</p>
                            @else
                                <p class="mt-5 whitespace-pre-line leading-7 text-ink-950/70">{{ $discussion->body }}</p>
                            @endif

                            <div class="mt-7 border-t border-ink-950/10 pt-6">
                                <h4 class="text-sm font-bold uppercase tracking-[0.16em] text-ink-950/55">Comments</h4>
                                <div class="mt-4 grid gap-4">
                                    @foreach ($discussion->topLevelComments as $comment)
                                        <div class="border-l-2 border-brand-sky bg-brand-cream/45 p-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <a href="{{ route('profiles.show', $comment->user) }}" class="font-bold text-ink-950 transition hover:text-brand-coral">{{ $comment->user->name }}</a>
                                                <time datetime="{{ $comment->created_at->utc()->toIso8601String() }}" data-local-datetime class="text-xs font-semibold uppercase tracking-wider text-ink-950/45">{{ $comment->created_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                                            </div>

                                            @if ($comment->contains_spoiler)
                                                <button type="button" class="mt-3 text-sm font-bold text-ink-950 underline decoration-brand-coral underline-offset-4" data-spoiler-reveal aria-controls="comment-body-{{ $comment->id }}">Reveal spoiler comment</button>
                                                <p id="comment-body-{{ $comment->id }}" hidden class="mt-3 whitespace-pre-line leading-7 text-ink-950/70">{{ $comment->body }}</p>
                                            @else
                                                <p class="mt-3 whitespace-pre-line leading-7 text-ink-950/70">{{ $comment->body }}</p>
                                            @endif

                                            @auth
                                                @if (auth()->id() !== $comment->user_id)
                                                    <div class="mt-3"><x-report-form target-type="comment" :target-id="$comment->id" /></div>
                                                @endif
                                            @endauth

                                            @foreach ($comment->replies as $reply)
                                                <div class="mt-4 ml-4 border-l border-ink-950/15 pl-4">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="text-sm font-bold text-ink-950"><a href="{{ route('profiles.show', $reply->user) }}" class="transition hover:text-brand-coral">{{ $reply->user->name }}</a> <span class="font-normal text-ink-950/45">replied</span></p>
                                                        <time datetime="{{ $reply->created_at->utc()->toIso8601String() }}" data-local-datetime class="text-xs font-semibold uppercase tracking-wider text-ink-950/45">{{ $reply->created_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                                                    </div>
                                                    @if ($reply->contains_spoiler)
                                                        <button type="button" class="mt-2 text-sm font-bold text-ink-950 underline decoration-brand-coral underline-offset-4" data-spoiler-reveal aria-controls="comment-body-{{ $reply->id }}">Reveal spoiler reply</button>
                                                        <p id="comment-body-{{ $reply->id }}" hidden class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-950/70">{{ $reply->body }}</p>
                                                    @else
                                                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-950/70">{{ $reply->body }}</p>
                                                    @endif
                                                    @auth
                                                        @if (auth()->id() !== $reply->user_id)
                                                            <div class="mt-2"><x-report-form target-type="comment" :target-id="$reply->id" label="Report reply" /></div>
                                                        @endif
                                                    @endauth
                                                </div>
                                            @endforeach

                                            @auth
                                                <details class="mt-4">
                                                    <summary class="w-fit cursor-pointer text-sm font-bold text-ink-950 hover:text-brand-coral">Reply</summary>
                                                    <form action="{{ route('discussions.comments.store', $discussion) }}" method="POST" class="mt-3 grid gap-3">
                                                        @csrf
                                                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                                        <label for="reply-{{ $comment->id }}" class="sr-only">Reply to {{ $comment->user->name }}</label>
                                                        <textarea id="reply-{{ $comment->id }}" name="comment_body" rows="3" required maxlength="3000" placeholder="Write a reply..." class="w-full resize-y border border-ink-950/20 bg-white/60 px-4 py-3 text-sm leading-6 outline-none focus:border-brand-coral"></textarea>
                                                        <label class="flex items-center gap-2 text-xs text-ink-950/60">
                                                            <input name="comment_contains_spoiler" type="checkbox" value="1" class="size-4 accent-brand-coral">
                                                            This reply contains spoilers
                                                        </label>
                                                        <button class="w-fit bg-ink-950 px-4 py-2 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Publish reply</button>
                                                    </form>
                                                </details>
                                            @endauth
                                        </div>
                                    @endforeach
                                </div>

                                @auth
                                    <form action="{{ route('discussions.comments.store', $discussion) }}" method="POST" class="mt-5 grid gap-3 border-t border-ink-950/10 pt-5">
                                        @csrf
                                        <label for="comment-{{ $discussion->id }}" class="text-sm font-bold text-ink-950">Add a comment</label>
                                        <textarea id="comment-{{ $discussion->id }}" name="comment_body" rows="3" required maxlength="3000" placeholder="Add to this discussion..." class="w-full resize-y border border-ink-950/20 bg-brand-cream/60 px-4 py-3 leading-6 outline-none focus:border-brand-coral"></textarea>
                                        @error('comment_body') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                                        @error('parent_id') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <label class="flex items-center gap-2 text-xs text-ink-950/60">
                                                <input name="comment_contains_spoiler" type="checkbox" value="1" class="size-4 accent-brand-coral">
                                                This comment contains spoilers
                                            </label>
                                            <button class="w-fit bg-ink-950 px-4 py-2 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Publish comment</button>
                                        </div>
                                    </form>
                                @endauth
                            </div>
                        </article>
                    @empty
                        <div class="border border-dashed border-ink-950/20 p-7 text-ink-950/60">No discussions yet. Start a focused conversation about this work.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</x-app-shell>
