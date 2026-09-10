<x-app-shell title="Catalog review">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Catalog administration</p>
            <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-ink-950 sm:text-5xl">Catalog review</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-ink-950/60">Resolve uncertain source matches without automatically merging similarly named works.</p>
                </div>
                <span class="w-fit border border-ink-950/15 bg-brand-cream/75 px-4 py-2 text-xs font-bold uppercase tracking-wider text-ink-950">
                    {{ $mappings->total() }} need review
                </span>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-12">
        @if ($errors->any())
            <div class="mb-6 border border-red-800/30 bg-red-50 p-4 text-sm font-semibold text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-5">
            @forelse ($mappings as $mapping)
                @php
                    $literature = $mapping->literature;
                    $candidates = collect($mapping->candidate_work_ids ?? [])->map(fn ($id) => $candidateWorks->get((int) $id))->filter();
                @endphp
                <article class="border border-ink-950/10 bg-white/35 p-5 sm:p-6">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                        <div class="grid min-w-0 grid-cols-[64px_minmax(0,1fr)] gap-4">
                            <a href="{{ route('literatures.show', $literature) }}" class="aspect-[2/3] overflow-hidden border border-ink-950/15 bg-brand-sky/20">
                                @if ($literature->cover_url)
                                    <img src="{{ $literature->cover_url }}" alt="" class="size-full object-cover" loading="lazy">
                                @endif
                            </a>
                            <div class="min-w-0">
                                <div class="flex flex-wrap gap-2 text-[0.65rem] font-bold uppercase tracking-wider">
                                    <span class="bg-brand-coral px-2 py-1 text-brand-cream">Needs review</span>
                                    <span class="border border-ink-950/15 px-2 py-1 text-ink-950/55">{{ $mapping->apiSource->name }}</span>
                                </div>
                                <h2 class="mt-3 truncate text-xl font-bold text-ink-950">{{ $literature->displayTitle() }}</h2>
                                <p class="mt-1 text-sm text-ink-950/55">{{ $literature->authors->pluck('name')->implode(', ') ?: 'Author unavailable' }}</p>
                                <dl class="mt-3 grid gap-1 text-xs text-ink-950/50">
                                    <div><dt class="inline font-bold text-ink-950/65">Reason:</dt> <dd class="inline">{{ Str::headline($mapping->match_method) }}</dd></div>
                                    <div><dt class="inline font-bold text-ink-950/65">Confidence:</dt> <dd class="inline">{{ number_format($mapping->confidence * 100) }}%</dd></div>
                                </dl>
                            </div>
                        </div>

                        <div class="min-w-0 border-t border-ink-950/10 pt-5 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-ink-950/55">Suggested canonical works</p>
                            <div class="mt-3 grid gap-2">
                                @foreach ($candidates as $candidate)
                                    @php($candidateLiterature = $candidate->preferredLiterature)
                                    <form action="{{ route('admin.catalog-review.update', $mapping) }}" method="POST" class="flex items-center justify-between gap-4 border border-ink-950/10 bg-brand-cream/65 p-3">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="merge">
                                        <input type="hidden" name="canonical_work_id" value="{{ $candidate->id }}">
                                        <span class="min-w-0">
                                            <span class="block truncate font-bold text-ink-950">{{ $candidate->canonical_title }}</span>
                                            <span class="mt-0.5 block truncate text-xs text-ink-950/50">{{ $candidate->primaryAuthor?->name ?? $candidateLiterature?->authors->pluck('name')->implode(', ') ?: 'Author unavailable' }}@if ($candidate->publication_year) · {{ $candidate->publication_year }}@endif</span>
                                        </span>
                                        <button class="shrink-0 bg-ink-950 px-3 py-2 text-xs font-bold text-brand-cream transition hover:bg-brand-coral">Merge</button>
                                    </form>
                                @endforeach

                                <form action="{{ route('admin.catalog-review.update', $mapping) }}" method="POST" class="mt-1">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="keep_separate">
                                    <button class="w-full border border-ink-950/20 px-4 py-2.5 text-xs font-bold text-ink-950 transition hover:border-brand-coral">Confirm as a separate work</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </article>
            @empty
                <div class="border border-dashed border-ink-950/20 bg-white/25 p-10 text-center">
                    <p class="text-2xl font-bold text-ink-950">Catalog review queue is clear</p>
                    <p class="mt-2 text-sm text-ink-950/55">No ambiguous source matches currently need an administrator decision.</p>
                </div>
            @endforelse
        </div>

        @if ($mappings->hasPages())
            <div class="mt-8">{{ $mappings->links() }}</div>
        @endif
    </section>
</x-app-shell>
