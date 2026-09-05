<x-app-shell :title="$literature['title']">
    @php
        $coverTheme = match ($literature['theme']) {
            'coral' => 'from-brand-coral via-brand-cream to-brand-sky text-ink-950',
            'sky' => 'from-brand-sky via-brand-cream to-ink-950 text-ink-950',
            'cream' => 'from-brand-cream via-brand-sky to-brand-cream text-ink-950',
            'deep' => 'from-ink-950 via-brand-cream to-brand-coral text-ink-950',
            'mixed' => 'from-brand-cream via-brand-coral to-brand-sky text-ink-950',
            default => 'from-brand-cream via-brand-sky to-brand-coral text-ink-950',
        };
    @endphp

    <section class="catalog-grid relative overflow-hidden border-b border-brand-sky/15">
        <div class="absolute inset-x-0 top-0 h-72 bg-linear-to-b from-brand-cream/15 to-transparent"></div>
        <div class="relative mx-auto max-w-7xl px-5 pb-16 pt-10 sm:px-8 lg:px-10 lg:pb-20 lg:pt-16">
            <a href="{{ route('literatures.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-brand-cream transition hover:text-brand-sky">
                <span aria-hidden="true">&larr;</span> Kembali ke katalog
            </a>

            <div class="mt-10 grid gap-8 md:grid-cols-[220px_1fr] lg:grid-cols-[250px_1fr_280px] lg:items-end">
                <div class="relative aspect-[2/3] overflow-hidden border border-brand-sky/30 bg-linear-to-br {{ $coverTheme }} shadow-2xl">
                    <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(115deg,transparent_20%,rgba(255,255,255,.35)_50%,transparent_80%)]"></div>
                    <div class="absolute inset-x-0 top-0 flex justify-between p-4 text-xs font-bold uppercase tracking-wider">
                        <span>{{ $literature['type_label'] }}</span>
                        <span>{{ $literature['year'] }}</span>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 p-6">
                        <span class="font-serif text-6xl font-bold leading-none">{{ $literature['initials'] }}</span>
                        <p class="mt-4 border-t border-current/40 pt-4 text-xs font-bold uppercase tracking-[0.18em]">{{ $literature['source'] }}</p>
                    </div>
                </div>

                <div class="pb-2">
                    <div class="flex flex-wrap items-center gap-3 text-sm font-semibold uppercase tracking-wider text-brand-sky">
                        <span class="bg-brand-coral px-2.5 py-1 text-ink-950">{{ $literature['type_label'] }}</span>
                        <span>{{ $literature['year'] }}</span>
                    </div>
                    <h1 class="mt-4 font-serif text-5xl font-bold leading-none tracking-tight text-brand-cream sm:text-6xl">{{ $literature['title'] }}</h1>
                    <p class="mt-4 text-lg text-brand-sky">Oleh <span class="font-semibold text-brand-cream">{{ $literature['author'] }}</span></p>
                    <p class="mt-7 max-w-2xl text-lg font-medium uppercase leading-7 tracking-[0.08em] text-brand-sky">{{ $literature['tagline'] }}</p>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-brand-cream/80">{{ $literature['synopsis'] }}</p>
                </div>

                <aside class="border border-brand-sky/20 bg-ink-900/95 lg:mb-2" aria-label="Status katalog">
                    <div class="border-b border-brand-sky/20 p-5">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-sky">Status katalog</p>
                        <div class="mt-4 flex items-center gap-3">
                            <span class="grid size-10 place-items-center bg-brand-cream font-bold text-ink-950">OK</span>
                            <div>
                                <p class="font-bold text-brand-cream">Metadata tersedia</p>
                                <p class="text-sm text-brand-sky">Siap ditampilkan</p>
                            </div>
                        </div>
                    </div>
                    <dl class="grid gap-4 p-5 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-brand-sky">Sumber</dt><dd class="font-semibold text-brand-cream">{{ $literature['source'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-brand-sky">Format</dt><dd class="font-semibold text-brand-cream">{{ $literature['format'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-brand-sky">Bahasa</dt><dd class="font-semibold text-brand-cream">{{ $literature['language'] }}</dd></div>
                    </dl>
                    <a href="{{ route('literatures.index') }}" class="block bg-brand-cream px-5 py-4 text-center font-bold text-ink-950 transition hover:bg-brand-sky">Cari karya lain</a>
                </aside>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
        <nav class="flex gap-6 overflow-x-auto border-b border-brand-sky/20 text-sm font-bold uppercase tracking-[0.14em] text-brand-sky" aria-label="Bagian detail">
            <a href="#summary" class="border-b-2 border-brand-coral pb-4 text-brand-cream">Ringkasan</a>
            <a href="#authors" class="pb-4 text-brand-cream/80 hover:text-brand-cream">Pengarang</a>
            <a href="#details" class="pb-4 text-brand-cream/80 hover:text-brand-cream">Detail</a>
            <a href="#genres" class="pb-4 text-brand-cream/80 hover:text-brand-cream">Genre</a>
        </nav>

        <div class="mt-10 grid gap-10 lg:grid-cols-[1.25fr_.75fr]">
            <div id="summary" class="border border-brand-sky/20 bg-ink-900 p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Tentang karya</p>
                <h2 class="mt-3 font-serif text-3xl font-bold text-brand-cream">Ringkasan metadata</h2>
                <p class="mt-5 max-w-3xl text-base leading-8 text-brand-cream/80">{{ $literature['synopsis'] }}</p>

                <div id="authors" class="mt-10 border-t border-brand-sky/20 pt-7">
                    <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-brand-sky">Pengarang dan kreator</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($literature['authors'] as $author)
                            <span class="bg-ink-800 px-3 py-2 font-semibold text-brand-cream">{{ $author }}</span>
                        @endforeach
                    </div>
                </div>

                <div id="genres" class="mt-8 border-t border-brand-sky/20 pt-7">
                    <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-brand-sky">Genre</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($literature['genres'] as $genre)
                            <span class="border border-brand-sky/25 px-3 py-2 text-brand-sky">{{ $genre }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <aside id="details" class="h-fit border border-brand-sky/20 bg-ink-900 p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Source tracking</p>
                <h2 class="mt-3 font-serif text-2xl font-bold text-brand-cream">Detail katalog</h2>
                <dl class="mt-7 grid gap-5 text-sm">
                    <div class="border-b border-brand-sky/15 pb-4"><dt class="text-brand-sky">Penerbit</dt><dd class="mt-1 font-semibold text-brand-cream">{{ $literature['publisher'] }}</dd></div>
                    <div class="border-b border-brand-sky/15 pb-4"><dt class="text-brand-sky">Identifier eksternal</dt><dd class="mt-1 font-semibold text-brand-cream">{{ $literature['identifier'] }}</dd></div>
                    <div class="border-b border-brand-sky/15 pb-4"><dt class="text-brand-sky">Sumber metadata</dt><dd class="mt-1 font-semibold text-brand-cream">{{ $literature['source'] }}</dd></div>
                    <div><dt class="text-brand-sky">Tahap implementasi</dt><dd class="mt-1 font-semibold text-brand-cream">Katalog internal MySQL</dd></div>
                </dl>
            </aside>
        </div>
    </section>
</x-app-shell>
