<x-app-shell title="Personal Diary">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-5xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Reading history</p>
            <div class="mt-3 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-serif text-5xl font-bold text-ink-950">Personal Diary</h1>
                    <p class="mt-3 max-w-2xl leading-7 text-ink-950/65">A chronological history of reading updates, progress, ratings, and reviews.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('profiles.show', auth()->user()) }}" class="border border-ink-950/20 bg-brand-cream/70 px-5 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral">Back to profile</a>
                    <a href="{{ route('profiles.readlist', auth()->user()) }}" class="bg-ink-950 px-5 py-3 text-sm font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Open Readlist</a>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16" aria-labelledby="activity-history-heading">
        <div class="flex items-end justify-between gap-4 border-b border-ink-950/15 pb-3">
            <h2 id="activity-history-heading" class="font-serif text-3xl font-bold text-ink-950">Activity history</h2>
            <span class="text-sm text-ink-950/55">Latest 100 activities</span>
        </div>

        <ol class="mt-6 border-l border-ink-950/20 pl-6" data-diary-activity-history>
            @forelse ($activities as $activity)
                <li class="relative pb-9">
                    <span class="absolute -left-[1.75rem] top-1.5 size-3 border-2 border-brand-cream bg-brand-coral ring-1 ring-ink-950/20"></span>
                    <time datetime="{{ $activity['occurred_at']->utc()->toIso8601String() }}" data-local-datetime class="text-xs font-bold uppercase tracking-wider text-ink-950/50">{{ $activity['occurred_at']->utc()->format('M j, Y, g:i A') }} (UTC)</time>
                    <article class="mt-2 border border-ink-950/10 bg-white/35 p-5 sm:p-6">
                        <p class="text-lg font-bold text-ink-950">{{ $activity['label'] }}</p>
                        <a href="{{ route('literatures.show', $activity['literature']) }}" class="mt-1 inline-block font-serif text-2xl font-bold text-brand-coral hover:underline">{{ $activity['literature']->original_title ?? $activity['literature']->title }}</a>

                        @if ($activity['rating'] !== null)
                            <div class="mt-3 flex items-center gap-2">
                                <x-star-rating :rating="$activity['rating']" size="sm" />
                                <span class="text-sm font-semibold text-ink-950/55">{{ number_format($activity['rating'], 1) }} / 5</span>
                            </div>
                        @endif

                        @if ($activity['progress_value'] !== null)
                            <p class="mt-3 text-sm text-ink-950/65">Progress: {{ $activity['progress_value'] }}{{ $activity['progress_total'] ? ' / '.$activity['progress_total'] : '' }} {{ $activity['progress_unit'] }}</p>
                        @endif

                        @if ($activity['note'])
                            <blockquote class="mt-4 border-l-2 border-brand-sky bg-brand-cream/55 px-4 py-3 text-sm leading-6 text-ink-950/70">{{ $activity['note'] }}</blockquote>
                        @endif
                    </article>
                </li>
            @empty
                <li class="border border-dashed border-ink-950/20 bg-white/25 p-7 text-ink-950/60">No reading activity has been recorded yet.</li>
            @endforelse
        </ol>
    </section>
</x-app-shell>
