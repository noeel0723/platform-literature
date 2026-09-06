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

    <section class="catalog-grid relative overflow-hidden border-b border-ink-950/10">
        <div class="absolute inset-x-0 top-0 h-72 bg-linear-to-b from-white/35 to-transparent"></div>
        <div class="relative mx-auto max-w-7xl px-5 pb-16 pt-10 sm:px-8 lg:px-10 lg:pb-20 lg:pt-16">
            <a href="{{ route('literatures.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-ink-950/75 transition hover:text-brand-coral">
                <span aria-hidden="true">&larr;</span> Back to catalog
            </a>

            <div class="mt-10 grid gap-8 md:grid-cols-[220px_1fr] lg:grid-cols-[250px_1fr_280px] lg:items-end">
                <div class="relative aspect-[2/3] overflow-hidden border border-ink-950/20 bg-linear-to-br {{ $coverTheme }} shadow-2xl">
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

                <div class="pb-2">
                    <div class="flex flex-wrap items-center gap-3 text-sm font-semibold uppercase tracking-wider text-ink-950/60">
                        <span class="bg-brand-coral px-2.5 py-1 text-ink-950">{{ $literature['type_label'] }}</span>
                        <span>{{ $literature['year'] }}</span>
                    </div>
                    <h1 class="mt-4 font-serif text-5xl font-bold leading-none tracking-tight text-ink-950 sm:text-6xl">{{ $literature['title'] }}</h1>
                    @if ($literature['edition_title'])
                        <p class="mt-3 text-sm text-ink-950/55">Edition title: <span class="font-semibold text-ink-950/75">{{ $literature['edition_title'] }}</span></p>
                    @endif
                    <p class="mt-4 text-lg text-ink-950/60">By <span class="font-semibold text-ink-950">{{ $literature['author'] }}</span></p>
                    <p class="mt-7 max-w-2xl text-lg font-medium uppercase leading-7 tracking-[0.08em] text-ink-950/70">{{ $literature['tagline'] }}</p>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-ink-950/70">{{ $literature['synopsis'] }}</p>
                </div>

                <aside class="border border-ink-950/10 bg-white/40 lg:mb-2" aria-label="Catalog status">
                    <div class="border-b border-ink-950/10 p-5">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-ink-950/60">Catalog status</p>
                        <div class="mt-4 flex items-center gap-3">
                            <span class="grid size-10 place-items-center bg-ink-950 font-bold text-brand-cream">OK</span>
                            <div>
                                <p class="font-bold text-ink-950">Metadata available</p>
                                <p class="text-sm text-ink-950/60">Ready to display</p>
                            </div>
                        </div>
                    </div>
                    <dl class="grid gap-4 p-5 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-950/60">Source</dt><dd class="font-semibold text-ink-950">{{ $literature['source'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-950/60">Format</dt><dd class="font-semibold text-ink-950">{{ $literature['format'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-950/60">Language</dt><dd class="font-semibold text-ink-950">{{ $literature['language'] }}</dd></div>
                    </dl>
                    <a href="{{ route('literatures.index') }}" class="block bg-ink-950 px-5 py-4 text-center font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Find another title</a>
                </aside>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
        <nav class="flex gap-6 overflow-x-auto border-b border-ink-950/10 text-sm font-bold uppercase tracking-[0.14em] text-ink-950/60" aria-label="Literature details">
            <a href="#summary" class="border-b-2 border-brand-coral pb-4 text-ink-950">Summary</a>
            <a href="#authors" class="pb-4 text-ink-950/70 hover:text-brand-coral">Authors</a>
            <a href="#details" class="pb-4 text-ink-950/70 hover:text-brand-coral">Detail</a>
            <a href="#genres" class="pb-4 text-ink-950/70 hover:text-brand-coral">Genre</a>
            <a href="#readlist" class="pb-4 text-ink-950/70 hover:text-brand-coral">Readlist</a>
            <a href="#reviews" class="pb-4 text-ink-950/70 hover:text-brand-coral">Reviews</a>
            <a href="#discussions" class="pb-4 text-ink-950/70 hover:text-brand-coral">Discussions</a>
        </nav>

        <div class="mt-10 grid gap-10 lg:grid-cols-[1.25fr_.75fr]">
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

            <aside id="details" class="h-fit border border-ink-950/10 bg-white/40 p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Source tracking</p>
                <h2 class="mt-3 font-serif text-2xl font-bold text-ink-950">Catalog details</h2>
                <dl class="mt-7 grid gap-5 text-sm">
                    <div class="border-b border-ink-950/10 pb-4"><dt class="text-ink-950/60">Publisher</dt><dd class="mt-1 font-semibold text-ink-950">{{ $literature['publisher'] }}</dd></div>
                    <div class="border-b border-ink-950/10 pb-4"><dt class="text-ink-950/60">External identifier</dt><dd class="mt-1 font-semibold text-ink-950">{{ $literature['identifier'] }}</dd></div>
                    <div class="border-b border-ink-950/10 pb-4"><dt class="text-ink-950/60">Metadata source</dt><dd class="mt-1 font-semibold text-ink-950">{{ $literature['source'] }}</dd></div>
                    <div><dt class="text-ink-950/60">Storage</dt><dd class="mt-1 font-semibold text-ink-950">Internal MySQL catalog</dd></div>
                </dl>
            </aside>
        </div>
    </section>

    <section id="readlist" class="border-t border-ink-950/10 bg-white/20">
        <div class="mx-auto grid max-w-7xl gap-8 px-5 py-14 sm:px-8 lg:grid-cols-[.7fr_1.3fr] lg:px-10 lg:py-20">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Increment 2</p>
                <h2 class="mt-3 font-serif text-4xl font-bold text-ink-950">Manage your reading</h2>
                <p class="mt-4 max-w-md leading-7 text-ink-950/65">Save a status, record your latest page or chapter, and add a short note. Every change is added to your Personal Diary automatically.</p>
                @auth
                    <a href="{{ route('diary.index') }}" class="mt-6 inline-flex font-bold text-ink-950 underline decoration-brand-coral decoration-2 underline-offset-4">Open Personal Diary</a>
                @endauth
            </div>

            @auth
                <form action="{{ route('reading-list.update', $literature['slug']) }}" method="POST" class="grid gap-5 border border-ink-950/15 bg-brand-cream/65 p-6 sm:grid-cols-2 sm:p-8">
                    @csrf
                    @method('PUT')

                    <div class="sm:col-span-2">
                        <label for="status" class="text-sm font-bold text-ink-950">Reading status</label>
                        <select id="status" name="status" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                            @foreach ($readingStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $readingList?->status ?? 'want_to_read') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="progress_value" class="text-sm font-bold text-ink-950">Current progress</label>
                        <input id="progress_value" name="progress_value" type="number" min="0" value="{{ old('progress_value', $readingList?->progress?->current_value) }}" placeholder="Example: 120" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                        @error('progress_value') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="progress_total" class="text-sm font-bold text-ink-950">Total</label>
                        <input id="progress_total" name="progress_total" type="number" min="1" value="{{ old('progress_total', $readingList?->progress?->total_value) }}" placeholder="Example: 320" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                        @error('progress_total') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="progress_unit" class="text-sm font-bold text-ink-950">Progress unit</label>
                        <select id="progress_unit" name="progress_unit" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                            @foreach (['page' => 'Pages', 'chapter' => 'Chapters', 'percent' => 'Percent'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('progress_unit', $readingList?->progress?->unit ?? 'page') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="note" class="text-sm font-bold text-ink-950">Activity note <span class="font-normal text-ink-950/50">(optional)</span></label>
                        <textarea id="note" name="note" rows="3" maxlength="1000" placeholder="Write a short thought or reminder..." class="mt-2 w-full resize-y border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">{{ old('note') }}</textarea>
                        @error('note') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    @if ($readingList)
                        <label class="flex items-start gap-3 text-sm leading-6 text-ink-950/70 sm:col-span-2">
                            <input name="reread" type="checkbox" value="1" class="mt-1 size-4 accent-brand-coral">
                            Start a reread. Progress will return to 0 and the reread count will increase.
                        </label>
                    @endif

                    <button class="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950 sm:col-span-2">Save to Readlist</button>
                </form>
            @else
                <div class="border border-ink-950/15 bg-brand-cream/65 p-7 sm:p-9">
                    <p class="font-serif text-2xl font-bold text-ink-950">Log in to track your reading</p>
                    <p class="mt-3 leading-7 text-ink-950/65">The catalog remains public. An account keeps your status and progress private to you.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Log in</a>
                        <a href="{{ route('register') }}" class="border border-ink-950/20 px-5 py-3 font-bold text-ink-950 transition hover:border-brand-coral">Create account</a>
                    </div>
                </div>
            @endauth
        </div>
    </section>

    <section id="reviews" class="border-t border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <div class="flex flex-col gap-4 border-b border-ink-950/15 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Increment 3 / Social Cataloging</p>
                    <h2 class="mt-3 font-serif text-4xl font-bold text-ink-950">Ratings &amp; reviews</h2>
                    <p class="mt-3 max-w-2xl leading-7 text-ink-950/65">Share a rating, write a review, and protect other readers by marking spoilers.</p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="font-serif text-4xl font-bold text-ink-950">{{ $averageRating ? number_format($averageRating, 1) : '—' }}</p>
                    <p class="text-xs font-bold uppercase tracking-wider text-ink-950/50">{{ $reviews->count() }} {{ Str::plural('rating', $reviews->count()) }}</p>
                </div>
            </div>

            <div class="mt-9 grid gap-10 lg:grid-cols-[.8fr_1.2fr]">
                <div>
                    @auth
                        <form action="{{ route('reviews.update', $literature['slug']) }}" method="POST" class="grid gap-5 border border-ink-950/15 bg-white/40 p-6 sm:p-8">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="rating" class="text-sm font-bold text-ink-950">Your rating</label>
                                <select id="rating" name="rating" required class="mt-2 w-full border border-ink-950/20 bg-brand-cream/60 px-4 py-3 outline-none focus:border-brand-coral">
                                    <option value="">Choose 1–5 stars</option>
                                    @foreach (range(1, 5) as $rating)
                                        <option value="{{ $rating }}" @selected((int) old('rating', $currentReview?->rating) === $rating)>{{ $rating }} {{ Str::plural('star', $rating) }}</option>
                                    @endforeach
                                </select>
                                @error('rating') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="review-body" class="text-sm font-bold text-ink-950">Review <span class="font-normal text-ink-950/50">(optional)</span></label>
                                <textarea id="review-body" name="body" rows="6" maxlength="5000" placeholder="What stayed with you after reading?" class="mt-2 w-full resize-y border border-ink-950/20 bg-brand-cream/60 px-4 py-3 leading-7 outline-none focus:border-brand-coral">{{ old('body', $currentReview?->body) }}</textarea>
                                @error('body') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                            </div>

                            <label class="flex items-start gap-3 text-sm leading-6 text-ink-950/70">
                                <input name="contains_spoiler" type="checkbox" value="1" class="mt-1 size-4 accent-brand-coral" @checked(old('contains_spoiler', $currentReview?->contains_spoiler))>
                                This review contains spoilers. Hide its text until another reader chooses to reveal it.
                            </label>

                            <button class="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">{{ $currentReview ? 'Update review' : 'Publish review' }}</button>
                        </form>
                    @else
                        <div class="border border-ink-950/15 bg-white/40 p-7">
                            <p class="font-serif text-2xl font-bold text-ink-950">Join the conversation</p>
                            <p class="mt-3 leading-7 text-ink-950/65">Log in to rate this work and publish a spoiler-aware review.</p>
                            <a href="{{ route('login') }}" class="mt-6 inline-block bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Log in</a>
                        </div>
                    @endauth
                </div>

                <div>
                    <h3 class="font-serif text-2xl font-bold text-ink-950">Reader reviews</h3>
                    <div class="mt-5 grid gap-4">
                        @forelse ($reviews as $review)
                            <article id="review-{{ $review->id }}" class="scroll-mt-28 border border-ink-950/10 bg-white/35 p-5 sm:p-6">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p class="font-bold text-ink-950">{{ $review->user->name }}</p>
                                        <time datetime="{{ $review->created_at->utc()->toIso8601String() }}" data-local-datetime class="mt-1 block text-xs font-semibold uppercase tracking-wider text-ink-950/45">{{ $review->created_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                                    </div>
                                    <p class="text-lg tracking-widest text-brand-coral" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}<span class="text-ink-950/15">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-ink-950/10 pt-4">
                                    <span class="text-xs font-bold uppercase tracking-wider text-ink-950/45">{{ $review->likes_count }} {{ Str::plural('like', $review->likes_count) }}</span>
                                    @auth
                                        @php($reviewIsLiked = $review->likes->isNotEmpty())
                                        <form action="{{ $reviewIsLiked ? route('reviews.likes.destroy', $review) : route('reviews.likes.store', $review) }}" method="POST">
                                            @csrf
                                            @if ($reviewIsLiked) @method('DELETE') @endif
                                            <button class="border px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition {{ $reviewIsLiked ? 'border-brand-coral bg-brand-coral text-ink-950' : 'border-ink-950/20 text-ink-950 hover:border-brand-coral' }}" aria-pressed="{{ $reviewIsLiked ? 'true' : 'false' }}">{{ $reviewIsLiked ? 'Liked' : 'Like' }}</button>
                                        </form>
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
        </div>
    </section>

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

                            <button class="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Start discussion</button>
                        </form>
                    @else
                        <div class="border border-ink-950/15 bg-brand-cream/70 p-7">
                            <p class="font-serif text-2xl font-bold text-ink-950">Join the discussion</p>
                            <p class="mt-3 leading-7 text-ink-950/65">Log in to start a discussion, leave a comment, or reply to another reader.</p>
                            <a href="{{ route('login') }}" class="mt-6 inline-block bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Log in</a>
                        </div>
                    @endauth
                </div>

                <div class="grid content-start gap-5">
                    @forelse ($discussions as $discussion)
                        <article id="discussion-{{ $discussion->id }}" class="scroll-mt-28 border border-ink-950/10 bg-white/45 p-5 sm:p-7">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-ink-950">{{ $discussion->user->name }}</p>
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
                                        <button class="border px-3 py-1.5 text-xs font-bold uppercase tracking-wider transition {{ $discussionIsLiked ? 'border-brand-coral bg-brand-coral text-ink-950' : 'border-ink-950/20 text-ink-950 hover:border-brand-coral' }}" aria-pressed="{{ $discussionIsLiked ? 'true' : 'false' }}">{{ $discussionIsLiked ? 'Liked' : 'Like' }}</button>
                                    </form>
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
                                                <p class="font-bold text-ink-950">{{ $comment->user->name }}</p>
                                                <time datetime="{{ $comment->created_at->utc()->toIso8601String() }}" data-local-datetime class="text-xs font-semibold uppercase tracking-wider text-ink-950/45">{{ $comment->created_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                                            </div>

                                            @if ($comment->contains_spoiler)
                                                <button type="button" class="mt-3 text-sm font-bold text-ink-950 underline decoration-brand-coral underline-offset-4" data-spoiler-reveal aria-controls="comment-body-{{ $comment->id }}">Reveal spoiler comment</button>
                                                <p id="comment-body-{{ $comment->id }}" hidden class="mt-3 whitespace-pre-line leading-7 text-ink-950/70">{{ $comment->body }}</p>
                                            @else
                                                <p class="mt-3 whitespace-pre-line leading-7 text-ink-950/70">{{ $comment->body }}</p>
                                            @endif

                                            @foreach ($comment->replies as $reply)
                                                <div class="mt-4 ml-4 border-l border-ink-950/15 pl-4">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <p class="text-sm font-bold text-ink-950">{{ $reply->user->name }} <span class="font-normal text-ink-950/45">replied</span></p>
                                                        <time datetime="{{ $reply->created_at->utc()->toIso8601String() }}" data-local-datetime class="text-xs font-semibold uppercase tracking-wider text-ink-950/45">{{ $reply->created_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                                                    </div>
                                                    @if ($reply->contains_spoiler)
                                                        <button type="button" class="mt-2 text-sm font-bold text-ink-950 underline decoration-brand-coral underline-offset-4" data-spoiler-reveal aria-controls="comment-body-{{ $reply->id }}">Reveal spoiler reply</button>
                                                        <p id="comment-body-{{ $reply->id }}" hidden class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-950/70">{{ $reply->body }}</p>
                                                    @else
                                                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-ink-950/70">{{ $reply->body }}</p>
                                                    @endif
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
                                                        <button class="w-fit bg-ink-950 px-4 py-2 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Publish reply</button>
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
                                            <button class="w-fit bg-ink-950 px-4 py-2 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Publish comment</button>
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
