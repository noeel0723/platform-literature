<x-app-shell :title="$user->name">
    @php
        $initials = Str::of($user->name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    @endphp

    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
            <div class="grid gap-7 md:grid-cols-[140px_minmax(0,1fr)] md:items-center">
                <div class="grid size-32 place-items-center overflow-hidden rounded-full border border-ink-950/15 bg-ink-950 font-serif text-4xl font-bold text-brand-cream shadow-xl sm:size-36">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}'s profile photo" class="size-full object-cover">
                    @else
                        {{ $initials ?: 'LH' }}
                    @endif
                </div>

                <div class="min-w-0">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader profile</p>
                            <h1 class="mt-2 font-serif text-5xl font-bold leading-none text-ink-950">{{ $user->name }}</h1>
                            <p class="mt-2 font-semibold text-ink-950/55">&#64;{{ $user->username }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            @if (auth()->id() === $user->id)
                                <a href="{{ route('profiles.edit') }}" class="w-fit border border-ink-950/20 px-5 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral">Edit profile</a>
                            @elseif (auth()->check())
                                <form action="{{ $isFollowing ? route('profiles.follow.destroy', $user) : route('profiles.follow.store', $user) }}" method="POST">
                                    @csrf
                                    @if ($isFollowing) @method('DELETE') @endif
                                    <button class="w-fit px-5 py-3 text-sm font-bold transition {{ $isFollowing ? 'border border-ink-950/20 text-ink-950 hover:border-brand-coral' : 'bg-ink-950 text-brand-cream hover:bg-brand-coral hover:text-ink-950' }}">{{ $isFollowing ? 'Following' : 'Follow' }}</button>
                                </form>
                            @else
                                <a href="{{ route('login') }}" class="w-fit bg-ink-950 px-5 py-3 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Log in to follow</a>
                            @endif
                        </div>
                    </div>

                    @if ($user->bio)
                        <p class="mt-5 max-w-3xl text-lg leading-8 text-ink-950/70">{{ $user->bio }}</p>
                    @else
                        <p class="mt-5 text-ink-950/45">This reader has not added a bio yet.</p>
                    @endif

                    <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-sm text-ink-950/55">
                        @if ($user->location)
                            <span><span aria-hidden="true">●</span> {{ $user->location }}</span>
                        @endif
                        <span>Member since {{ $user->created_at->format('F Y') }}</span>
                    </div>
                </div>
            </div>

            @error('user') <p class="mt-5 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror

            <dl class="mt-10 grid grid-cols-2 gap-px border border-ink-950/10 bg-ink-950/10 sm:grid-cols-3 lg:grid-cols-6">
                <div class="bg-brand-cream/90 p-5"><dd class="font-serif text-3xl font-bold text-ink-950">{{ $user->reading_lists_count }}</dd><dt class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/50">In Readlist</dt></div>
                <div class="bg-brand-cream/90 p-5"><dd class="font-serif text-3xl font-bold text-ink-950">{{ $user->completed_literature_count }}</dd><dt class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/50">Completed</dt></div>
                <div class="bg-brand-cream/90 p-5"><dd class="font-serif text-3xl font-bold text-ink-950">{{ $user->reviews_count }}</dd><dt class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/50">Reviews</dt></div>
                <div class="bg-brand-cream/90 p-5"><dd class="font-serif text-3xl font-bold text-ink-950">{{ $user->discussions_count }}</dd><dt class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/50">Discussions</dt></div>
                <div class="bg-brand-cream/90"><a href="{{ route('profiles.following', $user) }}" class="block p-5 transition hover:bg-brand-sky/25"><dd class="font-serif text-3xl font-bold text-ink-950">{{ $user->following_count }}</dd><dt class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/50">Following</dt></a></div>
                <div class="bg-brand-cream/90"><a href="{{ route('profiles.followers', $user) }}" class="block p-5 transition hover:bg-brand-sky/25"><dd class="font-serif text-3xl font-bold text-ink-950">{{ $user->followers_count }}</dd><dt class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/50">Followers</dt></a></div>
            </dl>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
        <div class="grid gap-12 lg:grid-cols-[1.35fr_.65fr]">
            <div>
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                    <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Personal shelf</p><h2 class="mt-2 font-serif text-3xl font-bold text-ink-950">Favorite literature</h2></div>
                    <span class="text-sm text-ink-950/50">Up to four</span>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-5 sm:grid-cols-4">
                    @forelse ($user->favoriteLiteratures as $literature)
                        <article class="group min-w-0">
                            <a href="{{ route('literatures.show', $literature) }}" class="block">
                                <div class="aspect-[2/3] overflow-hidden border border-ink-950/15 bg-brand-sky/20 shadow-lg transition group-hover:-translate-y-1 group-hover:border-brand-coral">
                                    @if ($literature->cover_url)
                                        <img src="{{ $literature->cover_url }}" alt="Cover of {{ $literature->original_title ?? $literature->title }}" class="size-full object-cover">
                                    @else
                                        <div class="grid size-full place-items-center p-4 text-center font-serif text-3xl font-bold text-ink-950">{{ Str::upper(Str::substr($literature->title, 0, 2)) }}</div>
                                    @endif
                                </div>
                                <h3 class="mt-3 truncate font-bold text-ink-950 group-hover:text-brand-coral">{{ $literature->original_title ?? $literature->title }}</h3>
                                <p class="mt-1 truncate text-sm text-ink-950/55">{{ $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable' }}</p>
                            </a>
                        </article>
                    @empty
                        <div class="col-span-full border border-dashed border-ink-950/20 p-7 text-ink-950/55">No favorite literature has been selected.</div>
                    @endforelse
                </div>
            </div>

            <aside>
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                    <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Creative voices</p><h2 class="mt-2 font-serif text-3xl font-bold text-ink-950">Favorite authors</h2></div>
                </div>
                <ol class="mt-6 grid gap-3">
                    @forelse ($user->favoriteAuthors as $author)
                        <li class="flex items-center gap-4 border border-ink-950/10 bg-white/35 p-4">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-sky/35 font-serif text-lg font-bold text-ink-950">{{ $loop->iteration }}</span>
                            <div class="min-w-0"><p class="truncate font-bold text-ink-950">{{ $author->name }}</p><p class="text-xs uppercase tracking-wider text-ink-950/45">Favorite author</p></div>
                        </li>
                    @empty
                        <li class="border border-dashed border-ink-950/20 p-7 text-ink-950/55">No favorite authors have been selected.</li>
                    @endforelse
                </ol>
            </aside>
        </div>
    </section>

    <section class="border-t border-ink-950/10 bg-white/20">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 py-14 sm:px-8 lg:grid-cols-2 lg:px-10 lg:py-20">
            <div>
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3"><h2 class="font-serif text-3xl font-bold text-ink-950">Recent reviews</h2><span class="text-sm text-ink-950/50">Latest four</span></div>
                <div class="mt-5 grid gap-4">
                    @forelse ($recentReviews as $review)
                        <article class="border border-ink-950/10 bg-brand-cream/65 p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3"><a href="{{ route('literatures.show', $review->literature) }}#review-{{ $review->id }}" class="font-bold text-ink-950 hover:text-brand-coral">{{ $review->literature->original_title ?? $review->literature->title }}</a><x-star-rating :rating="$review->rating" size="sm" /></div>
                            @if ($review->body)
                                <p class="mt-3 line-clamp-3 leading-7 text-ink-950/65">{{ $review->contains_spoiler ? 'This review contains spoilers.' : $review->body }}</p>
                            @else
                                <p class="mt-3 text-sm italic text-ink-950/45">Rating only.</p>
                            @endif
                        </article>
                    @empty
                        <div class="border border-dashed border-ink-950/20 p-7 text-ink-950/55">No reviews yet.</div>
                    @endforelse
                </div>
            </div>

            <div>
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3"><h2 class="font-serif text-3xl font-bold text-ink-950">Recently completed</h2><span class="text-sm text-ink-950/50">Latest four</span></div>
                <div class="mt-5 grid gap-3">
                    @forelse ($recentCompletions as $readingList)
                        <a href="{{ route('literatures.show', $readingList->literature) }}" class="flex items-center justify-between gap-4 border border-ink-950/10 bg-brand-cream/65 p-5 transition hover:border-brand-coral">
                            <div class="min-w-0"><p class="truncate font-bold text-ink-950">{{ $readingList->literature->original_title ?? $readingList->literature->title }}</p><p class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/45">Completed</p></div>
                            @if ($readingList->completed_at)
                                <time datetime="{{ $readingList->completed_at->utc()->toIso8601String() }}" data-local-datetime class="shrink-0 text-xs text-ink-950/50">{{ $readingList->completed_at->utc()->format('M j, Y') }} (UTC)</time>
                            @endif
                        </a>
                    @empty
                        <div class="border border-dashed border-ink-950/20 p-7 text-ink-950/55">No completed literature yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</x-app-shell>
