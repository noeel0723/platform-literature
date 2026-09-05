@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Temukan buku, komik Barat, manga, dan light novel dalam satu katalog.">
    <title>{{ $title ? $title.' - ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 text-brand-cream antialiased">
    <a href="#main-content" class="sr-only z-50 bg-brand-cream px-4 py-3 text-ink-950 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten utama</a>

    <header class="sticky top-0 z-40 border-b border-brand-sky/15 bg-ink-950/95 backdrop-blur-xl">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="flex h-18 min-w-0 items-center gap-4 lg:gap-6">
                <div class="shrink-0"><x-brand-mark /></div>

                <div class="ml-auto hidden min-w-0 items-center gap-5 md:flex">
                    <nav class="hidden items-center gap-5 text-xs font-semibold uppercase tracking-[0.14em] text-brand-cream/80 xl:flex" aria-label="Navigasi utama">
                        <a href="{{ route('home') }}" class="whitespace-nowrap transition hover:text-brand-sky">Beranda</a>
                        <a href="{{ route('literatures.index') }}" class="whitespace-nowrap transition hover:text-brand-sky">Katalog</a>
                        <a href="{{ route('home') }}#sources" class="whitespace-nowrap transition hover:text-brand-sky">Sumber</a>
                        <a href="{{ route('home') }}#increment" class="whitespace-nowrap transition hover:text-brand-sky">Increment 1</a>
                    </nav>

                    <form action="{{ route('literatures.index') }}" method="GET" role="search" class="flex h-10 w-56 min-w-0 overflow-hidden rounded-full border border-brand-cream/25 bg-ink-900 lg:w-64 xl:w-72">
                        @if (request()->filled('type'))
                            <input type="hidden" name="type" value="{{ request('type') }}">
                        @endif
                        <label for="catalog-search" class="sr-only">Cari judul, pengarang, atau genre</label>
                        <input id="catalog-search" name="q" value="{{ request('q') }}" placeholder="Cari literatur..." class="min-w-0 flex-1 bg-transparent px-4 text-sm text-brand-cream outline-none placeholder:text-brand-sky/55 focus:bg-ink-800">
                        <button type="submit" class="grid size-10 shrink-0 place-items-center bg-brand-cream text-ink-950 transition hover:bg-brand-sky" aria-label="Jalankan pencarian">
                            <svg aria-hidden="true" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                        </button>
                    </form>
                </div>

                <button type="button" class="ml-auto grid size-10 shrink-0 place-items-center border border-brand-cream/25 bg-ink-900 text-brand-cream transition hover:bg-brand-cream hover:text-ink-950 md:ml-0 xl:hidden" data-mobile-menu-button aria-expanded="false" aria-controls="mobile-menu">
                    <span class="sr-only">Buka navigasi</span>
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
                </button>
            </div>

            <form action="{{ route('literatures.index') }}" method="GET" role="search" class="mb-4 flex h-11 min-w-0 overflow-hidden rounded-full border border-brand-cream/25 bg-ink-900 md:hidden">
                @if (request()->filled('type'))
                    <input type="hidden" name="type" value="{{ request('type') }}">
                @endif
                <label for="catalog-search-mobile" class="sr-only">Cari judul, pengarang, atau genre</label>
                <input id="catalog-search-mobile" name="q" value="{{ request('q') }}" placeholder="Cari judul, pengarang, atau genre..." class="min-w-0 flex-1 bg-transparent px-4 text-sm text-brand-cream outline-none placeholder:text-brand-sky/55 focus:bg-ink-800">
                <button type="submit" class="grid w-12 shrink-0 place-items-center bg-brand-cream text-ink-950 transition hover:bg-brand-sky" aria-label="Jalankan pencarian">
                    <svg aria-hidden="true" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                </button>
            </form>
        </div>
        <nav id="mobile-menu" class="hidden border-t border-brand-sky/15 bg-ink-900 px-5 py-4 xl:hidden" data-mobile-menu aria-label="Navigasi seluler">
            <div class="mx-auto grid max-w-7xl gap-1 text-sm font-semibold uppercase tracking-[0.14em] text-brand-cream sm:px-3 lg:px-5">
                <a href="{{ route('home') }}" class="px-3 py-3 transition hover:bg-brand-cream hover:text-ink-950">Beranda</a>
                <a href="{{ route('literatures.index') }}" class="px-3 py-3 transition hover:bg-brand-cream hover:text-ink-950">Katalog</a>
                <a href="{{ route('home') }}#sources" class="px-3 py-3 transition hover:bg-brand-cream hover:text-ink-950">Sumber API</a>
                <a href="{{ route('home') }}#increment" class="px-3 py-3 transition hover:bg-brand-cream hover:text-ink-950">Increment 1</a>
            </div>
        </nav>
    </header>

    <main id="main-content">{{ $slot }}</main>

    <footer class="border-t border-brand-sky/15">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-9 text-sm text-brand-sky sm:px-8 md:flex-row md:justify-between lg:px-10">
            <p>Literature Social Discovery</p>
            <p>Increment 1 - Fondasi sistem dan katalog terintegrasi.</p>
        </div>
    </footer>
</body>
</html>
