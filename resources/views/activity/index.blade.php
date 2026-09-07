<x-app-shell title="Activity">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-8 sm:px-8 lg:px-10">
            <x-profile-subnav :user="$viewer" current="activity" />
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-10 px-5 py-10 sm:px-8 lg:grid-cols-[minmax(0,1fr)_280px] lg:px-10 lg:py-14">
        <main class="min-w-0" aria-labelledby="activity-heading">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-ink-950/20 pb-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Social reading stream</p>
                    <h1 id="activity-heading" class="mt-1 font-serif text-3xl font-bold text-ink-950">Latest Activity</h1>
                </div>
                <nav class="flex gap-4 text-xs font-bold uppercase tracking-wider" aria-label="Activity scopes">
                    @foreach (['all' => 'You + friends', 'you' => 'You', 'friends' => 'Friends'] as $value => $label)
                        <a href="{{ route('activity.index', ['scope' => $value]) }}" class="border-b-2 py-1 {{ $scope === $value ? 'border-brand-coral text-ink-950' : 'border-transparent text-ink-950/45 hover:text-ink-950' }}" @if ($scope === $value) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            <div data-activity-stream>
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

                    <article class="border-b border-ink-950/10 py-5" data-activity-item data-activity-type="{{ $activity->type }}">
                        <div class="flex items-start gap-3">
                            <a href="{{ route('profiles.show', $activity->user) }}" class="grid size-9 shrink-0 place-items-center overflow-hidden rounded-full bg-ink-950 font-serif text-xs font-bold text-brand-cream">
                                @if ($activity->user->avatarUrl())
                                    <img src="{{ $activity->user->avatarUrl() }}" alt="" class="size-full object-cover">
                                @else
                                    {{ Str::upper(Str::substr($activity->user->name, 0, 1)) }}
                                @endif
                            </a>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm leading-6 text-ink-950/60"><a href="{{ route('profiles.show', $activity->user) }}" class="font-bold text-ink-950 hover:text-brand-coral">{{ $activity->user->name }}</a> {{ $action }} <a href="{{ route('literatures.show', $activity->literature) }}" class="font-bold text-ink-950 hover:text-brand-coral">{{ $title }}</a></p>
                                <time datetime="{{ $activity->occurred_at->utc()->toIso8601String() }}" data-local-datetime class="mt-0.5 block text-xs text-ink-950/40">{{ $activity->occurred_at->utc()->format('M j, Y') }} (UTC)</time>

                                @if ($isExpanded)
                                    <div class="mt-4 grid grid-cols-[76px_minmax(0,1fr)] gap-4 sm:grid-cols-[92px_minmax(0,1fr)]">
                                        <a href="{{ route('literatures.show', $activity->literature) }}" class="aspect-[2/3] overflow-hidden rounded-sm border border-ink-950/15 bg-brand-sky/25">
                                            @if ($activity->literature->cover_url)
                                                <img src="{{ $activity->literature->cover_url }}" alt="Cover of {{ $title }}" class="size-full object-cover" loading="lazy">
                                            @else
                                                <span class="grid size-full place-items-center font-serif text-xl font-bold text-ink-950">{{ Str::upper(Str::substr($title, 0, 2)) }}</span>
                                            @endif
                                        </a>
                                        <div class="min-w-0 self-center">
                                            <h2 class="font-serif text-2xl font-bold text-ink-950"><a href="{{ route('literatures.show', $activity->literature) }}" class="hover:text-brand-coral">{{ $title }}</a></h2>
                                            @if ($rating !== null)
                                                <div class="mt-2 flex items-center gap-2"><x-star-rating :rating="$rating" /><span class="text-sm font-bold text-ink-950/50">{{ number_format((float) $rating, 1) }}</span></div>
                                            @endif
                                            @if ($excerpt)
                                                <p class="mt-3 line-clamp-3 font-serif text-lg leading-7 text-ink-950/70">{{ $containsSpoiler ? 'This activity contains spoilers.' : $excerpt }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @elseif ($activity->type === \App\Models\Activity::TYPE_DISCUSSION || $activity->type === \App\Models\Activity::TYPE_COMMENT)
                                    <div class="mt-3 border-l-2 border-brand-coral bg-brand-yogurt/30 px-4 py-3">
                                        <p class="font-bold text-ink-950">{{ data_get($activity->metadata, 'title') ?? data_get($activity->metadata, 'discussion_title') }}</p>
                                        @if ($excerpt)
                                            <p class="mt-1 line-clamp-2 text-sm leading-6 text-ink-950/60">{{ $containsSpoiler ? 'This activity contains spoilers.' : $excerpt }}</p>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="border-b border-ink-950/10 py-14 text-center">
                        <p class="font-serif text-2xl font-bold text-ink-950">No activity in this view yet.</p>
                        <p class="mt-2 text-ink-950/55">Read, rate, review, discuss, or follow another reader to build your stream.</p>
                    </div>
                @endforelse
            </div>

            @if ($activities->hasPages())
                <div class="mt-8">{{ $activities->links() }}</div>
            @endif
        </main>

        <aside class="min-w-0 lg:border-l lg:border-ink-950/10 lg:pl-7">
            <div class="sticky top-24">
                <section class="border border-ink-950/10 bg-brand-cream/80 p-5" aria-labelledby="activity-filter-heading">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Activity filters</p>
                    <h2 id="activity-filter-heading" class="mt-2 font-serif text-2xl font-bold text-ink-950">Choose your stream</h2>
                    <div class="mt-5 grid gap-2 text-sm">
                        <a href="{{ route('activity.index') }}" class="flex justify-between border px-4 py-3 font-bold {{ $scope === 'all' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/10 text-ink-950 hover:border-brand-coral' }}"><span>You + friends</span><span>All</span></a>
                        <a href="{{ route('activity.index', ['scope' => 'you']) }}" class="flex justify-between border px-4 py-3 font-bold {{ $scope === 'you' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/10 text-ink-950 hover:border-brand-coral' }}"><span>Your activity</span><span>You</span></a>
                        <a href="{{ route('activity.index', ['scope' => 'friends']) }}" class="flex justify-between border px-4 py-3 font-bold {{ $scope === 'friends' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/10 text-ink-950 hover:border-brand-coral' }}"><span>Following</span><span>Friends</span></a>
                    </div>
                </section>
                <p class="mt-5 text-sm leading-6 text-ink-950/50">Activity includes Readlist additions, reading milestones, ratings, reviews, discussions, and comments. Moderated content is excluded.</p>
            </div>
        </aside>
    </section>
</x-app-shell>
