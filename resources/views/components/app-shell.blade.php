@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover books, novels, Western comics, manga, manhwa, and light novels in one social catalog.">
    <title>{{ $title ? $title.' - ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-plate text-brand-blueberry antialiased">
    <a href="#main-content" class="sr-only z-50 bg-ink-950 px-4 py-3 text-brand-cream focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to main content</a>

    <header class="sticky top-0 z-40 border-b border-brand-blueberry/15 bg-brand-stem/95 text-brand-plate backdrop-blur-xl">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="flex h-18 min-w-0 items-center gap-4 lg:gap-6">
                <div class="shrink-0"><x-brand-mark /></div>

                <div class="ml-auto hidden min-w-0 items-center gap-5 md:flex">
                    <nav class="hidden items-center gap-5 text-xs font-semibold uppercase tracking-[0.14em] text-brand-plate/85 xl:flex" aria-label="Main navigation">
                        <a href="{{ route('home') }}" class="whitespace-nowrap transition hover:text-white">Home</a>
                        <a href="{{ route('literatures.index') }}" class="whitespace-nowrap transition hover:text-white">Catalog</a>
                        @auth
                            <a href="{{ route('profiles.show', auth()->user()) }}" class="whitespace-nowrap transition hover:text-white">Profile</a>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.moderation.index') }}" class="whitespace-nowrap transition hover:text-white">Moderation</a>
                            @endif
                        @endauth
                    </nav>

                    <form action="{{ route('literatures.index') }}" method="GET" role="search" class="flex h-10 w-56 min-w-0 overflow-hidden rounded-full border border-brand-plate/30 bg-brand-plate lg:w-64 xl:w-72">
                        @if (request()->filled('type'))
                            <input type="hidden" name="type" value="{{ request('type') }}">
                        @endif
                        <label for="catalog-search" class="sr-only">Search by title, author, or genre</label>
                        <input id="catalog-search" name="q" value="{{ request('q') }}" placeholder="Search literature..." class="min-w-0 flex-1 bg-transparent px-4 text-sm text-ink-950 outline-none placeholder:text-ink-950/45 focus:bg-white/45">
                        <button type="submit" class="grid size-10 shrink-0 place-items-center bg-brand-blueberry text-brand-plate transition hover:bg-brand-berry" aria-label="Search">
                            <svg aria-hidden="true" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                        </button>
                    </form>

                    @auth
                        <form action="{{ route('logout') }}" method="POST" class="shrink-0">
                            @csrf
                            <button class="whitespace-nowrap border border-brand-plate/35 px-3 py-2 text-xs font-bold uppercase tracking-wider text-brand-plate transition hover:border-brand-plate hover:bg-brand-plate hover:text-brand-blueberry">Log out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="shrink-0 whitespace-nowrap text-xs font-bold uppercase tracking-wider text-brand-plate/85 hover:text-white">Log in</a>
                        <a href="{{ route('register') }}" class="shrink-0 whitespace-nowrap bg-brand-blueberry px-3 py-2 text-xs font-bold uppercase tracking-wider text-brand-plate transition hover:bg-brand-berry">Join</a>
                    @endauth
                </div>

                <button type="button" class="ml-auto grid size-10 shrink-0 place-items-center border border-brand-plate/35 bg-transparent text-brand-plate transition hover:bg-brand-plate hover:text-brand-blueberry md:ml-0 xl:hidden" data-mobile-menu-button aria-expanded="false" aria-controls="mobile-menu">
                    <span class="sr-only">Open navigation</span>
                    <svg aria-hidden="true" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"></path></svg>
                </button>
            </div>

            <form action="{{ route('literatures.index') }}" method="GET" role="search" class="mb-4 flex h-11 min-w-0 overflow-hidden rounded-full border border-brand-plate/30 bg-brand-plate md:hidden">
                @if (request()->filled('type'))
                    <input type="hidden" name="type" value="{{ request('type') }}">
                @endif
                <label for="catalog-search-mobile" class="sr-only">Search by title, author, or genre</label>
                <input id="catalog-search-mobile" name="q" value="{{ request('q') }}" placeholder="Search by title, author, or genre..." class="min-w-0 flex-1 bg-transparent px-4 text-sm text-ink-950 outline-none placeholder:text-ink-950/45 focus:bg-white/45">
                <button type="submit" class="grid w-12 shrink-0 place-items-center bg-brand-blueberry text-brand-plate transition hover:bg-brand-berry" aria-label="Search">
                    <svg aria-hidden="true" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                </button>
            </form>
        </div>
        <nav id="mobile-menu" class="hidden border-t border-brand-plate/15 bg-brand-stem px-5 py-4 xl:hidden" data-mobile-menu aria-label="Mobile navigation">
            <div class="mx-auto grid max-w-7xl gap-1 text-sm font-semibold uppercase tracking-[0.14em] text-brand-plate sm:px-3 lg:px-5">
                <a href="{{ route('home') }}" class="px-3 py-3 transition hover:bg-brand-plate/10">Home</a>
                <a href="{{ route('literatures.index') }}" class="px-3 py-3 transition hover:bg-brand-plate/10">Catalog</a>
                @auth
                    <a href="{{ route('profiles.show', auth()->user()) }}" class="px-3 py-3 transition hover:bg-brand-plate/10">My Profile</a>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.moderation.index') }}" class="px-3 py-3 transition hover:bg-brand-plate/10">Moderation</a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button class="w-full px-3 py-3 text-left transition hover:bg-brand-plate/10">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="px-3 py-3 transition hover:bg-brand-plate/10">Log in</a>
                    <a href="{{ route('register') }}" class="px-3 py-3 transition hover:bg-brand-plate/10">Join</a>
                @endauth
            </div>
        </nav>
    </header>

    <main id="main-content">
        @if (session('success'))
            <div class="border-b border-brand-blueberry/10 bg-brand-blueberry px-5 py-3 text-center text-sm font-semibold text-brand-plate" role="status">{{ session('success') }}</div>
        @endif
        @error('report')
            <div class="border-b border-red-800/20 bg-red-50 px-5 py-3 text-center text-sm font-semibold text-red-800" role="alert">{{ $message }}</div>
        @enderror
        {{ $slot }}
    </main>

    <footer class="border-t border-brand-blueberry/10 bg-brand-yogurt/45">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-9 text-sm text-ink-950/60 sm:px-8 md:flex-row md:justify-between lg:px-10">
            <p>Literahaven</p>
            <p>Activity Feed &amp; Social Literature Discovery.</p>
        </div>
    </footer>
</body>
</html>
