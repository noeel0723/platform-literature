<x-app-shell title="Home">
    <section class="border-b border-ink-950/10 bg-white/20">
        <div class="mx-auto max-w-5xl px-5 py-12 sm:px-8 sm:py-16 lg:px-10">
            <p class="text-xs font-bold uppercase tracking-[0.28em] text-brand-coral">Home</p>
            <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-serif text-5xl font-bold tracking-tight text-ink-950 sm:text-6xl">Activity Feed</h1>
                    <p class="mt-3 max-w-2xl text-base leading-7 text-ink-950/65">Follow what the Literahaven community is reading, finishing, rating, and reviewing.</p>
                </div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-ink-950/50">{{ $feedLabel }}</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="activity-feed-heading">
        <div class="mx-auto max-w-5xl px-5 py-10 sm:px-8 lg:px-10">
            <h2 id="activity-feed-heading" class="sr-only">Recent activity</h2>

            @if ($activities->isEmpty())
                <div class="border border-ink-950/15 bg-white/25 px-6 py-16 text-center sm:px-10">
                    <div class="mx-auto grid size-14 place-items-center rounded-full bg-ink-950 text-2xl text-brand-cream" aria-hidden="true">✦</div>
                    <h3 class="mt-5 font-serif text-3xl font-bold text-ink-950">Your feed is quiet for now.</h3>
                    <p class="mx-auto mt-3 max-w-xl leading-7 text-ink-950/60">
                        @auth
                            Your own reading updates and new activity from readers you follow will appear here.
                        @else
                            Public reading updates will appear here as the community starts sharing activity.
                        @endauth
                    </p>
                    <a href="{{ route('literatures.index') }}" class="mt-7 inline-flex items-center bg-ink-950 px-5 py-3 text-sm font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Browse the catalog</a>
                </div>
            @else
                <div class="space-y-5">
                    @foreach ($activities as $activity)
                        @php
                            $displayTitle = $activity->literature->original_title ?? $activity->literature->title;
                            $authorNames = $activity->literature->authors->pluck('name')->implode(' & ');
                            $rating = data_get($activity->metadata, 'rating');
                            $reviewExcerpt = data_get($activity->metadata, 'review_excerpt');
                            $containsSpoiler = (bool) data_get($activity->metadata, 'contains_spoiler', false);
                            $isReread = (bool) data_get($activity->metadata, 'reread', false);
                            $action = match ($activity->type) {
                                \App\Models\Activity::TYPE_STARTED_READING => $isReread ? 'started reading again' : 'started reading',
                                \App\Models\Activity::TYPE_COMPLETED => 'finished reading',
                                \App\Models\Activity::TYPE_RATED => 'rated',
                                \App\Models\Activity::TYPE_REVIEWED => 'reviewed',
                                default => 'updated',
                            };
                        @endphp

                        <article class="grid gap-5 border border-ink-950/15 bg-white/30 p-5 shadow-[6px_6px_0_rgba(5,22,46,0.07)] sm:grid-cols-[5rem_minmax(0,1fr)] sm:p-6">
                            <a href="{{ route('literatures.show', $activity->literature) }}" class="block w-20 shrink-0 overflow-hidden border border-ink-950/15 bg-brand-sky/30" aria-label="View {{ $displayTitle }}">
                                @if ($activity->literature->cover_url)
                                    <img src="{{ $activity->literature->cover_url }}" alt="Cover of {{ $displayTitle }}" class="aspect-[2/3] h-full w-full object-cover" loading="lazy">
                                @else
                                    <span class="grid aspect-[2/3] place-items-center px-2 text-center font-serif text-lg font-bold text-ink-950">{{ Str::upper(Str::substr($displayTitle, 0, 2)) }}</span>
                                @endif
                            </a>

                            <div class="min-w-0">
                                <div class="flex items-start gap-3">
                                    <a href="{{ route('profiles.show', $activity->user) }}" class="mt-0.5 grid size-10 shrink-0 place-items-center overflow-hidden rounded-full border border-ink-950/15 bg-brand-sky/45 font-bold text-ink-950" aria-label="View {{ $activity->user->name }}'s profile">
                                        @if ($activity->user->avatarUrl())
                                            <img src="{{ $activity->user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
                                        @else
                                            {{ Str::upper(Str::substr($activity->user->name, 0, 1)) }}
                                        @endif
                                    </a>

                                    <div class="min-w-0 flex-1">
                                        <p class="leading-6 text-ink-950/70">
                                            <a href="{{ route('profiles.show', $activity->user) }}" class="font-bold text-ink-950 underline decoration-transparent underline-offset-4 transition hover:decoration-brand-coral">{{ $activity->user->name }}</a>
                                            <span>{{ $action }}</span>
                                        </p>
                                        <a href="{{ route('literatures.show', $activity->literature) }}" class="mt-1 block truncate font-serif text-2xl font-bold text-ink-950 transition hover:text-brand-coral">{{ $displayTitle }}</a>
                                        @if ($authorNames !== '')
                                            <p class="mt-1 truncate text-sm text-ink-950/55">by {{ $authorNames }}</p>
                                        @endif
                                    </div>

                                    <time datetime="{{ $activity->occurred_at->utc()->toIso8601String() }}" data-local-datetime class="hidden shrink-0 text-xs font-bold uppercase tracking-wider text-ink-950/45 md:block">{{ $activity->occurred_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                                </div>

                                @if ($rating !== null)
                                    <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-ink-950/10 pt-4">
                                        <span class="text-xl tracking-[0.08em] text-brand-coral" aria-hidden="true">★★★★★</span>
                                        <span class="text-sm font-bold text-ink-950">{{ number_format((float) $rating, 1) }} / 5</span>
                                        <span class="sr-only">Rated {{ number_format((float) $rating, 1) }} out of 5</span>
                                    </div>
                                @endif

                                @if ($reviewExcerpt)
                                    @if ($containsSpoiler)
                                        <details class="mt-4 border-l-2 border-brand-coral pl-4">
                                            <summary class="cursor-pointer text-sm font-bold text-ink-950">Review contains spoilers — reveal</summary>
                                            <p class="mt-3 leading-7 text-ink-950/70">{{ $reviewExcerpt }}</p>
                                        </details>
                                    @else
                                        <p class="mt-4 border-l-2 border-brand-coral pl-4 leading-7 text-ink-950/70">{{ $reviewExcerpt }}</p>
                                    @endif
                                @endif

                                <time datetime="{{ $activity->occurred_at->utc()->toIso8601String() }}" data-local-datetime class="mt-4 block text-xs font-bold uppercase tracking-wider text-ink-950/45 md:hidden">{{ $activity->occurred_at->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($activities->hasPages())
                    <div class="mt-10">{{ $activities->links() }}</div>
                @endif
            @endif
        </div>
    </section>
</x-app-shell>
