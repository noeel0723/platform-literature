<x-app-shell title="Activity">
    <x-profile-subnav :user="$viewer" current="activity" />

    <section class="mx-auto grid max-w-7xl gap-7 px-5 py-7 sm:px-8 sm:py-9 lg:grid-cols-[minmax(0,1fr)_240px] lg:px-10 lg:py-10">
        <main class="min-w-0" aria-labelledby="activity-heading">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-ink-950/20 pb-2.5">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Social reading stream</p>
                    <h1 id="activity-heading" class="mt-0.5 font-serif text-2xl font-bold text-ink-950">Latest Activity</h1>
                </div>
                <nav class="flex gap-3 text-[11px] font-bold uppercase tracking-wider sm:gap-4" aria-label="Activity scopes">
                    @foreach (['all' => 'You + friends', 'you' => 'You', 'friends' => 'Friends'] as $value => $label)
                        <a href="{{ route('activity.index', ['scope' => $value]) }}" class="border-b-2 py-0.5 {{ $scope === $value ? 'border-brand-coral text-ink-950' : 'border-transparent text-ink-950/45 hover:text-ink-950' }}" @if ($scope === $value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            <div data-activity-stream data-activity-density="compact">
                @forelse ($activities as $activity)
                    @php
                        $title = $activity->literature->original_title ?? $activity->literature->title;
                        $rating = data_get($activity->metadata, 'rating');
                        $containsSpoiler = (bool) data_get($activity->metadata, 'contains_spoiler', false);
                        $excerpt = data_get($activity->metadata, 'review_excerpt') ?? data_get($activity->metadata, 'excerpt');
                        $action = match ($activity->type) {
                            \App\Models\Activity::TYPE_ADDED_TO_READLIST => 'added to Readlist',
                            \App\Models\Activity::TYPE_STARTED_READING => 'started reading',
                            \App\Models\Activity::TYPE_COMPLETED => 'completed',
                            \App\Models\Activity::TYPE_RATED => 'rated',
                            \App\Models\Activity::TYPE_REVIEWED => 'reviewed',
                            \App\Models\Activity::TYPE_DISCUSSION => 'started a discussion about',
                            \App\Models\Activity::TYPE_COMMENT => data_get($activity->metadata, 'is_reply') ? 'replied to a discussion about' : 'commented on a discussion about',
                            default => 'updated',
                        };
                        $isExpanded = in_array($activity->type, [\App\Models\Activity::TYPE_RATED, \App\Models\Activity::TYPE_REVIEWED, \App\Models\Activity::TYPE_COMPLETED], true);
                    @endphp

                    <article class="border-b border-ink-950/10 py-3.5" data-activity-item data-activity-type="{{ $activity->type }}">
                        <div class="flex items-start gap-2.5">
                            <a href="{{ route('profiles.show', $activity->user) }}" class="grid size-7 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-[10px] font-bold text-brand-cream sm:size-8">
                                @if ($activity->user->avatarUrl())
                                    <img src="{{ $activity->user->avatarUrl() }}" alt="" class="size-full object-cover">
                                @else
                                    {{ Str::upper(Str::substr($activity->user->name, 0, 1)) }}
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <div class="flex min-w-0 items-start justify-between gap-3">
                                    <p class="min-w-0 text-[13px] leading-5 text-ink-950/60 sm:text-sm"><a href="{{ route('profiles.show', $activity->user) }}" class="font-bold text-ink-950 hover:text-brand-coral">{{ $activity->user->name }}</a> {{ $action }} @unless ($isExpanded)<a href="{{ route('literatures.show', $activity->literature) }}" class="font-bold text-ink-950 hover:text-brand-coral">{{ $title }}</a>@endunless</p>
                                    <time datetime="{{ $activity->occurred_at->utc()->toIso8601String() }}" data-local-datetime class="shrink-0 pt-0.5 text-[11px] leading-4 text-ink-950/35">{{ $activity->occurred_at->utc()->format('M j, Y') }} (UTC)</time>
                                </div>

                                @if ($isExpanded)
                                    <div class="mt-2.5 grid grid-cols-[52px_minmax(0,1fr)] gap-3 sm:grid-cols-[60px_minmax(0,1fr)]">
                                        <a href="{{ route('literatures.show', $activity->literature) }}" class="aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25">
                                            @if ($activity->literature->cover_url)
                                                <img src="{{ $activity->literature->cover_url }}" alt="Cover of {{ $title }}" class="size-full object-cover" loading="lazy">
                                            @else
                                                <span class="grid size-full place-items-center font-serif text-xl font-bold text-ink-950">{{ Str::upper(Str::substr($title, 0, 2)) }}</span>
                                            @endif
                                        </a>
                                        <div class="min-w-0 self-center">
                                            <h2 class="truncate font-serif text-lg font-bold leading-tight text-ink-950 sm:text-xl"><a href="{{ route('literatures.show', $activity->literature) }}" class="hover:text-brand-coral">{{ $title }}</a></h2>
                                            @if ($rating !== null)
                                                <div class="mt-1 flex items-center gap-1.5"><x-star-rating :rating="$rating" size="sm" /><span class="text-xs font-bold text-ink-950/45">{{ number_format((float) $rating, 1) }}</span></div>
                                            @endif
                                            @if ($excerpt)
                                                <p class="mt-1.5 line-clamp-2 text-sm leading-5 text-ink-950/60">{{ $containsSpoiler ? 'This activity contains spoilers.' : $excerpt }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @elseif ($activity->type === \App\Models\Activity::TYPE_DISCUSSION || $activity->type === \App\Models\Activity::TYPE_COMMENT)
                                    <div class="mt-2 border-l-2 border-brand-coral bg-brand-yogurt/25 px-3 py-2">
                                        <p class="truncate text-sm font-bold text-ink-950">{{ data_get($activity->metadata, 'title') ?? data_get($activity->metadata, 'discussion_title') }}</p>
                                        @if ($excerpt)
                                            <p class="mt-0.5 line-clamp-1 text-xs leading-5 text-ink-950/55">{{ $containsSpoiler ? 'This activity contains spoilers.' : $excerpt }}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="border-b border-ink-950/10 py-10 text-center">
                        <p class="font-serif text-xl font-bold text-ink-950">No activity in this view yet.</p>
                        <p class="mt-1.5 text-sm text-ink-950/55">Read, rate, review, discuss, or follow another reader to build your stream.</p>
                    </div>
                @endforelse
            </div>

            @if ($activities->hasPages())
                <div class="mt-6">{{ $activities->links() }}</div>
            @endif
        </main>

        <aside class="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-5">
            <div class="sticky top-24">
                <section class="border border-ink-950/10 bg-brand-cream/65 p-4" aria-labelledby="activity-filter-heading">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-brand-coral">Activity filters</p>
                            <h2 id="activity-filter-heading" class="mt-0.5 font-serif text-lg font-bold text-ink-950">Choose your stream</h2>
                        </div>
                        <span class="grid size-7 place-items-center rounded-full border border-ink-950/10 text-xs text-ink-950/45" aria-hidden="true">&#9776;</span>
                    </div>
                    <div class="mt-3 grid gap-1.5 text-xs">
                        <a href="{{ route('activity.index') }}" class="flex justify-between border px-3 py-2.5 font-bold transition {{ $scope === 'all' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/10 text-ink-950 hover:border-brand-coral' }}"><span>You + friends</span><span>All</span></a>
                        <a href="{{ route('activity.index', ['scope' => 'you']) }}" class="flex justify-between border px-3 py-2.5 font-bold transition {{ $scope === 'you' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/10 text-ink-950 hover:border-brand-coral' }}"><span>Your activity</span><span>You</span></a>
                        <a href="{{ route('activity.index', ['scope' => 'friends']) }}" class="flex justify-between border px-3 py-2.5 font-bold transition {{ $scope === 'friends' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/10 text-ink-950 hover:border-brand-coral' }}"><span>Following</span><span>Friends</span></a>
                    </div>
                </section>
                <p class="mt-3 text-xs leading-5 text-ink-950/45">Includes Readlist additions, reading milestones, ratings, reviews, discussions, and comments. Moderated content is excluded.</p>
            </div>
        </aside>
    </section>
</x-app-shell>
