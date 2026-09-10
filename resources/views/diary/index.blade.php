<x-app-shell title="Personal Diary">
    @php($reader = auth()->user())

    <x-profile-subnav :user="$reader" current="diary" />

    <section class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14" aria-labelledby="activity-history-heading">
        <div class="flex flex-col gap-2 border-b border-ink-950/20 pb-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Personal reading record</p>
                <h1 id="activity-history-heading" class="mt-1 font-serif text-3xl font-bold text-ink-950">Activity history</h1>
            </div>
            <span class="text-sm text-ink-950/55">{{ $activities->count() }} rated {{ Str::plural('literature', $activities->count()) }}</span>
        </div>

        <div class="mt-5 overflow-x-auto border border-ink-950/10 bg-white/25" data-diary-activity-history>
            <div class="hidden min-w-[850px] grid-cols-[100px_90px_minmax(280px,1fr)_110px_190px_90px_70px] border-b border-ink-950/15 bg-brand-cream/65 px-4 py-3 text-xs font-bold uppercase tracking-wider text-ink-950/50 md:grid" role="row">
                <span>Month</span>
                <span>Day</span>
                <span>Literature</span>
                <span>Released</span>
                <span>Rating</span>
                <span>Review</span>
                <span>Edit</span>
            </div>

            <ol class="min-w-0 md:min-w-[850px]">
                @forelse ($activities as $activity)
                    @php($literature = $activity['literature'])
                    <li class="grid grid-cols-[68px_minmax(0,1fr)] border-b border-ink-950/10 px-4 py-4 last:border-b-0 md:grid-cols-[100px_90px_minmax(280px,1fr)_110px_190px_90px_70px] md:items-center md:py-3" data-diary-rating-row>
                        <div class="row-span-2 self-start text-center md:row-span-1 md:text-left">
                            <time datetime="{{ $activity['occurred_at']->utc()->toIso8601String() }}" data-local-date-part="month" class="block text-xs font-bold uppercase tracking-[0.14em] text-ink-950/55">{{ $activity['occurred_at']->utc()->format('M') }}</time>
                            <time datetime="{{ $activity['occurred_at']->utc()->toIso8601String() }}" data-local-date-part="year" class="mt-1 block text-xs text-ink-950/45">{{ $activity['occurred_at']->utc()->format('Y') }}</time>
                        </div>
                        <time datetime="{{ $activity['occurred_at']->utc()->toIso8601String() }}" data-local-date-part="day" class="font-serif text-3xl text-ink-950/60">{{ $activity['occurred_at']->utc()->format('d') }}</time>

                        <a href="{{ route('literatures.show', $literature) }}" class="group col-start-2 mt-2 flex min-w-0 items-center gap-3 md:col-start-auto md:mt-0">
                            <span class="h-16 w-11 shrink-0 overflow-hidden border border-ink-950/15 bg-brand-sky/20">
                                @if ($literature->displayCoverUrl())
                                    <img src="{{ $literature->displayCoverUrl() }}" alt="" class="size-full object-cover" loading="lazy">
                                @else
                                    <span class="grid size-full place-items-center font-serif text-sm font-bold text-ink-950">{{ Str::upper(Str::substr($literature->title, 0, 2)) }}</span>
                                @endif
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-serif text-xl font-bold text-ink-950 transition group-hover:text-brand-coral">{{ $literature->displayTitle() }}</span>
                                <span class="mt-1 block truncate text-xs text-ink-950/50">{{ $literature->authors->pluck('name')->implode(' & ') ?: 'Author unavailable' }}</span>
                            </span>
                        </a>

                        <span class="hidden text-sm text-ink-950/60 md:block">{{ $literature->displayPublicationYear() ?: '—' }}</span>
                        <div class="col-start-2 mt-3 flex items-center gap-2 md:col-start-auto md:mt-0">
                            <x-star-rating :rating="$activity['rating']" size="sm" />
                            <span class="text-xs font-bold text-ink-950/50">{{ number_format($activity['rating'], 1) }}</span>
                        </div>
                        <span class="hidden text-sm font-semibold text-ink-950/55 md:block">{{ filled($activity['review']) ? ($activity['contains_spoiler'] ? 'Spoiler' : 'Written') : '—' }}</span>
                        <a href="{{ route('literatures.show', $literature) }}?review=edit" class="hidden text-sm font-bold text-brand-coral hover:underline md:inline" aria-label="Edit rating or review for {{ $literature->displayTitle() }}">Edit</a>
                    </li>
                @empty
                    <li class="p-8 text-center text-ink-950/60">No rated literature yet. Give a title a rating to add it to your Diary.</li>
                @endforelse
            </ol>
        </div>
        <p class="mt-4 text-sm leading-6 text-ink-950/50">Completed-only activity is excluded. Dates are displayed in your device's local time zone.</p>
    </section>
</x-app-shell>
