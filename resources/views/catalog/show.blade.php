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

    <section class="catalog-grid relative overflow-hidden border-b border-ink-950/10">
        <div class="absolute inset-x-0 top-0 h-72 bg-linear-to-b from-white/35 to-transparent"></div>
        <div class="relative mx-auto max-w-7xl px-5 pb-16 pt-10 sm:px-8 lg:px-10 lg:pb-20 lg:pt-16">
            <a href="{{ route('literatures.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-ink-950/75 transition hover:text-brand-coral">
                <span aria-hidden="true">&larr;</span> Kembali ke katalog
            </a>

            <div class="mt-10 grid gap-8 md:grid-cols-[220px_1fr] lg:grid-cols-[250px_1fr_280px] lg:items-end">
                <div class="relative aspect-[2/3] overflow-hidden border border-ink-950/20 bg-linear-to-br {{ $coverTheme }} shadow-2xl">
                    @if ($literature['cover_url'] !== null)
                        <img src="{{ $literature['cover_url'] }}" alt="Sampul {{ $literature['title'] }}" class="absolute inset-0 size-full object-cover">
                        <div class="absolute inset-0 bg-linear-to-t from-ink-950/80 via-transparent to-ink-950/25"></div>
                    @else
                        <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(115deg,transparent_20%,rgba(255,255,255,.35)_50%,transparent_80%)]"></div>
                    @endif
                    <div class="absolute inset-x-0 top-0 flex justify-between p-4 text-xs font-bold uppercase tracking-wider {{ $literature['cover_url'] !== null ? 'text-brand-cream' : '' }}">
                        <span>{{ $literature['type_label'] }}</span>
                        <span>{{ $literature['year'] }}</span>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 p-6 {{ $literature['cover_url'] !== null ? 'text-brand-cream' : '' }}">
                        @if ($literature['cover_url'] === null)
                            <span class="font-serif text-6xl font-bold leading-none">{{ $literature['initials'] }}</span>
                        @endif
                        <p class="mt-4 border-t border-current/40 pt-4 text-xs font-bold uppercase tracking-[0.18em]">{{ $literature['source'] }}</p>
                    </div>
                </div>

                <div class="pb-2">
                    <div class="flex flex-wrap items-center gap-3 text-sm font-semibold uppercase tracking-wider text-ink-950/60">
                        <span class="bg-brand-coral px-2.5 py-1 text-ink-950">{{ $literature['type_label'] }}</span>
                        <span>{{ $literature['year'] }}</span>
                    </div>
                    <h1 class="mt-4 font-serif text-5xl font-bold leading-none tracking-tight text-ink-950 sm:text-6xl">{{ $literature['title'] }}</h1>
                    @if ($literature['edition_title'])
                        <p class="mt-3 text-sm text-ink-950/55">Judul edisi: <span class="font-semibold text-ink-950/75">{{ $literature['edition_title'] }}</span></p>
                    @endif
                    <p class="mt-4 text-lg text-ink-950/60">Oleh <span class="font-semibold text-ink-950">{{ $literature['author'] }}</span></p>
                    <p class="mt-7 max-w-2xl text-lg font-medium uppercase leading-7 tracking-[0.08em] text-ink-950/70">{{ $literature['tagline'] }}</p>
                    <p class="mt-5 max-w-2xl text-base leading-8 text-ink-950/70">{{ $literature['synopsis'] }}</p>
                </div>

                <aside class="border border-ink-950/10 bg-white/40 lg:mb-2" aria-label="Status katalog">
                    <div class="border-b border-ink-950/10 p-5">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-ink-950/60">Status katalog</p>
                        <div class="mt-4 flex items-center gap-3">
                            <span class="grid size-10 place-items-center bg-ink-950 font-bold text-brand-cream">OK</span>
                            <div>
                                <p class="font-bold text-ink-950">Metadata tersedia</p>
                                <p class="text-sm text-ink-950/60">Siap ditampilkan</p>
                            </div>
                        </div>
                    </div>
                    <dl class="grid gap-4 p-5 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-ink-950/60">Sumber</dt><dd class="font-semibold text-ink-950">{{ $literature['source'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-950/60">Format</dt><dd class="font-semibold text-ink-950">{{ $literature['format'] }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-ink-950/60">Bahasa</dt><dd class="font-semibold text-ink-950">{{ $literature['language'] }}</dd></div>
                    </dl>
                    <a href="{{ route('literatures.index') }}" class="block bg-ink-950 px-5 py-4 text-center font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Cari karya lain</a>
                </aside>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:px-10 lg:py-20">
        <nav class="flex gap-6 overflow-x-auto border-b border-ink-950/10 text-sm font-bold uppercase tracking-[0.14em] text-ink-950/60" aria-label="Bagian detail">
            <a href="#summary" class="border-b-2 border-brand-coral pb-4 text-ink-950">Ringkasan</a>
            <a href="#authors" class="pb-4 text-ink-950/70 hover:text-brand-coral">Pengarang</a>
            <a href="#details" class="pb-4 text-ink-950/70 hover:text-brand-coral">Detail</a>
            <a href="#genres" class="pb-4 text-ink-950/70 hover:text-brand-coral">Genre</a>
            <a href="#readlist" class="pb-4 text-ink-950/70 hover:text-brand-coral">Readlist</a>
        </nav>

        <div class="mt-10 grid gap-10 lg:grid-cols-[1.25fr_.75fr]">
            <div id="summary" class="border border-ink-950/10 bg-white/40 p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Tentang karya</p>
                <h2 class="mt-3 font-serif text-3xl font-bold text-ink-950">Ringkasan metadata</h2>
                <p class="mt-5 max-w-3xl text-base leading-8 text-ink-950/70">{{ $literature['synopsis'] }}</p>
                @if ($literature['synopsis_source_name'] && $literature['synopsis_source_url'])
                    <p class="mt-4 text-xs leading-5 text-ink-950/50">
                        Ringkasan pelengkap dari
                        <a href="{{ $literature['synopsis_source_url'] }}" target="_blank" rel="noopener noreferrer" class="font-semibold underline decoration-brand-coral underline-offset-4">{{ $literature['synopsis_source_name'] }}</a>
                        dengan lisensi <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4">CC BY-SA</a>.
                    </p>
                @endif

                <div id="authors" class="mt-10 border-t border-ink-950/10 pt-7">
                    <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-ink-950/60">Pengarang dan kreator</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($literature['authors'] as $author)
                            <span class="bg-brand-sky/25 px-3 py-2 font-semibold text-ink-950">{{ $author }}</span>
                        @endforeach
                    </div>
                </div>

                <div id="genres" class="mt-8 border-t border-ink-950/10 pt-7">
                    <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-ink-950/60">Genre</h3>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($literature['genres'] as $genre)
                            <span class="border border-ink-950/15 px-3 py-2 text-ink-950/70">{{ $genre }}</span>
                        @endforeach
                    </div>
                </div>
            </div>

            <aside id="details" class="h-fit border border-ink-950/10 bg-white/40 p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Source tracking</p>
                <h2 class="mt-3 font-serif text-2xl font-bold text-ink-950">Detail katalog</h2>
                <dl class="mt-7 grid gap-5 text-sm">
                    <div class="border-b border-ink-950/10 pb-4"><dt class="text-ink-950/60">Penerbit</dt><dd class="mt-1 font-semibold text-ink-950">{{ $literature['publisher'] }}</dd></div>
                    <div class="border-b border-ink-950/10 pb-4"><dt class="text-ink-950/60">Identifier eksternal</dt><dd class="mt-1 font-semibold text-ink-950">{{ $literature['identifier'] }}</dd></div>
                    <div class="border-b border-ink-950/10 pb-4"><dt class="text-ink-950/60">Sumber metadata</dt><dd class="mt-1 font-semibold text-ink-950">{{ $literature['source'] }}</dd></div>
                    <div><dt class="text-ink-950/60">Tahap implementasi</dt><dd class="mt-1 font-semibold text-ink-950">Katalog internal MySQL</dd></div>
                </dl>
            </aside>
        </div>
    </section>

    <section id="readlist" class="border-t border-ink-950/10 bg-white/20">
        <div class="mx-auto grid max-w-7xl gap-8 px-5 py-14 sm:px-8 lg:grid-cols-[.7fr_1.3fr] lg:px-10 lg:py-20">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-coral">Increment 2</p>
                <h2 class="mt-3 font-serif text-4xl font-bold text-ink-950">Kelola bacaanmu</h2>
                <p class="mt-4 max-w-md leading-7 text-ink-950/65">Simpan status, catat halaman atau bab terakhir, dan tambahkan catatan singkat. Setiap perubahan akan masuk ke Personal Diary secara otomatis.</p>
                @auth
                    <a href="{{ route('diary.index') }}" class="mt-6 inline-flex font-bold text-ink-950 underline decoration-brand-coral decoration-2 underline-offset-4">Buka Personal Diary</a>
                @endauth
            </div>

            @auth
                <form action="{{ route('reading-list.update', $literature['slug']) }}" method="POST" class="grid gap-5 border border-ink-950/15 bg-brand-cream/65 p-6 sm:grid-cols-2 sm:p-8">
                    @csrf
                    @method('PUT')

                    <div class="sm:col-span-2">
                        <label for="status" class="text-sm font-bold text-ink-950">Status bacaan</label>
                        <select id="status" name="status" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                            @foreach ($readingStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $readingList?->status ?? 'want_to_read') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="progress_value" class="text-sm font-bold text-ink-950">Progres saat ini</label>
                        <input id="progress_value" name="progress_value" type="number" min="0" value="{{ old('progress_value', $readingList?->progress?->current_value) }}" placeholder="Contoh: 120" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                        @error('progress_value') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="progress_total" class="text-sm font-bold text-ink-950">Total</label>
                        <input id="progress_total" name="progress_total" type="number" min="1" value="{{ old('progress_total', $readingList?->progress?->total_value) }}" placeholder="Contoh: 320" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                        @error('progress_total') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label for="progress_unit" class="text-sm font-bold text-ink-950">Satuan progres</label>
                        <select id="progress_unit" name="progress_unit" class="mt-2 w-full border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">
                            @foreach (['page' => 'Halaman', 'chapter' => 'Bab', 'percent' => 'Persen'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('progress_unit', $readingList?->progress?->unit ?? 'page') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label for="note" class="text-sm font-bold text-ink-950">Catatan aktivitas <span class="font-normal text-ink-950/50">(opsional)</span></label>
                        <textarea id="note" name="note" rows="3" maxlength="1000" placeholder="Tuliskan kesan atau pengingat singkat..." class="mt-2 w-full resize-y border border-ink-950/20 bg-white/60 px-4 py-3 outline-none focus:border-brand-coral">{{ old('note') }}</textarea>
                        @error('note') <p class="mt-2 text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    </div>

                    @if ($readingList)
                        <label class="flex items-start gap-3 text-sm leading-6 text-ink-950/70 sm:col-span-2">
                            <input name="reread" type="checkbox" value="1" class="mt-1 size-4 accent-brand-coral">
                            Mulai baca ulang. Progres akan kembali ke 0 dan jumlah baca ulang bertambah.
                        </label>
                    @endif

                    <button class="bg-ink-950 px-5 py-3.5 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950 sm:col-span-2">Simpan ke Readlist</button>
                </form>
            @else
                <div class="border border-ink-950/15 bg-brand-cream/65 p-7 sm:p-9">
                    <p class="font-serif text-2xl font-bold text-ink-950">Masuk untuk mencatat bacaan</p>
                    <p class="mt-3 leading-7 text-ink-950/65">Katalog tetap dapat dijelajahi tanpa akun. Akun diperlukan agar status dan progres tersimpan secara pribadi.</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('login') }}" class="bg-ink-950 px-5 py-3 font-bold text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Masuk</a>
                        <a href="{{ route('register') }}" class="border border-ink-950/20 px-5 py-3 font-bold text-ink-950 transition hover:border-brand-coral">Buat akun</a>
                    </div>
                </div>
            @endauth
        </div>
    </section>
</x-app-shell>
