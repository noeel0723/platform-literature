<x-app-shell title="Personal Diary">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Increment 2 / Reading Management</p>
            <div class="mt-3 flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="font-serif text-5xl font-bold text-ink-950">Personal Diary</h1>
                    <p class="mt-3 max-w-2xl leading-7 text-ink-950/65">Riwayat kronologis status, progres, dan catatan bacaan milik {{ auth()->user()->name }}.</p>
                </div>
                <a href="{{ route('literatures.index') }}" class="w-fit bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Tambah bacaan</a>
            </div>

            <div class="mt-10 grid grid-cols-2 gap-px border border-ink-950/10 bg-ink-950/10 sm:grid-cols-4">
                @foreach ($statusLabels as $status => $label)
                    <div class="bg-brand-cream/90 p-4 sm:p-5">
                        <p class="font-serif text-3xl font-bold text-ink-950">{{ $readingLists->where('status', $status)->count() }}</p>
                        <p class="mt-1 text-xs font-bold uppercase tracking-wider text-ink-950/55">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-10 px-5 py-12 sm:px-8 lg:grid-cols-[.8fr_1.2fr] lg:px-10 lg:py-16">
        <div>
            <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                <h2 class="font-serif text-3xl font-bold text-ink-950">Readlist</h2>
                <span class="text-sm text-ink-950/55">{{ $readingLists->count() }} karya</span>
            </div>
            <div class="mt-5 grid gap-3">
                @forelse ($readingLists as $item)
                    <a href="{{ route('literatures.show', $item->literature) }}" class="group grid grid-cols-[52px_1fr] gap-4 border border-ink-950/10 bg-white/40 p-3 transition hover:border-brand-coral">
                        <div class="aspect-[2/3] overflow-hidden bg-brand-sky/25">
                            @if ($item->literature->cover_url)
                                <img src="{{ $item->literature->cover_url }}" alt="" class="size-full object-cover">
                            @endif
                        </div>
                        <div class="min-w-0 py-1">
                            <h3 class="truncate font-bold text-ink-950 group-hover:text-brand-coral">{{ $item->literature->title }}</h3>
                            <p class="mt-1 text-sm text-ink-950/60">{{ $statusLabels[$item->status] ?? $item->status }}</p>
                            @if ($item->progress)
                                <p class="mt-2 text-xs font-semibold uppercase tracking-wider text-ink-950/50">{{ $item->progress->current_value }}{{ $item->progress->total_value ? ' / '.$item->progress->total_value : '' }} {{ $item->progress->unit }}</p>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="border border-dashed border-ink-950/20 p-6 text-ink-950/60">Readlist masih kosong. Buka katalog dan pilih sebuah karya untuk memulai.</div>
                @endforelse
            </div>
        </div>

        <div>
            <div class="flex items-end justify-between border-b border-ink-950/15 pb-3">
                <h2 class="font-serif text-3xl font-bold text-ink-950">Riwayat aktivitas</h2>
                <span class="text-sm text-ink-950/55">100 aktivitas terbaru</span>
            </div>
            <ol class="mt-5 border-l border-ink-950/20 pl-6">
                @forelse ($logs as $log)
                    <li class="relative pb-8">
                        <span class="absolute -left-[1.75rem] top-1.5 size-3 border-2 border-brand-cream bg-brand-coral ring-1 ring-ink-950/20"></span>
                        <time class="text-xs font-bold uppercase tracking-wider text-ink-950/50">{{ $log->occurred_at->timezone(config('app.display_timezone'))->format('d/m/Y H:i') }} {{ config('app.display_timezone_label') }}</time>
                        <p class="mt-1 text-lg font-bold text-ink-950">{{ $eventLabels[$log->event_type] ?? $log->event_type }}</p>
                        <a href="{{ route('literatures.show', $log->readingList->literature) }}" class="font-semibold text-brand-coral hover:underline">{{ $log->readingList->literature->title }}</a>
                        @if ($log->progress_value !== null)
                            <p class="mt-2 text-sm text-ink-950/65">Progres: {{ $log->progress_value }}{{ $log->progress_total ? ' / '.$log->progress_total : '' }} {{ $log->progress_unit }}</p>
                        @endif
                        @if ($log->note)
                            <blockquote class="mt-3 border-l-2 border-brand-sky bg-white/40 px-4 py-3 text-sm leading-6 text-ink-950/70">{{ $log->note }}</blockquote>
                        @endif
                    </li>
                @empty
                    <li class="border border-dashed border-ink-950/20 p-6 text-ink-950/60">Belum ada aktivitas bacaan yang dicatat.</li>
                @endforelse
            </ol>
        </div>
    </section>
</x-app-shell>
