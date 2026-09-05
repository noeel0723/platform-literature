@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Temukan buku, novel, komik Barat, manga, manhwa, dan light novel dalam satu katalog.">
    <title>{{ $title ? $title.' - ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-cream text-ink-950 antialiased">
    <a href="#main-content" class="sr-only z-50 bg-ink-950 px-4 py-3 text-brand-cream focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Lewati ke konten utama</a>

    <header class="sticky top-0 z-40 border-b border-ink-950/10 bg-brand-cream/95 backdrop-blur-xl">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="flex h-18 min-w-0 items-center gap-4 lg:gap-6">
                <div class="shrink-0"><x-brand-mark /></div>

                <div class="ml-auto hidden min-w-0 items-center gap-5 md:flex">
                    <nav class="hidden items-center gap-5 text-xs font-semibold uppercase tracking-[0.14em] text-ink-950/75 xl:flex" aria-label="Navigasi utama">
                        <a href="{{ route('home') }}" class="whitespace-nowrap transition hover:text-brand-coral">Beranda</a>
                        <a href="{{ route('literatures.index') }}" class="whitespace-nowrap transition hover:text-brand-coral">Katalog</a>
                        <a href="{{ route('home') }}#sources" class="whitespace-nowrap transition hover:text-brand-coral">Sumber</a>
                        @auth
                            <a href="{{ route('diary.index') }}" class="whitespace-nowrap transition hover:text-brand-coral">Diary</a>
                        @endauth
                    </nav>

                    <form action="{{ route('literatures.index') }}" method="GET" role="search" class="flex h-10 w-56 min-w-0 overflow-hidden rounded-full border border-ink-950/20 bg-white/35 lg:w-64 xl:w-72">
                        @if (request()->filled('type'))
                            <input type="hidden" name="type" value="{{ request('type') }}">
                        @endif
                        <label for="catalog-search" class="sr-only">Cari judul, pengarang, atau genre</label>
                        <input id="catalog-search" name="q" value="{{ request('q') }}" placeholder="Cari literatur..." class="min-w-0 flex-1 bg-transparent px-4 text-sm text-ink-950 outline-none placeholder:text-ink-950/45 focus:bg-white/45">
                        <button type="submit" class="grid size-10 shrink-0 place-items-center bg-ink-950 text-brand-cream transition hover:bg-brand-coral hover:text-ink-950" aria-label="Jalankan pencarian">
                            <svg aria-hidden="true" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                        </button>
                    </form>

                    @auth
                        <form action="{{ route('logout') }}" method="POST" class="shrink-0">
                            @csrf
                            <button class="whitespace-nowrap border border-ink-950/20 px-3 py-2 text-xs font-bold uppercase tracking-wider transition hover:bg-ink-950 hover:text-brand-cream">Keluar</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="shrink-0 whitespace-nowrap text-xs font-bold uppercase tracking-wider text-ink-950 hover:text-brand-coral">Masuk</a>
                        <a href="{{ route('register') }}" class="shrink-0 whitespace-nowrap bg-ink-950 px-3 py-2 text-xs font-bold uppercase tracking-wider text-brand-cream transition hover:bg-brand-coral hover:text-ink-950">Daftar</a>
                    @endauth
                </div>

                <button type="button" class="ml-auto grid size-10 shrink-0 place-items-center border border-ink-950/20 bg-transparent text-ink-950 transition hover:bg-ink-950 hover:text-brand-cream md:ml-0 xl:hidden" data-mobile-menu-button aria-expanded="false" aria-controls="mobile-menu">
                    <span class="sr-only">Buka navigasi</span>
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
                </button>
            </div>

            <form action="{{ route('literatures.index') }}" method="GET" role="search" class="mb-4 flex h-11 min-w-0 overflow-hidden rounded-full border border-ink-950/20 bg-white/35 md:hidden">
                @if (request()->filled('type'))
                    <input type="hidden" name="type" value="{{ request('type') }}">
                @endif
                <label for="catalog-search-mobile" class="sr-only">Cari judul, pengarang, atau genre</label>
                <input id="catalog-search-mobile" name="q" value="{{ request('q') }}" placeholder="Cari judul, pengarang, atau genre..." class="min-w-0 flex-1 bg-transparent px-4 text-sm text-ink-950 outline-none placeholder:text-ink-950/45 focus:bg-white/45">
                <button type="submit" class="grid w-12 shrink-0 place-items-center bg-ink-950 text-brand-cream transition hover:bg-brand-coral hover:text-ink-950" aria-label="Jalankan pencarian">
                    <svg aria-hidden="true" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                </button>
            </form>
        </div>
        <nav id="mobile-menu" class="hidden border-t border-ink-950/10 bg-brand-cream px-5 py-4 xl:hidden" data-mobile-menu aria-label="Navigasi seluler">
            <div class="mx-auto grid max-w-7xl gap-1 text-sm font-semibold uppercase tracking-[0.14em] text-ink-950 sm:px-3 lg:px-5">
                <a href="{{ route('home') }}" class="px-3 py-3 transition hover:bg-brand-sky/25">Beranda</a>
                <a href="{{ route('literatures.index') }}" class="px-3 py-3 transition hover:bg-brand-sky/25">Katalog</a>
                <a href="{{ route('home') }}#sources" class="px-3 py-3 transition hover:bg-brand-sky/25">Sumber API</a>
                @auth
                    <a href="{{ route('diary.index') }}" class="px-3 py-3 transition hover:bg-brand-sky/25">Personal Diary</a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button class="w-full px-3 py-3 text-left transition hover:bg-brand-sky/25">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-3 transition hover:bg-brand-sky/25">Masuk</a>
                    <a href="{{ route('register') }}" class="px-3 py-3 transition hover:bg-brand-sky/25">Daftar</a>
                @endauth
            </div>
        </nav>
    </header>

    <main id="main-content">
        @if (session('success'))
            <div class="border-b border-ink-950/10 bg-ink-950 px-5 py-3 text-center text-sm font-semibold text-brand-cream" role="status">{{ session('success') }}</div>
        @endif
        {{ $slot }}
    </main>

    <footer class="border-t border-ink-950/10">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-9 text-sm text-ink-950/60 sm:px-8 md:flex-row md:justify-between lg:px-10">
            <p>Literature Social Discovery</p>
            <p>Increment 2 - Reading Management dan Personal Diary.</p>
        </div>
    </footer>
</body>
</html>
