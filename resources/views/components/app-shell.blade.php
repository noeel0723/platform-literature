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
            'search_query' => request()->routeIs('search.index') ? request('q') : '',
            'routes' => [
                'home' => route('home'),
                'catalog' => route('literatures.index'),
                'search' => route('search.index'),
                'login' => route('login'),
                'register' => route('register'),
            ],
            'user' => $headerUser ? [
                'name' => $headerUser->name,
                'username' => $headerUser->username,
                'avatar_url' => $headerUser->avatarUrl(),
                'initials' => Str::of($headerUser->name)->squish()->explode(' ')->filter()->take(2)->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))->implode('') ?: '?',
                'navigation' => [
                    ['label' => 'Profile', 'url' => route('profiles.show', $headerUser)],
                    ['label' => 'Activity', 'url' => route('activity.index')],
                    ['label' => 'Literature', 'url' => route('profiles.literature', $headerUser)],
                    ['label' => 'Reviews', 'url' => route('profiles.reviews', $headerUser)],
                    ['label' => 'Readlist', 'url' => route('profiles.readlist', $headerUser)],
                ],
                'edit_url' => route('profiles.edit'),
                'moderation_url' => $headerUser->isAdmin() ? route('admin.moderation.index') : null,
                'logout_url' => route('logout'),
            ] : null,
        ];
    @endphp
    <script type="application/json" data-react-header-props>{{ Illuminate\Support\Js::encode($headerProps) }}</script>
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

    @auth
        <x-quick-log />
    @endauth

    <footer class="border-t border-brand-blueberry/10 bg-brand-yogurt/45">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-5 py-9 text-sm text-ink-950/60 sm:px-8 md:flex-row md:justify-between lg:px-10">
            <p>Literahaven</p>
            <p>Activity Feed &amp; Social Literature Discovery.</p>
        </div>
    </footer>
</body>
</html>
