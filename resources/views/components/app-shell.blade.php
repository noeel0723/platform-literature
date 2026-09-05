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
        <div class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-6 px-5 sm:px-8 lg:px-10">
            <x-brand-mark />
            <nav class="hidden items-center gap-7 text-sm font-semibold uppercase tracking-[0.16em] text-brand-sky lg:flex" aria-label="Navigasi utama">
                <a href="{{ route('home') }}" class="transition hover:text-brand-cream">Beranda</a>
                <a href="{{ route('literatures.index') }}" class="transition hover:text-brand-cream">Katalog</a>
                <a href="{{ route('home') }}#sources" class="transition hover:text-brand-cream">Sumber</a>
                <a href="{{ route('home') }}#increment" class="transition hover:text-brand-cream">Increment 1</a>
            </nav>
            <button type="button" class="grid size-10 place-items-center border border-brand-sky/20 bg-ink-900 text-brand-sky lg:hidden" data-mobile-menu-button aria-expanded="false" aria-controls="mobile-menu">
                <span class="sr-only">Buka navigasi</span>
                <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
            </button>
        </div>
        <nav id="mobile-menu" class="hidden border-t border-brand-sky/15 bg-ink-900 px-5 py-4 lg:hidden" data-mobile-menu aria-label="Navigasi seluler">
            <div class="grid gap-1 text-sm font-semibold uppercase tracking-[0.14em] text-brand-sky">
                <a href="{{ route('home') }}" class="px-3 py-3 hover:bg-brand-primary hover:text-white">Beranda</a>
                <a href="{{ route('literatures.index') }}" class="px-3 py-3 hover:bg-brand-primary hover:text-white">Katalog</a>
                <a href="{{ route('home') }}#sources" class="px-3 py-3 hover:bg-brand-primary hover:text-white">Sumber API</a>
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
