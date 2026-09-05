<x-app-shell>
    <section class="catalog-grid border-b border-brand-sky/15">
        <div class="mx-auto min-w-0 max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <div class="min-w-0 max-w-3xl">
                <p class="mb-5 text-xs font-bold uppercase tracking-[0.28em] text-brand-coral">Increment 1 / Katalog terintegrasi</p>
                <h1 class="max-w-2xl font-serif text-5xl font-bold leading-[0.98] tracking-tight text-brand-cream sm:text-6xl lg:text-7xl">
                    Satu rak untuk setiap cerita.
                </h1>
                <p class="mt-7 max-w-xl text-base leading-8 text-brand-sky sm:text-lg">
                    Jelajahi buku, komik Barat, manga, dan light novel tanpa berpindah platform.
                    Metadata disiapkan dalam satu bentuk yang konsisten dan mudah dipahami.
                </p>
                <div class="mt-8 flex flex-col items-start gap-3 sm:flex-row sm:items-center">
                    <a href="#catalog-title" class="inline-flex items-center gap-2 bg-brand-cream px-5 py-3 font-bold text-ink-950 transition hover:bg-brand-sky">
                        Jelajahi katalog <span aria-hidden="true">&darr;</span>
                    </a>
                    <p class="text-sm text-brand-sky">Pencarian cepat tersedia pada menu di atas.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20" aria-labelledby="catalog-title">
        <div class="mb-8 flex flex-col gap-5 border-b border-brand-sky/20 pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-coral">Katalog pilihan</p>
                <h2 id="catalog-title" class="mt-2 font-serif text-3xl font-bold text-brand-cream">
                    {{ $query !== '' || $selectedType !== '' ? 'Hasil pencarian' : 'Mulai jelajahi' }}
                </h2>
                @if ($query !== '' || $selectedType !== '')
                    <p class="mt-2 text-sm text-brand-sky">{{ $literatures->count() }} karya sesuai filter.</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2" aria-label="Filter format">
                <a href="{{ route('literatures.index', ['q' => $query]) }}" class="border px-3 py-2 text-xs font-semibold uppercase tracking-wider transition {{ $selectedType === '' ? 'border-brand-cream bg-brand-cream text-ink-950' : 'border-brand-cream/25 text-brand-cream/80 hover:border-brand-cream hover:text-brand-cream' }}">Semua</a>
                @foreach ($types as $value => $label)
                    <a href="{{ route('literatures.index', ['q' => $query, 'type' => $value]) }}" class="border px-3 py-2 text-xs font-semibold uppercase tracking-wider transition {{ $selectedType === $value ? 'border-brand-cream bg-brand-cream text-ink-950' : 'border-brand-cream/25 text-brand-cream/80 hover:border-brand-cream hover:text-brand-cream' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($literatures->isEmpty())
            <div class="border border-brand-sky/20 bg-ink-900 px-6 py-14 text-center">
                <p class="font-serif text-2xl font-bold text-brand-cream">Belum ada karya yang cocok.</p>
                <p class="mt-2 text-brand-sky">Coba kata kunci lain atau kembalikan filter ke semua format.</p>
                <a href="{{ route('literatures.index') }}" class="mt-6 inline-block bg-brand-cream px-5 py-3 font-bold text-ink-950 transition hover:bg-brand-sky">Hapus filter</a>
            </div>
        @else
            <div class="grid grid-cols-2 gap-x-4 gap-y-9 sm:grid-cols-3 sm:gap-x-5 lg:grid-cols-6">
                @foreach ($literatures as $literature)
                    <x-literature-card :$literature />
                @endforeach
            </div>
        @endif
    </section>

    <section id="sources" class="border-y border-brand-sky/15 bg-ink-900/65">
        <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-coral">Sumber metadata</p>
                <h2 class="mt-3 font-serif text-3xl font-bold text-brand-cream sm:text-4xl">Tiga sumber, satu bahasa katalog.</h2>
                <p class="mt-4 leading-7 text-brand-sky">Respons setiap API akan dipetakan ke struktur internal yang sama sebelum disimpan ke MySQL.</p>
            </div>
            <div class="mt-9 grid gap-px bg-brand-sky/20 md:grid-cols-3">
                @foreach ([
                    ['Google Books', 'Buku dan novel umum', 'GB'],
                    ['Comic Vine', 'Komik Barat', 'CV'],
                    ['AniList', 'Manga dan light novel', 'AL'],
                ] as [$source, $scope, $code])
                    <article class="bg-ink-900 p-6 sm:p-8">
                        <span class="grid size-12 place-items-center bg-brand-sky font-bold text-ink-950">{{ $code }}</span>
                        <h3 class="mt-6 text-xl font-bold text-brand-cream">{{ $source }}</h3>
                        <p class="mt-2 text-brand-sky">{{ $scope }}</p>
                        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.18em] text-brand-coral">Mapping / Normalisasi / Source tracking</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section id="increment" class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
        <div class="grid gap-10 lg:grid-cols-[.7fr_1.3fr]">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.22em] text-brand-coral">Batas fitur</p>
                <h2 class="mt-3 font-serif text-3xl font-bold text-brand-cream">Yang tersedia pada tahap ini.</h2>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['Pencarian lintas format', 'Detail metadata terstruktur', 'Identitas sumber data', 'Tampilan responsif'] as $feature)
                    <div class="flex gap-4 border border-brand-sky/20 bg-ink-900 p-5">
                        <span class="mt-1 size-2 shrink-0 bg-brand-coral"></span>
                        <p class="font-semibold text-brand-cream">{{ $feature }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-app-shell>
