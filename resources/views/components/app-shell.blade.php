@props(['title' => null, 'inertia' => false])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover novels, comics, manga, and manhwa in one social catalog.">
    <link rel="icon" type="image/png" href="{{ asset('literahaven-logo.png') }}">
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
            'logo_url' => asset('literahaven-logo.png'),
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
                'password_request' => route('password.request'),
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
        @error('report')
            <div class="border-b border-red-800/20 bg-red-50 px-5 py-3 text-center text-sm font-semibold text-red-800" role="alert">{{ $message }}</div>
        @enderror
        {{ $slot }}
    </main>

    <footer class="border-t-2 border-brand-coral/60 bg-ink-950 text-brand-cream">
        <div class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10 lg:py-12">
            <section aria-labelledby="footer-data-sources-heading">
                <h2 id="footer-data-sources-heading" class="text-[0.65rem] font-bold uppercase tracking-[0.16em] text-brand-coral">Data sources</h2>
                <p class="mt-1.5 text-xs leading-5 text-brand-cream/55">Literature information and supporting metadata are brought together from these services.</p>
                @php
                    $footerSources = [
                        'Literature catalog' => [
                            'Hardcover' => 'https://hardcover.app/',
                            'Google Books' => 'https://books.google.com/',
                            'AniList' => 'https://anilist.co/',
                            'Kitsu' => 'https://kitsu.io/',
                            'Comic Vine' => 'https://comicvine.gamespot.com/',
                        ],
                        'Supporting metadata' => [
                            'MangaUpdates' => 'https://www.mangaupdates.com/',
                            'Wikidata' => 'https://www.wikidata.org/',
                            'Wikipedia' => 'https://www.wikipedia.org/',
                            'Google Knowledge Graph' => 'https://developers.google.com/knowledge-graph',
                        ],
                    ];
                @endphp
                <div class="mt-4 grid gap-5 md:grid-cols-2 md:gap-8">
                    @foreach ($footerSources as $category => $sources)
                        <div class="min-w-0">
                            <h3 class="text-[0.6rem] font-semibold uppercase tracking-[0.12em] text-brand-cream/50">{{ $category }}</h3>
                            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-2 text-xs font-medium text-brand-cream/75">
                                @foreach ($sources as $name => $url)
                                    <li>
                                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="rounded-sm transition hover:text-brand-sky focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-sky">
                                            {{ $name }}<span class="sr-only"> (opens in a new tab)</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </section>
            <div class="mt-6 border-t border-brand-cream/15 pt-4 text-xs text-brand-cream/45">
                <p>&copy; {{ now()->year }} Literahaven. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
