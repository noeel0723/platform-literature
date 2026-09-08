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

    <x-profile-subnav :$user current="profile" />

    <section id="profile-overview" data-profile-header data-profile-compact-header class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-7 sm:px-8 lg:px-10 lg:py-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:gap-10">
                <div class="flex min-w-0 flex-wrap items-start gap-4 sm:items-center sm:gap-5">
                    <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-full border-2 border-brand-cream bg-ink-950 font-serif text-xl font-bold text-brand-cream shadow-[0_7px_20px_rgba(16,47,98,0.12)] sm:size-24">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}'s profile photo" class="size-full object-cover">
                        @else
                            {{ $initials ?: 'LH' }}
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-[0.62rem] font-bold uppercase tracking-[0.18em] text-brand-coral">Reader profile</p>
                        <div class="mt-1.5 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
                            <h1 class="max-w-full truncate font-serif text-3xl font-bold leading-none text-ink-950 sm:text-4xl">{{ $user->name }}</h1>
                            @if ($isOwner)
                                <a href="{{ route('profiles.edit') }}" class="rounded-full border border-ink-950/15 bg-brand-cream/65 px-3 py-1.5 text-[0.65rem] font-bold uppercase tracking-wider text-ink-950 transition hover:border-brand-coral hover:bg-brand-coral hover:text-white">Edit profile</a>
                            @endif
                            <p class="w-full truncate text-sm font-semibold text-ink-950/50">&#64;{{ $user->username }}</p>
                        </div>

                        @if ($user->bio)
                            <p class="mt-2.5 line-clamp-2 max-w-2xl text-sm leading-6 text-ink-950/65">{{ $user->bio }}</p>
                        @else
                            <p class="mt-2.5 text-sm italic leading-6 text-ink-950/45">This reader has not added a bio yet.</p>
                        @endif

                        <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-ink-950/50">
                            @if ($user->location)
                                <span class="inline-flex items-center gap-1.5">
                                    <svg class="size-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.5"></circle></svg>
                                    {{ $user->location }}
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="size-3.5" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z"></path></svg>
                                Member since {{ $user->created_at->format('F Y') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex w-full shrink-0 flex-wrap gap-2 pl-24 sm:w-auto sm:pl-0">
                        @if (! $isOwner && auth()->check())
                            <form action="{{ $isFollowing ? route('profiles.follow.destroy', $user) : route('profiles.follow.store', $user) }}" method="POST">
                                @csrf
                                @if ($isFollowing) @method('DELETE') @endif
                                <button class="rounded-full px-4 py-2 text-xs font-bold transition {{ $isFollowing ? 'border border-ink-950/15 bg-brand-cream/60 text-ink-950 hover:border-brand-coral' : 'bg-ink-950 text-brand-cream hover:bg-brand-coral' }}">{{ $isFollowing ? 'Following' : 'Follow' }}</button>
                            </form>
                            <x-report-form target-type="user" :target-id="$user->id" label="Report profile" />
                        @elseif (! $isOwner)
                            <a href="{{ route('login') }}" class="rounded-full bg-ink-950 px-4 py-2 text-xs font-bold text-brand-cream transition hover:bg-brand-coral">Log in to follow</a>
                        @endif
                    </div>
                </div>

                <dl data-profile-stats class="grid min-w-0 grid-cols-4 divide-x divide-ink-950/10 border-y border-ink-950/10 text-center lg:min-w-[410px] lg:border-y-0">
                    <div data-profile-stat="literature" class="px-2 py-3 sm:px-4"><dd class="font-serif text-xl font-bold leading-none text-ink-950 sm:text-2xl">{{ $user->completed_literature_count }}</dd><dt class="mt-1.5 text-[0.55rem] font-bold uppercase tracking-[0.12em] text-ink-950/45 sm:text-[0.62rem]">Literature</dt></div>
                    <div data-profile-stat="reviews" class="px-2 py-3 sm:px-4"><dd class="font-serif text-xl font-bold leading-none text-ink-950 sm:text-2xl">{{ $user->reviews_count }}</dd><dt class="mt-1.5 text-[0.55rem] font-bold uppercase tracking-[0.12em] text-ink-950/45 sm:text-[0.62rem]">Reviews</dt></div>
                    <div data-profile-stat="following"><a href="{{ route('profiles.following', $user) }}" class="block px-2 py-3 transition hover:bg-brand-sky/20 sm:px-4"><dd class="font-serif text-xl font-bold leading-none text-ink-950 sm:text-2xl">{{ $user->following_count }}</dd><dt class="mt-1.5 text-[0.55rem] font-bold uppercase tracking-[0.12em] text-ink-950/45 sm:text-[0.62rem]">Following</dt></a></div>
                    <div data-profile-stat="followers"><a href="{{ route('profiles.followers', $user) }}" class="block px-2 py-3 transition hover:bg-brand-sky/20 sm:px-4"><dd class="font-serif text-xl font-bold leading-none text-ink-950 sm:text-2xl">{{ $user->followers_count }}</dd><dt class="mt-1.5 text-[0.55rem] font-bold uppercase tracking-[0.12em] text-ink-950/45 sm:text-[0.62rem]">Followers</dt></a></div>
                </dl>
            </div>

            @error('user') <p class="mt-4 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
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
                        <a href="{{ route('authors.show', $author) }}" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral">
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
                        </a>
                    </li>
                @empty
                    <li class="col-span-full border border-dashed border-ink-950/20 p-7 text-ink-950/55">No favorite authors have been selected.</li>
                @endforelse
            </ol>
            </div>

            <div id="recently-completed" class="mt-12 scroll-mt-24">
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3"><h2 class="font-serif text-3xl font-bold text-ink-950">Recently completed</h2><span class="text-sm text-ink-950/50">Latest four</span></div>
                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @forelse ($recentCompletions as $readingList)
                        <a href="{{ route('literatures.show', $readingList->literature) }}" class="group min-w-0" data-profile-completed-card>
                            <span class="block aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25 shadow-[0_7px_18px_rgba(47,58,85,0.07)] transition group-hover:-translate-y-1 group-hover:border-brand-coral">
                                @if ($readingList->literature->cover_url)
                                    <img src="{{ $readingList->literature->cover_url }}" alt="Cover of {{ $readingList->literature->original_title ?? $readingList->literature->title }}" class="size-full object-cover" loading="lazy">
                                @else
                                    <span class="grid size-full place-items-center font-serif text-2xl font-bold text-ink-950">{{ Str::upper(Str::substr($readingList->literature->original_title ?? $readingList->literature->title, 0, 2)) }}</span>
                                @endif
                            </span>
                            <span class="mt-2 block truncate text-sm font-bold text-ink-950 group-hover:text-brand-coral">{{ $readingList->literature->original_title ?? $readingList->literature->title }}</span>
                            @if ($readingList->literature->reviews->first())
                                <x-star-rating :rating="$readingList->literature->reviews->first()->rating" size="sm" class="mt-1" />
                            @else
                                <span class="mt-1 block text-xs font-bold uppercase tracking-wider text-ink-950/40">Completed</span>
                            @endif
                            @if ($readingList->completed_at)
                                <time datetime="{{ $readingList->completed_at->utc()->toIso8601String() }}" data-local-datetime class="mt-1 block truncate text-xs text-ink-950/40">{{ $readingList->completed_at->utc()->format('M j, Y') }} (UTC)</time>
                            @endif
                        </a>
                    @empty
                        <div class="col-span-full border border-dashed border-ink-950/20 p-7 text-ink-950/55">No completed literature yet.</div>
                    @endforelse
                </div>
            </div>

            <div id="recent-reviews" class="mt-12 scroll-mt-24">
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3"><h2 class="font-serif text-3xl font-bold text-ink-950">Recent reviews</h2><a href="{{ route('profiles.reviews', $user) }}" class="text-xs font-bold uppercase tracking-wider text-brand-coral hover:underline">More</a></div>
                <div>
                    @forelse ($recentReviews as $review)
                        <article class="grid grid-cols-[72px_minmax(0,1fr)] gap-4 border-b border-ink-950/10 py-5 sm:grid-cols-[88px_minmax(0,1fr)] sm:gap-6" data-profile-recent-review>
                            <a href="{{ route('literatures.show', $review->literature) }}#review-{{ $review->id }}" class="aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25">
                                @if ($review->literature->cover_url)
                                    <img src="{{ $review->literature->cover_url }}" alt="Cover of {{ $review->literature->original_title ?? $review->literature->title }}" class="size-full object-cover" loading="lazy">
                                @else
                                    <span class="grid size-full place-items-center font-serif text-lg font-bold text-ink-950">{{ Str::upper(Str::substr($review->literature->original_title ?? $review->literature->title, 0, 2)) }}</span>
                                @endif
                            </a>
                            <div class="min-w-0">
                                <h3 class="font-serif text-2xl font-bold text-ink-950"><a href="{{ route('literatures.show', $review->literature) }}#review-{{ $review->id }}" class="hover:text-brand-coral">{{ $review->literature->original_title ?? $review->literature->title }}</a> @if ($review->literature->publication_year)<span class="font-sans text-sm font-normal text-ink-950/45">{{ $review->literature->publication_year }}</span>@endif</h3>
                                <div class="mt-2 flex flex-wrap items-center gap-3"><x-star-rating :rating="$review->rating" size="sm" /><time datetime="{{ $review->updated_at->utc()->toIso8601String() }}" data-local-datetime class="text-xs text-ink-950/45">{{ $review->updated_at->utc()->format('M j, Y') }} (UTC)</time></div>
                                @if ($review->body)
                                    <p class="mt-3 line-clamp-3 font-serif text-lg leading-7 text-ink-950/65">{{ $review->contains_spoiler ? 'This review contains spoilers.' : $review->body }}</p>
                                @else
                                    <p class="mt-3 text-sm italic text-ink-950/45">Rating only.</p>
                                @endif
                                <p class="mt-3 text-xs text-ink-950/40">{{ $review->likes_count }} {{ Str::plural('like', $review->likes_count) }}</p>
                            </div>
                        </article>
                    @empty
                        <div class="border border-dashed border-ink-950/20 p-7 text-ink-950/55">No reviews yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="min-w-0 lg:sticky lg:top-24 lg:self-start lg:border-l lg:border-ink-950/10 lg:pl-8" aria-labelledby="profile-readlist-heading" data-profile-readlist-preview>
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

            <section class="mt-9" aria-labelledby="profile-diary-heading" data-profile-diary-preview>
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                    <h2 id="profile-diary-heading" class="font-serif text-2xl font-bold text-ink-950">Diary</h2>
                    <span class="text-sm text-ink-950/45">{{ $user->reviews_count }}</span>
                </div>
                <ol class="mt-3 grid gap-2">
                    @forelse ($recentReviews as $review)
                        <li class="flex min-w-0 items-center justify-between gap-3 text-sm">
                            <a href="{{ route('literatures.show', $review->literature) }}" class="truncate text-ink-950/65 hover:text-brand-coral">{{ $review->literature->original_title ?? $review->literature->title }}</a>
                            <span class="shrink-0 font-bold text-brand-coral">{{ number_format($review->rating, 1) }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-ink-950/45">No rated literature yet.</li>
                    @endforelse
                </ol>
            </section>

            @php
                $ratingMaximum = max(1, (int) ($ratingDistribution->max() ?? 0));
            @endphp
            <section class="mt-9" aria-labelledby="profile-ratings-heading" data-profile-ratings>
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                    <h2 id="profile-ratings-heading" class="font-serif text-2xl font-bold text-ink-950">Ratings</h2>
                    <span class="text-sm text-ink-950/45">{{ $user->reviews_count }}</span>
                </div>
                <div class="mt-5 flex h-20 items-end gap-1.5" aria-label="Rating distribution from half a star to five stars">
                    @foreach (range(1, 10) as $slot)
                        @php
                            $ratingValue = number_format($slot / 2, 1, '.', '');
                            $ratingCount = (int) ($ratingDistribution[$ratingValue] ?? 0);
                            $height = $ratingCount === 0 ? 6 : max(14, (int) round(($ratingCount / $ratingMaximum) * 100));
                        @endphp
                        <span class="flex h-full flex-1 items-end" title="{{ $ratingValue }} stars: {{ $ratingCount }}">
                            <span class="block w-full bg-brand-coral/75" style="height: {{ $height }}%"></span>
                        </span>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-[0.65rem] font-bold text-ink-950/40"><span>½</span><span>5 ★</span></div>
            </section>

            <section class="mt-9" aria-labelledby="profile-activity-heading" data-profile-activity-preview>
                <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                    <h2 id="profile-activity-heading" class="font-serif text-2xl font-bold text-ink-950">Activity</h2>
                    @if ($isOwner)<a href="{{ route('activity.index') }}" class="text-xs font-bold uppercase tracking-wider text-brand-coral hover:underline">All</a>@endif
                </div>
                <ol class="mt-4 border-l border-ink-950/20 pl-4">
                    @forelse ($recentActivities as $activity)
                        @php
                            $activityTitle = $activity->literature->original_title ?? $activity->literature->title;
                            $activityLabel = match ($activity->type) {
                                \App\Models\Activity::TYPE_ADDED_TO_READLIST => 'Added to Readlist',
                                \App\Models\Activity::TYPE_STARTED_READING => 'Started',
                                \App\Models\Activity::TYPE_COMPLETED => 'Completed',
                                \App\Models\Activity::TYPE_RATED => 'Rated',
                                \App\Models\Activity::TYPE_REVIEWED => 'Reviewed',
                                \App\Models\Activity::TYPE_DISCUSSION => 'Discussed',
                                \App\Models\Activity::TYPE_COMMENT => 'Commented on',
                                default => 'Updated',
                            };
                        @endphp
                        <li class="relative pb-4 text-sm last:pb-0 before:absolute before:-left-[1.19rem] before:top-1.5 before:size-2 before:rounded-full before:bg-brand-coral">
                            <p class="leading-5 text-ink-950/55">{{ $activityLabel }} <a href="{{ route('literatures.show', $activity->literature) }}" class="font-semibold text-ink-950 hover:text-brand-coral">{{ $activityTitle }}</a></p>
                            <time datetime="{{ $activity->occurred_at->utc()->toIso8601String() }}" data-local-datetime class="mt-1 block text-xs text-ink-950/35">{{ $activity->occurred_at->utc()->format('M j, Y') }} (UTC)</time>
                        </li>
                    @empty
                        <li class="text-sm text-ink-950/45">No activity yet.</li>
                    @endforelse
                </ol>
            </section>
        </aside>
    </section>

</x-app-shell>
