<x-app-shell>
    <section class="catalog-grid border-b border-ink-950/10">
        <div class="mx-auto min-w-0 max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <div class="min-w-0 max-w-3xl">
                <p class="mb-5 text-xs font-bold uppercase tracking-[0.28em] text-brand-coral">Increment 1 / Katalog terintegrasi</p>
                <h1 class="max-w-2xl font-serif text-5xl font-bold leading-[0.98] tracking-tight text-ink-950 sm:text-6xl lg:text-7xl">
                    Satu rak untuk setiap cerita.
                </h1>
                <p class="mt-7 max-w-xl text-base leading-8 text-ink-950/65 sm:text-lg">
                    Jelajahi buku, komik Barat, manga, dan light novel tanpa berpindah platform.
                    Metadata disiapkan dalam satu bentuk yang konsisten dan mudah dipahami.
                </p>
                <div class="mt-8 flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                    <a href="#catalog-title" class="inline-flex items-center gap-2 bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">
                        Jelajahi katalog <span aria-hidden="true">&darr;</span>
                    </a>
                    <p class="text-sm text-ink-950/60">Pencarian cepat tersedia pada menu di atas.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20" aria-labelledby="catalog-title">
        <div class="mb-8 flex flex-col gap-5 border-b border-ink-950/15 pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-coral">Katalog pilihan</p>
                <h2 id="catalog-title" class="mt-2 font-serif text-3xl font-bold text-ink-950">
                    {{ $query !== '' || $selectedType !== '' ? 'Hasil pencarian' : 'Mulai jelajahi' }}
                </h2>
                @if ($query !== '' || $selectedType !== '')
                    <p class="mt-2 text-sm text-ink-950/60">{{ $literatures->count() }} karya sesuai filter.</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2" aria-label="Filter format">
                <a href="{{ route('literatures.index', ['q' => $query]) }}" class="border px-3 py-2 text-xs font-semibold uppercase tracking-wider transition {{ $selectedType === '' ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/20 text-ink-950/75 hover:border-ink-950 hover:bg-brand-sky/25' }}">Semua</a>
                @foreach ($types as $value => $label)
                    <a href="{{ route('literatures.index', ['q' => $query, 'type' => $value]) }}" class="border px-3 py-2 text-xs font-semibold uppercase tracking-wider transition {{ $selectedType === $value ? 'border-ink-950 bg-ink-950 text-brand-cream' : 'border-ink-950/20 text-ink-950/75 hover:border-ink-950 hover:bg-brand-sky/25' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($sourceWarning !== null)
            <div role="status" class="mb-8 border border-brand-coral/35 bg-white/35 px-4 py-3 text-sm leading-6 text-ink-950/70">
                <span class="font-bold text-ink-950">Katalog lokal tetap aktif.</span>
                {{ $sourceWarning }}
            </div>
        @endif

        @if ($literatures->isEmpty())
            <div class="border border-ink-950/10 bg-white/35 px-6 py-14 text-center">
                <p class="font-serif text-2xl font-bold text-ink-950">Belum ada karya yang cocok.</p>
                <p class="mt-2 text-ink-950/60">Coba kata kunci lain atau kembalikan filter ke semua format.</p>
                <a href="{{ route('literatures.index') }}" class="mt-6 inline-block bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Hapus filter</a>
            </div>
        @else
            <div class="grid grid-cols-2 gap-x-4 gap-y-9 sm:grid-cols-3 sm:gap-x-5 lg:grid-cols-6">
                @foreach ($literatures as $literature)
                    <x-literature-card :$literature />
                @endforeach
            </div>
        @endif
    </section>

    <section id="sources" class="border-y border-ink-950/10 bg-white/30">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-coral">Sumber metadata</p>
                <h2 class="mt-3 font-serif text-3xl font-bold text-ink-950 sm:text-4xl">Tiga sumber, satu bahasa katalog.</h2>
                <p class="mt-4 leading-7 text-ink-950/65">Fondasi katalog kini tersimpan di MySQL. Respons setiap API berikutnya akan dipetakan ke struktur internal yang sama.</p>
            </div>
            <div class="mt-9 grid gap-px bg-ink-950/10 md:grid-cols-3">
                @foreach ([
                    ['Google Books', 'Buku dan novel umum', 'GB', 'Terhubung / API key server'],
                    ['Comic Vine', 'Komik Barat', 'CV', filled(config('services.comic_vine.key')) ? 'Terhubung / API key server' : 'Menunggu API key'],
                    ['AniList', 'Manga dan light novel', 'AL', 'Terhubung / data publik'],
                ] as [$source, $scope, $code, $status])
                    <article class="bg-brand-cream/85 p-6 sm:p-8">
                        <span class="grid size-12 place-items-center bg-brand-sky font-bold text-ink-950">{{ $code }}</span>
                        <h3 class="mt-6 text-xl font-bold text-ink-950">
                            @if ($source === 'Comic Vine')
                                <a href="https://comicvine.gamespot.com/" target="_blank" rel="noreferrer" class="underline decoration-ink-950/20 underline-offset-4 transition hover:decoration-brand-coral">{{ $source }}</a>
                            @else
                                {{ $source }}
                            @endif
                        </h3>
                        <p class="mt-2 text-ink-950/60">{{ $scope }}</p>
                        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-brand-coral">{{ $status }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="increment" class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
        <div class="grid gap-10 lg:grid-cols-[.7fr_1.3fr]">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-coral">Batas fitur</p>
                <h2 class="mt-3 font-serif text-3xl font-bold text-ink-950">Yang tersedia pada tahap ini.</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['Pencarian lintas format', 'Detail metadata terstruktur', 'Identitas sumber data', 'Tampilan responsif'] as $feature)
                    <div class="flex gap-4 border border-ink-950/10 bg-white/35 p-5">
                        <span class="mt-1 size-2 shrink-0 bg-brand-coral"></span>
                        <p class="font-semibold text-ink-950">{{ $feature }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-app-shell>
