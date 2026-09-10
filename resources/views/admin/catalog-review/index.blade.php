<x-app-shell title="Catalog review">
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto max-w-6xl px-5 py-7 sm:px-8 lg:px-10 lg:py-9">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Catalog administration</p>
            <div class="mt-1.5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-ink-950 sm:text-4xl">Catalog review</h1>
                    <p class="mt-2 max-w-2xl text-xs leading-5 text-ink-950/55">Merge a source record into the same work, or keep it separate when it represents a different work.</p>
                </div>
                <span class="w-fit border border-ink-950/15 bg-brand-cream/75 px-3 py-1.5 text-[0.65rem] font-bold uppercase tracking-wider text-ink-950">
                    {{ $mappings->total() }} need review
                </span>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-7 sm:px-8 lg:px-10 lg:py-8">
        @if ($errors->any())
            <div class="mb-6 border border-red-800/30 bg-red-50 p-4 text-sm font-semibold text-red-800">{{ $errors->first() }}</div>
        @endif

        <div class="grid gap-3">
            @forelse ($mappings as $mapping)
                @php
                    $literature = $mapping->literature;
                    $candidates = collect($mapping->candidate_work_ids ?? [])->map(fn ($id) => $candidateWorks->get((int) $id))->filter();
                @endphp
                <article class="border border-ink-950/10 bg-white/35 p-4">
                    <div class="grid gap-4 lg:grid-cols-[minmax(0,0.72fr)_minmax(0,1.28fr)]">
                        <div class="grid min-w-0 grid-cols-[48px_minmax(0,1fr)] gap-3">
                            <a href="{{ route('literatures.show', $literature) }}" class="aspect-[2/3] overflow-hidden border border-ink-950/15 bg-brand-sky/20">
                                @if ($literature->cover_url)
                                    <img src="{{ $literature->cover_url }}" alt="" class="size-full object-cover" loading="lazy">
                                @endif
                            </a>
                            <div class="min-w-0">
                                <div class="flex flex-wrap gap-1.5 text-[0.6rem] font-bold uppercase tracking-wider">
                                    <span class="bg-brand-coral px-1.5 py-0.5 text-brand-cream">Needs review</span>
                                    <span class="border border-ink-950/15 px-1.5 py-0.5 text-ink-950/55">{{ $mapping->apiSource->name }}</span>
                                </div>
                                <h2 class="mt-2 truncate text-base font-bold text-ink-950">{{ $literature->displayTitle() }}</h2>
                                <p class="mt-0.5 truncate text-xs text-ink-950/55">{{ $literature->authors->pluck('name')->implode(', ') ?: 'Author unavailable' }}</p>
                                <dl class="mt-2 grid gap-0.5 text-[0.68rem] text-ink-950/50 sm:grid-cols-2">
                                    <div><dt class="inline font-bold text-ink-950/65">Reason:</dt> <dd class="inline">{{ Str::headline($mapping->match_method) }}</dd></div>
                                    <div><dt class="inline font-bold text-ink-950/65">Confidence:</dt> <dd class="inline">{{ number_format($mapping->confidence * 100) }}%</dd></div>
                                </dl>
                            </div>
                        </div>

                        <div class="min-w-0 border-t border-ink-950/10 pt-3 lg:border-l lg:border-t-0 lg:pl-4 lg:pt-0">
                            <p class="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-ink-950/55">Suggested canonical works</p>
                            <div class="mt-2 grid gap-1.5">
                                @foreach ($candidates as $candidate)
                                    @php($candidateLiterature = $candidate->preferredLiterature)
                                    <form action="{{ route('admin.catalog-review.update', $mapping) }}" method="POST" class="flex items-center justify-between gap-3 border border-ink-950/10 bg-brand-cream/65 px-2.5 py-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="action" value="merge">
                                        <input type="hidden" name="canonical_work_id" value="{{ $candidate->id }}">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-bold text-ink-950">{{ $candidate->canonical_title }}</span>
                                            <span class="block truncate text-[0.68rem] text-ink-950/50">{{ $candidate->primaryAuthor?->name ?? $candidateLiterature?->authors->pluck('name')->implode(', ') ?: 'Author unavailable' }}@if ($candidate->publication_year) · {{ $candidate->publication_year }}@endif</span>
                                        </span>
                                        <button title="Treat this source record as the same work" class="shrink-0 bg-ink-950 px-2.5 py-1.5 text-[0.68rem] font-bold text-brand-cream transition hover:bg-brand-coral">Merge</button>
                                    </form>
                                @endforeach

                                <form action="{{ route('admin.catalog-review.update', $mapping) }}" method="POST" class="mt-0.5">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="action" value="keep_separate">
                                    <button title="Keep this source record as its own distinct work" class="w-full border border-ink-950/20 px-3 py-2 text-[0.68rem] font-bold text-ink-950 transition hover:border-brand-coral">Keep as a separate work</button>
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
