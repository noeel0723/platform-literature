<x-app-shell :title="$author->name">
    <main class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-14" aria-labelledby="author-heading">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_290px] lg:gap-12 xl:grid-cols-[minmax(0,1fr)_320px]">
            <section class="min-w-0 lg:order-1" aria-labelledby="author-heading">
                <div class="border-b border-ink-950/15 pb-4">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-coral">Works by</p>
                    <div class="mt-1 flex flex-wrap items-end justify-between gap-3">
                        <h1 id="author-heading" class="font-serif text-3xl font-bold tracking-tight text-ink-950 sm:text-4xl">{{ $author->name }}</h1>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-ink-950/45">{{ $author->literatures_count }} {{ Str::plural('work', $author->literatures_count) }}</p>
                    </div>
                </div>

                @if ($literatures->isNotEmpty())
                    <div class="mt-5 grid grid-cols-3 gap-x-3 gap-y-6 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-4 xl:grid-cols-5">
                        @foreach ($literatures as $literature)
                            <div class="min-w-0">
                                <x-literature-card :literature="$literature" compact />
                                @if ($literature['rating'] !== null)
                                    <div class="mt-1 flex items-center gap-1 text-[0.68rem] font-semibold text-ink-950/50" aria-label="Average rating {{ number_format($literature['rating'], 1) }} out of 5">
                                        <span class="text-brand-coral" aria-hidden="true">★</span>
                                        <span>{{ number_format($literature['rating'], 1) }}</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($literatures->hasPages())
                        <div class="mt-10 border-t border-ink-950/10 pt-6">
                            {{ $literatures->links() }}
                        </div>
                    @endif
                @else
                    <div class="mt-6 border border-dashed border-ink-950/20 bg-white/25 p-7">
                        <p class="font-serif text-xl font-bold text-ink-950">No linked works yet</p>
                        <p class="mt-2 text-sm leading-6 text-ink-950/55">Works will appear here after the catalog connects this author to a literature record.</p>
                    </div>
                @endif
            </section>

            <aside class="min-w-0 lg:order-2" aria-label="About {{ $author->name }}">
                <div class="lg:sticky lg:top-24">
                    <div class="mx-auto aspect-[4/5] max-w-[260px] overflow-hidden border border-ink-950/15 bg-linear-to-br from-brand-sky/40 via-brand-yogurt to-brand-coral/25 shadow-[0_12px_30px_rgba(16,47,98,0.10)] lg:max-w-none">
                        @if ($author->image_url)
                            <img src="{{ $author->image_url }}" alt="Portrait of {{ $author->name }}" class="size-full object-cover" decoding="async">
                        @else
                            <div class="grid size-full place-items-center px-6 text-center">
                                <span class="font-serif text-6xl font-bold text-ink-950/75">{{ Str::upper(Str::substr($author->name, 0, 1)) }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="mx-auto mt-5 max-w-[420px] border-t border-ink-950/15 pt-4 lg:max-w-none">
                        <h2 class="font-serif text-2xl font-bold tracking-tight text-ink-950">{{ $author->name }}</h2>
                        <p class="mt-3 text-sm leading-6 text-ink-950/65">{{ $author->biography ?: 'A biography is not available from the connected metadata sources yet.' }}</p>

                        <dl class="mt-5 grid grid-cols-2 border-y border-ink-950/10 text-sm">
                            <div class="py-3">
                                <dt class="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-ink-950/40">Catalog works</dt>
                                <dd class="mt-1 font-semibold text-ink-950">{{ $author->literatures_count }}</dd>
                            </div>
                            <div class="border-l border-ink-950/10 py-3 pl-4">
                                <dt class="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-ink-950/40">Identity</dt>
                                <dd class="mt-1 font-semibold text-ink-950">Author</dd>
                            </div>
                        </dl>

                        @if ($profileSourceUrl)
                            <a href="{{ $profileSourceUrl }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-brand-stem transition hover:text-brand-coral">
                                View metadata source <span aria-hidden="true">↗</span>
                            </a>
                        @endif
                        @if ($imageLicenseUrl)
                            <a href="{{ $imageLicenseUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 block text-[0.65rem] font-semibold uppercase tracking-[0.1em] text-ink-950/45 hover:text-brand-coral">Portrait license</a>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </main>
</x-app-shell>
