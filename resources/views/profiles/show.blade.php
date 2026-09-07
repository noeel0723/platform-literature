<x-app-shell :title="$user->name">
    @php
        $isOwner = auth()->id() === $user->id;
        $initials = Str::of($user->name)
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    @endphp

    <section id="profile-overview" data-profile-header class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-12">
            <div class="grid gap-8 lg:grid-cols-[minmax(0,1.15fr)_minmax(460px,.85fr)] lg:items-center">
                <div class="flex min-w-0 flex-col gap-6 sm:flex-row sm:items-center">
                    <div class="grid size-28 shrink-0 place-items-center overflow-hidden rounded-full border-4 border-brand-cream bg-ink-950 font-serif text-3xl font-bold text-brand-cream shadow-[0_10px_28px_rgba(47,58,85,0.10)] sm:size-32">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}'s profile photo" class="size-full object-cover">
                        @else
                            {{ $initials ?: 'LH' }}
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reader profile</p>
                                <h1 class="mt-2 truncate font-serif text-4xl font-bold leading-none text-ink-950 sm:text-5xl">{{ $user->name }}</h1>
                                @if ($isOwner)
                                    <a href="{{ route('profiles.edit') }}" class="mt-3 inline-block w-fit border border-ink-950/20 bg-brand-cream/60 px-4 py-2 text-sm font-bold text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-brand-cream">Edit profile</a>
                                @endif
                                <p class="mt-3 font-semibold text-ink-950/55">&#64;{{ $user->username }}</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-3">
                                @if (! $isOwner && auth()->check())
                                    <form action="{{ $isFollowing ? route('profiles.follow.destroy', $user) : route('profiles.follow.store', $user) }}" method="POST">
                                        @csrf
                                        @if ($isFollowing) @method('DELETE') @endif
                                        <button class="w-fit px-4 py-2.5 text-sm font-bold transition {{ $isFollowing ? 'border border-ink-950/20 bg-brand-cream/60 text-ink-950 hover:border-brand-coral' : 'bg-ink-950 text-brand-cream hover:bg-brand-coral hover:text-brand-cream' }}">{{ $isFollowing ? 'Following' : 'Follow' }}</button>
                                    </form>
                                    <x-report-form target-type="user" :target-id="$user->id" label="Report profile" />
                                @elseif (! $isOwner)
                                    <a href="{{ route('login') }}" class="w-fit bg-ink-950 px-4 py-2.5 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">Log in to follow</a>
                                @endif
                            </div>
                        </div>

                        @if ($user->bio)
                            <p class="mt-4 max-w-2xl leading-7 text-ink-950/70">{{ $user->bio }}</p>
                        @else
                            <p class="mt-4 max-w-2xl italic leading-7 text-ink-950/45">This reader has not added a bio yet.</p>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-ink-950/55">
                            @if ($user->location)
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="size-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                                    {{ $user->location }}
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="size-4" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"></path></svg>
                                Member since {{ $user->created_at->format('F Y') }}
                            </span>
                        </div>
                    </div>
                </div>

                <dl data-profile-stats class="grid grid-cols-2 gap-px overflow-hidden border border-ink-950/10 bg-ink-950/10 text-center sm:grid-cols-4">
                    <div data-profile-stat="literature" class="bg-brand-cream/85 p-4"><dd class="font-serif text-2xl font-bold text-ink-950">{{ $user->completed_literature_count }}</dd><dt class="mt-1 text-[0.65rem] font-bold uppercase tracking-wider text-ink-950/50">Literature</dt></div>
                    <div data-profile-stat="reviews" class="bg-brand-cream/85 p-4"><dd class="font-serif text-2xl font-bold text-ink-950">{{ $user->reviews_count }}</dd><dt class="mt-1 text-[0.65rem] font-bold uppercase tracking-wider text-ink-950/50">Reviews</dt></div>
                    <div data-profile-stat="following" class="bg-brand-cream/85"><a href="{{ route('profiles.following', $user) }}" class="block p-4 transition hover:bg-brand-sky/35"><dd class="font-serif text-2xl font-bold text-ink-950">{{ $user->following_count }}</dd><dt class="mt-1 text-[0.65rem] font-bold uppercase tracking-wider text-ink-950/50">Following</dt></a></div>
                    <div data-profile-stat="followers" class="bg-brand-cream/85"><a href="{{ route('profiles.followers', $user) }}" class="block p-4 transition hover:bg-brand-sky/35"><dd class="font-serif text-2xl font-bold text-ink-950">{{ $user->followers_count }}</dd><dt class="mt-1 text-[0.65rem] font-bold uppercase tracking-wider text-ink-950/50">Followers</dt></a></div>
                </dl>
            </div>

            @error('user') <p class="mt-5 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror

            <nav class="mt-8 flex gap-6 overflow-x-auto border border-ink-950/10 bg-brand-cream/55 px-5 text-sm font-bold text-ink-950/60" aria-label="Profile navigation">
                <a href="#profile-overview" class="border-b-2 border-brand-coral py-4 text-ink-950" aria-current="page">Profile</a>
                @if ($isOwner)
                    <a href="{{ route('diary.index') }}" class="py-4 transition hover:text-brand-coral">Diary</a>
                @endif
                <a href="{{ route('profiles.readlist', $user) }}" class="py-4 transition hover:text-brand-coral">Readlist</a>
                <a href="#favorites" class="py-4 transition hover:text-brand-coral">Favorites</a>
                <a href="#recent-reviews" class="py-4 transition hover:text-brand-coral">Reviews</a>
                <a href="#recently-completed" class="py-4 transition hover:text-brand-coral">Completed</a>
            </nav>
        </div>
    </section>

    <section id="favorites" class="mx-auto grid max-w-7xl scroll-mt-24 gap-12 px-5 py-14 sm:px-8 lg:grid-cols-[minmax(0,1fr)_300px] lg:px-10 lg:py-20">
        <div class="min-w-0">
            <div>
            <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Personal shelf</p><h2 class="mt-2 font-serif text-3xl font-bold text-ink-950">Favorite Literature</h2></div>
                <span class="text-sm text-ink-950/50">Up to four</span>
            </div>

            <div data-favorite-literature-grid class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                @forelse ($user->favoriteLiteratures as $literature)
                    <article class="group min-w-0">
                        <a href="{{ route('literatures.show', $literature) }}" class="block">
                            <div class="relative aspect-[2/3] overflow-hidden border border-ink-950/15 bg-brand-sky/20 shadow-[0_8px_24px_rgba(47,58,85,0.08)] transition duration-200 group-hover:-translate-y-1 group-hover:border-brand-coral">
                                @if ($literature->cover_url)
                                    <img src="{{ $literature->cover_url }}" alt="Cover of {{ $literature->original_title ?? $literature->title }}" class="size-full object-cover">
                                @else
                                    <div class="grid size-full place-items-center p-4 text-center font-serif text-3xl font-bold text-ink-950">{{ Str::upper(Str::substr($literature->title, 0, 2)) }}</div>
                                @endif
                                <span class="absolute left-3 top-3 grid size-8 place-items-center bg-ink-950 text-xs font-bold text-brand-cream">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            </div>
                            <h3 class="mt-3 truncate font-bold text-ink-950 transition group-hover:text-brand-coral">{{ $literature->original_title ?? $literature->title }}</h3>
                            <p class="mt-1 truncate text-sm text-ink-950/55">{{ $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable' }}</p>
                        </a>
                    </article>
                @empty
                    <div class="col-span-full border border-dashed border-ink-950/20 p-7 text-ink-950/55">No favorite literature has been selected.</div>
                @endforelse
            </div>
        </div>

            <div class="mt-12">
            <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Creative voices</p><h2 class="mt-2 font-serif text-3xl font-bold text-ink-950">Favorite Authors</h2></div>
                <span class="text-sm text-ink-950/50">Up to four</span>
            </div>

            <ol data-favorite-author-grid class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                @forelse ($user->favoriteAuthors as $author)
                    @php
                        $authorInitials = Str::of($author->name)
                            ->squish()
                            ->explode(' ')
                            ->filter()
                            ->take(2)
                            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
                            ->implode('');
                    @endphp
                    <li class="group min-w-0">
                        <article>
                            <div class="relative aspect-[4/5] overflow-hidden border border-ink-950/15 bg-brand-sky/30 shadow-[0_8px_24px_rgba(47,58,85,0.08)] transition duration-200 group-hover:-translate-y-1 group-hover:border-brand-coral">
                                @if ($author->image_url)
                                    <img src="{{ $author->image_url }}" alt="Portrait of {{ $author->name }}" class="size-full object-cover">
                                @else
                                    <div class="grid size-full place-items-center bg-linear-to-br from-brand-sky/45 to-brand-coral/35 font-serif text-5xl font-bold text-ink-950">{{ $authorInitials ?: 'A' }}</div>
                                @endif
                                <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-ink-950 via-ink-950/80 to-transparent p-4 pt-14 text-brand-cream">
                                    <p class="truncate font-serif text-xl font-bold">{{ $author->name }}</p>
                                    <p class="mt-1 text-xs font-bold uppercase tracking-wider text-brand-cream/65">Favorite author {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</p>
                                </div>
                            </div>
                        </article>
                    </li>
                @empty
                    <li class="col-span-full border border-dashed border-ink-950/20 p-7 text-ink-950/55">No favorite authors have been selected.</li>
                @endforelse
            </ol>
            </div>
        </div>

        <aside class="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-8" aria-labelledby="profile-readlist-heading" data-profile-readlist-preview>
            <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Saved shelf</p>
                    <h2 id="profile-readlist-heading" class="mt-2 font-serif text-3xl font-bold text-ink-950">Readlist</h2>
                </div>
                <span class="text-sm text-ink-950/50">{{ $user->readlist_count }}</span>
            </div>

            @if ($readlistPreview->isEmpty())
                <div class="mt-6 border border-dashed border-ink-950/20 bg-white/25 p-6 text-sm leading-6 text-ink-950/55">No literature has been saved to this Readlist.</div>
            @else
                <div class="mt-6 grid grid-cols-4 gap-1.5 overflow-hidden">
                    @foreach ($readlistPreview as $item)
                        <a href="{{ route('literatures.show', $item->literature) }}" class="group relative block aspect-[2/3] overflow-hidden border border-ink-950/10 bg-brand-sky/20" title="{{ $item->literature->original_title ?? $item->literature->title }}">
                            @if ($item->literature->cover_url)
                                <img src="{{ $item->literature->cover_url }}" alt="Cover of {{ $item->literature->original_title ?? $item->literature->title }}" class="size-full object-cover transition duration-200 group-hover:scale-105" loading="lazy">
                            @else
                                <span class="grid size-full place-items-center px-1 text-center font-serif text-sm font-bold text-ink-950">{{ Str::upper(Str::substr($item->literature->original_title ?? $item->literature->title, 0, 2)) }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('profiles.readlist', $user) }}" class="mt-5 flex items-center justify-between border border-ink-950/15 bg-ink-950 px-4 py-3 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">
                <span>View full Readlist</span>
                <span aria-hidden="true">→</span>
            </a>

            @if ($isOwner)
                <a href="{{ route('diary.index') }}" class="mt-3 flex items-center justify-between border border-ink-950/15 bg-brand-cream/70 px-4 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral">
                    <span>Open activity Diary</span>
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </aside>
    </section>

    <section class="border-t border-ink-950/10 bg-white/20">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 py-14 sm:px-8 lg:grid-cols-2 lg:px-10 lg:py-20">
            <div id="recent-reviews" class="scroll-mt-24">
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

            <div id="recently-completed" class="scroll-mt-24">
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
