<x-app-shell title="Home">
    <section>
        <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-14 lg:px-10">
            <div class="border-b border-ink-950/15 pb-8">
                <h1 class="mt-3 text-center font-serif text-1xl font-bold tracking-tight text-ink-950 sm:text-2xl">
                    @auth
                        Welcome back, {{ $viewer->name }}. Here is what your friends have been reading..
                    @else
                        Welcome to Literahaven.
                    @endauth
                </h1>
                <p class="mt-3 max-w-2xl leading-7 text-ink-950/60">
                    @auth
                        
                    @else
                        Sign in and follow other readers to build your personal activity feed.
                    @endauth
                </p>
            </div>

            <section class="mt-9" aria-labelledby="friends-activity-heading">
                <div class="flex items-end justify-between gap-4 border-b border-ink-950/15 pb-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Activity feed</p>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-[0.16em] text-ink-950/45">Latest 6</span>
                </div>

                @if ($activities->isEmpty())
                    <div class="mt-5 border border-dashed border-ink-950/20 bg-white/25 px-6 py-12 text-center">
                        <p class="font-serif text-2xl font-bold text-ink-950">No new activity from friends yet.</p>
                        <p class="mx-auto mt-2 max-w-xl leading-7 text-ink-950/60">
                            @auth
                                Follow readers from their profile. Their completed reads, ratings, and reviews will appear here.
                            @else
                                Log in to see completed reads, ratings, and reviews from readers you follow.
                            @endauth
                        </p>
                        <a href="{{ auth()->check() ? route('literatures.index') : route('login') }}" class="mt-6 inline-flex bg-ink-950 px-5 py-3 text-xs font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral hover:text-brand-cream">
                            {{ auth()->check() ? 'Explore literature' : 'Log in' }}
                        </a>
                    </div>
                @else
                    <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3 lg:grid-cols-6">
                        @foreach ($activities as $activity)
                            @php
                                $displayTitle = $activity->literature->original_title ?? $activity->literature->title;
                                $rating = data_get($activity->metadata, 'rating');
                                $action = match ($activity->type) {
                                    \App\Models\Activity::TYPE_COMPLETED => 'Completed',
                                    \App\Models\Activity::TYPE_RATED => 'Rated',
                                    \App\Models\Activity::TYPE_REVIEWED => 'Reviewed',
                                    default => 'Updated',
                                };
                            @endphp

                            <article class="min-w-0" data-friend-activity>
                                <a href="{{ route('literatures.show', $activity->literature) }}" class="group relative block overflow-hidden border border-ink-950/15 bg-brand-sky/25 shadow-[0_8px_24px_rgba(47,58,85,0.08)]">
                                    @if ($activity->literature->cover_url)
                                        <img src="{{ $activity->literature->cover_url }}" alt="Cover of {{ $displayTitle }}" class="aspect-[2/3] w-full object-cover transition duration-300 group-hover:scale-[1.025]" loading="lazy">
                                    @else
                                        <span class="grid aspect-[2/3] place-items-center px-3 text-center font-serif text-3xl font-bold text-ink-950">{{ Str::upper(Str::substr($displayTitle, 0, 2)) }}</span>
                                    @endif

                                    <span class="absolute left-2 top-2 bg-ink-950/90 px-2 py-1 text-[0.65rem] font-bold uppercase tracking-wider text-brand-cream">{{ $action }}</span>
                                    <span class="absolute inset-x-0 bottom-0 flex items-center gap-2 bg-gradient-to-t from-ink-950 via-ink-950/90 to-transparent px-3 pb-3 pt-10 text-brand-cream">
                                        <span class="grid size-7 shrink-0 place-items-center overflow-hidden rounded-full border border-brand-cream/60 bg-brand-sky font-bold text-ink-950">
                                            @if ($activity->user->avatarUrl())
                                                <img src="{{ $activity->user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
                                            @else
                                                {{ Str::upper(Str::substr($activity->user->name, 0, 1)) }}
                                            @endif
                                        </span>
                                        <span class="truncate text-xs font-bold">{{ $activity->user->name }}</span>
                                    </span>
                                </a>

                                <a href="{{ route('literatures.show', $activity->literature) }}" class="mt-3 block truncate font-bold text-ink-950 transition hover:text-brand-coral">{{ $displayTitle }}</a>
                                <div class="mt-1 flex min-w-0 items-center justify-between gap-2 text-xs text-ink-950/55">
                                    @if ($rating !== null)
                                        <span class="shrink-0 font-bold text-brand-coral">★ {{ number_format((float) $rating, 1) }}</span>
                                    @else
                                        <span class="truncate">{{ $action }}</span>
                                    @endif
                                    <time datetime="{{ $activity->occurred_at->utc()->toIso8601String() }}" data-local-datetime class="truncate text-right">{{ $activity->occurred_at->utc()->format('M j, Y') }}</time>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="mt-14" aria-labelledby="popular-friends-heading">
                <div class="flex items-end justify-between gap-4 border-b border-ink-950/15 pb-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Shared discoveries</p>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-[0.16em] text-ink-950/45">Most read</span>
                </div>

                @if ($popularLiteratures->isEmpty())
                    <div class="mt-5 border border-dashed border-ink-950/20 bg-white/25 px-6 py-10 text-center text-ink-950/60">
                        Literature your friends are reading or have completed will appear here.
                    </div>
                @else
                    <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-7 sm:grid-cols-3 lg:grid-cols-6">
                        @foreach ($popularLiteratures as $literature)
                            @php
                                $displayTitle = $literature->original_title ?? $literature->title;
                                $friends = $literature->readingLists;
                            @endphp

                            <article class="min-w-0" data-popular-with-friends>
                                <a href="{{ route('literatures.show', $literature) }}" class="group relative block overflow-hidden border border-ink-950/15 bg-brand-sky/25 shadow-[0_8px_24px_rgba(47,58,85,0.08)]">
                                    @if ($literature->cover_url)
                                        <img src="{{ $literature->cover_url }}" alt="Cover of {{ $displayTitle }}" class="aspect-[2/3] w-full object-cover transition duration-300 group-hover:scale-[1.025]" loading="lazy">
                                    @else
                                        <span class="grid aspect-[2/3] place-items-center px-3 text-center font-serif text-3xl font-bold text-ink-950">{{ Str::upper(Str::substr($displayTitle, 0, 2)) }}</span>
                                    @endif

                                    <span class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-2 bg-gradient-to-t from-ink-950 via-ink-950/90 to-transparent px-3 pb-3 pt-10 text-brand-cream">
                                        <span class="flex -space-x-2" aria-hidden="true">
                                            @foreach ($friends->take(3) as $readingList)
                                                <span class="grid size-7 place-items-center overflow-hidden rounded-full border-2 border-ink-950 bg-brand-sky text-[0.65rem] font-bold text-ink-950">
                                                    @if ($readingList->user->avatarUrl())
                                                        <img src="{{ $readingList->user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
                                                    @else
                                                        {{ Str::upper(Str::substr($readingList->user->name, 0, 1)) }}
                                                    @endif
                                                </span>
                                            @endforeach
                                        </span>
                                        <span class="text-xs font-bold">{{ $literature->friends_count }}</span>
                                    </span>
                                </a>

                                <a href="{{ route('literatures.show', $literature) }}" class="mt-3 block truncate font-bold text-ink-950 transition hover:text-brand-coral">{{ $displayTitle }}</a>
                                <p class="mt-1 text-xs text-ink-950/55">Read by {{ $literature->friends_count }} {{ Str::plural('friend', $literature->friends_count) }}</p>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </section>
</x-app-shell>
