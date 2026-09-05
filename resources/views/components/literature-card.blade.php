@props(['literature'])

@php
    $posterTheme = match ($literature['theme']) {
        'coral' => 'from-brand-coral via-brand-cream to-brand-sky text-ink-950',
        'sky' => 'from-brand-sky via-brand-primary to-ink-950 text-brand-cream',
        'cream' => 'from-brand-cream via-brand-sky to-brand-primary text-ink-950',
        'deep' => 'from-ink-950 via-brand-primary to-brand-coral text-brand-cream',
        'mixed' => 'from-brand-primary via-brand-coral to-brand-cream text-brand-cream',
        default => 'from-brand-primary via-brand-sky to-brand-cream text-brand-cream',
    };
@endphp

<article class="group min-w-0">
    <a href="{{ route('literatures.show', $literature['slug']) }}" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral focus-visible:ring-offset-4 focus-visible:ring-offset-ink-950">
        <div class="poster-shine relative aspect-[2/3] overflow-hidden border border-brand-sky/25 bg-linear-to-br {{ $posterTheme }} shadow-[0_20px_50px_rgba(0,0,0,0.28)] transition duration-300 group-hover:-translate-y-1 group-hover:border-brand-coral">
            <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(115deg,transparent_20%,rgba(255,255,255,.35)_50%,transparent_80%)]"></div>
            <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-3">
                <span class="bg-ink-950/80 px-2 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-brand-cream backdrop-blur">{{ $literature['type_label'] }}</span>
                <span class="text-xs font-semibold opacity-75">{{ $literature['year'] }}</span>
            </div>
            <div class="absolute inset-x-0 bottom-0 p-4">
                <span class="block font-serif text-4xl font-bold leading-none tracking-tight sm:text-5xl">{{ $literature['initials'] }}</span>
                <span class="mt-3 block border-t border-current/40 pt-3 text-xs font-semibold uppercase tracking-[0.18em]">{{ $literature['source'] }}</span>
            </div>
        </div>
        <div class="pt-3">
            <h3 class="truncate font-semibold text-brand-cream transition group-hover:text-brand-coral">{{ $literature['title'] }}</h3>
            <p class="mt-1 truncate text-sm text-brand-sky">{{ $literature['author'] }}</p>
        </div>
    </a>
</article>
