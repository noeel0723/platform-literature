<x-app-shell title="Moderation">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Increment 5 / Administration</p>
            <div class="mt-3 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="font-serif text-5xl font-bold text-ink-950 sm:text-6xl">Moderation queue</h1>
                    <p class="mt-4 max-w-2xl leading-7 text-ink-950/65">Review community reports, hide violating content, deactivate an account when necessary, or dismiss reports that do not require action.</p>
                </div>
                <div class="border border-ink-950/10 bg-brand-cream/75 px-5 py-4 text-sm text-ink-950/60">
                    Signed in as <span class="font-bold text-ink-950">{{ auth()->user()->name }}</span>
                    <span class="ml-2 bg-ink-950 px-2 py-1 text-xs font-bold uppercase tracking-wider text-brand-cream">Admin</span>
                </div>
            </div>

            <nav class="mt-9 flex gap-2 overflow-x-auto" aria-label="Report status">
                @foreach ($statusLabels as $value => $label)
                    <a href="{{ route('admin.moderation.index', ['status' => $value]) }}" class="border px-4 py-2.5 text-sm font-bold transition {{ $status === $value ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/15 bg-brand-cream/65 text-ink-950 hover:border-brand-coral' }}">
                        {{ $label }} <span class="ml-1 opacity-60">{{ $statusCounts[$value] ?? 0 }}</span>
                    </a>
                @endforeach
            </nav>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-12 sm:px-8 lg:px-10 lg:py-16">
        @if ($errors->any())
            <div class="mb-7 border border-red-800/30 bg-red-50 p-5 text-sm font-semibold text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5">
            @forelse ($reports as $report)
                @php
                    $target = $report->reportable;
                    $targetType = $target ? class_basename($target) : 'Removed content';
                    $targetOwner = match (true) {
                        $target instanceof \App\Models\User => $target,
                        $target instanceof \App\Models\Review,
                        $target instanceof \App\Models\Discussion,
                        $target instanceof \App\Models\Comment => $target->user,
                        default => null,
                    };
                    $targetSummary = match (true) {
                        $target instanceof \App\Models\User => $target->name.' (@'.$target->username.')',
                        $target instanceof \App\Models\Review => $target->body ?: 'Rating: '.number_format($target->rating, 1).' / 5',
                        $target instanceof \App\Models\Discussion => $target->title.' - '.$target->body,
                        $target instanceof \App\Models\Comment => $target->body,
                        default => 'The reported item is no longer available.',
                    };
                    $targetUrl = match (true) {
                        $target instanceof \App\Models\User => route('profiles.show', $target),
                        $target instanceof \App\Models\Review => route('literatures.show', $target->literature).'#review-'.$target->id,
                        $target instanceof \App\Models\Discussion => route('literatures.show', $target->literature).'#discussion-'.$target->id,
                        $target instanceof \App\Models\Comment => route('literatures.show', $target->discussion->literature).'#discussion-'.$target->discussion_id,
                        default => null,
                    };
                @endphp

                <article class="border border-ink-950/10 bg-white/35 p-5 sm:p-7">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.14em]">
                                <span class="bg-brand-coral px-2.5 py-1 text-brand-cream">{{ $reasonLabels[$report->reason] ?? Str::headline($report->reason) }}</span>
                                <span class="border border-ink-950/15 px-2.5 py-1 text-ink-950/60">{{ $targetType }}</span>
                                <span class="border border-ink-950/15 px-2.5 py-1 text-ink-950/60">{{ $statusLabels[$report->status] ?? Str::headline($report->status) }}</span>
                            </div>

                            <p class="mt-5 line-clamp-4 whitespace-pre-line text-lg leading-8 text-ink-950">{{ $targetSummary }}</p>

                            <dl class="mt-5 grid gap-3 border-t border-ink-950/10 pt-5 text-sm text-ink-950/60 sm:grid-cols-2">
                                <div><dt class="text-xs font-bold uppercase tracking-wider text-ink-950/45">Reported by</dt><dd class="mt-1 font-semibold text-ink-950">{{ $report->reporter->name }}</dd></div>
                                <div><dt class="text-xs font-bold uppercase tracking-wider text-ink-950/45">Content owner</dt><dd class="mt-1 font-semibold text-ink-950">{{ $targetOwner?->name ?? 'Unavailable' }}</dd></div>
                                <div><dt class="text-xs font-bold uppercase tracking-wider text-ink-950/45">Submitted</dt><dd class="mt-1"><time datetime="{{ $report->created_at->utc()->toIso8601String() }}" data-local-datetime>{{ $report->created_at->utc()->format('M j, Y, g:i A') }} (UTC)</time></dd></div>
                                @if ($targetUrl)
                                    <div><dt class="text-xs font-bold uppercase tracking-wider text-ink-950/45">Target</dt><dd class="mt-1"><a href="{{ $targetUrl }}" class="font-bold underline decoration-brand-coral underline-offset-4">Open reported item</a></dd></div>
                                @endif
                            </dl>

                            @if ($report->details)
                                <div class="mt-5 border-l-4 border-brand-sky bg-brand-sky/15 p-4"><p class="text-xs font-bold uppercase tracking-wider text-ink-950/45">Reporter details</p><p class="mt-2 leading-7 text-ink-950/70">{{ $report->details }}</p></div>
                            @endif

                            @if ($report->status !== 'pending')
                                <p class="mt-5 text-sm text-ink-950/55">Reviewed by <strong class="text-ink-950">{{ $report->resolver?->name ?? 'Former administrator' }}</strong>@if ($report->resolution_note): {{ $report->resolution_note }}@endif</p>
                            @endif
                        </div>

                        @if ($report->status === 'pending')
                            <form action="{{ route('admin.moderation.update', $report) }}" method="POST" class="grid content-start gap-4 border border-ink-950/10 bg-brand-cream/70 p-5">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label for="resolution-note-{{ $report->id }}" class="text-xs font-bold uppercase tracking-wider text-ink-950/60">Internal resolution note</label>
                                    <textarea id="resolution-note-{{ $report->id }}" name="resolution_note" rows="4" maxlength="1000" class="mt-2 w-full resize-y border border-ink-950/20 bg-white/65 px-3 py-2 text-sm outline-none focus:border-brand-coral" placeholder="Optional note for the moderation record"></textarea>
                                </div>

                                @if ($target instanceof \App\Models\Review || $target instanceof \App\Models\Discussion || $target instanceof \App\Models\Comment)
                                    @if ($target->hidden_at)
                                        <p class="border border-ink-950/10 bg-white/50 p-3 text-sm font-semibold text-ink-950/55">This content is already hidden.</p>
                                    @else
                                        <button name="action" value="hide" class="bg-red-800 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-700">Hide content and resolve</button>
                                    @endif
                                @elseif ($target instanceof \App\Models\User)
                                    @if ($target->deactivated_at)
                                        <p class="border border-ink-950/10 bg-white/50 p-3 text-sm font-semibold text-ink-950/55">This account is already deactivated.</p>
                                    @elseif (! $target->isAdmin())
                                        <button name="action" value="deactivate" class="bg-red-800 px-4 py-3 text-sm font-bold text-white transition hover:bg-red-700">Deactivate account and resolve</button>
                                    @endif
                                @endif

                                <button name="action" value="dismiss" class="border border-ink-950/20 px-4 py-3 text-sm font-bold text-ink-950 transition hover:border-brand-coral">Dismiss report</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="border border-dashed border-ink-950/20 bg-white/25 p-10 text-center">
                    <p class="font-serif text-3xl font-bold text-ink-950">No {{ Str::lower($statusLabels[$status]) }} reports</p>
                    <p class="mt-3 text-ink-950/55">This moderation queue is currently clear.</p>
                </div>
            @endforelse
        </div>

        @if ($reports->hasPages())
            <div class="mt-8">{{ $reports->links() }}</div>
        @endif
    </section>
</x-app-shell>
