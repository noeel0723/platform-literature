@props(['title' => null, 'inertia' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover novels, comics, manga, and manhwa in one social catalog.">
    <title>{{ $title ? $title.' - ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @if ($inertia)
        @viteReactRefresh
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @if ($inertia)
        <x-inertia::head />
    @endif
</head>
<body class="min-h-screen bg-brand-plate text-brand-blueberry antialiased">
    <a href="#main-content" class="sr-only z-50 bg-ink-950 px-4 py-3 text-brand-cream focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to main content</a>

    @php
        $headerUser = auth()->user();
        $headerProps = [
            'csrf_token' => csrf_token(),
            'is_guest_landing' => request()->routeIs('home') && ! $headerUser,
            'search_query' => request()->routeIs('search.index') ? request('q') : '',
            'routes' => [
                'home' => route('home'),
                'literature' => route('literature.index'),
                'catalog' => route('literatures.index'),
                'search' => route('search.index'),
                'login' => route('login'),
                'register' => route('register'),
                'quick_log_search' => $headerUser ? route('quick-log.literatures') : null,
            ],
            'user' => $headerUser ? [
                'name' => $headerUser->name,
                'username' => $headerUser->username,
                'avatar_url' => $headerUser->avatarUrl(),
                'initials' => Str::of($headerUser->name)->squish()->explode(' ')->filter()->take(2)->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: '?',
                'navigation' => [
                    ['label' => 'Home', 'url' => route('home')],
                    ['label' => 'Profile', 'url' => route('profiles.show', $headerUser)],
                    ['label' => 'Stats', 'url' => route('profiles.stats', $headerUser)],
                    ['label' => 'Activity', 'url' => route('activity.index')],
                    ['label' => 'Literature', 'url' => route('profiles.literature', $headerUser)],
                    ['label' => 'Diary', 'url' => route('diary.index')],
                    ['label' => 'Reviews', 'url' => route('profiles.reviews', $headerUser)],
                    ['label' => 'Readlist', 'url' => route('profiles.readlist', $headerUser)],
                    ['label' => 'Lists', 'url' => route('profiles.lists', $headerUser)],
                    ['label' => 'Connections', 'url' => route('profiles.connections', $headerUser)],
                ],
                'edit_url' => route('profiles.edit'),
                'moderation_url' => $headerUser->isAdmin() ? route('admin.moderation.index') : null,
                'logout_url' => route('logout'),
            ] : null,
        ];
    @endphp
    <script type="application/json" data-react-header-props>{!! Illuminate\Support\Js::encode($headerProps) !!}</script>
    <div data-react-header></div>

    <main id="main-content">
        @if (session('success'))
            <div class="border-b border-brand-blueberry/10 bg-brand-blueberry px-5 py-3 text-center text-sm font-semibold text-brand-plate" role="status">{{ session('success') }}</div>
        @endif
        @error('report')
            <div class="border-b border-red-800/20 bg-red-50 px-5 py-3 text-center text-sm font-semibold text-red-800" role="alert">{{ $message }}</div>
        @enderror
        {{ $slot }}
    </main>

    <footer class="border-t-2 border-brand-coral/60 bg-ink-950 text-brand-cream">
        <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-12">
            <div class="flex flex-col gap-7 md:flex-row md:items-start md:justify-between">
                <div>
                    <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 font-bold text-brand-cream transition hover:text-white" aria-label="Literahaven - Home">
                        <span class="grid grid-cols-2 gap-0.5" aria-hidden="true">
                            <span class="size-2.5 rounded-full bg-brand-plate"></span>
                            <span class="size-2.5 rounded-full bg-brand-sky"></span>
                            <span class="size-2.5 rounded-full bg-brand-coral"></span>
                            <span class="size-2.5 rounded-full bg-brand-sun"></span>
                        </span>
                        <span class="text-xl font-black tracking-[-0.04em]">Literahaven</span>
                    </a>
                    <p class="mt-2 max-w-xs text-sm leading-5 text-brand-cream/55">Activity Feed &amp; Social Literature Discovery.</p>
                </div>
                <nav class="flex flex-wrap gap-x-5 gap-y-3 text-xs font-semibold uppercase tracking-[0.12em] text-brand-cream/65 md:justify-end" aria-label="Footer navigation">
                    <a href="{{ route('home') }}" class="transition hover:text-brand-cream">Home</a>
                    <a href="{{ route('literature.index') }}" class="transition hover:text-brand-cream">Literature</a>
                    <a href="{{ route('literatures.index') }}" class="transition hover:text-brand-cream">Catalog</a>
                    @auth
                        <a href="{{ route('profiles.lists', $headerUser) }}" class="transition hover:text-brand-cream">Lists</a>
                    @endauth
                </nav>
            </div>
            <div class="mt-8 border-t border-brand-cream/15 pt-5 text-xs text-brand-cream/45">
                <p>&copy; {{ now()->year }} Literahaven. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
