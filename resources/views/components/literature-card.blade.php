@props(['literature'])

@php
    $posterTheme = match ($literature['theme']) {
        'coral' => 'from-brand-coral via-brand-cream to-brand-sky text-ink-950',
        'sky' => 'from-brand-sky via-brand-cream to-ink-950 text-ink-950',
        'cream' => 'from-brand-cream via-brand-sky to-brand-cream text-ink-950',
        'deep' => 'from-ink-950 via-brand-cream to-brand-coral text-ink-950',
        'mixed' => 'from-brand-cream via-brand-coral to-brand-sky text-ink-950',
        default => 'from-brand-cream via-brand-sky to-brand-coral text-ink-950',
    };
@endphp

<article class="group min-w-0" data-literature-card>
    <a href="{{ route('literatures.show', $literature['slug']) }}" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral focus-visible:ring-offset-4 focus-visible:ring-offset-brand-cream">
        <div class="poster-shine relative aspect-[2/3] overflow-hidden border border-ink-950/15 bg-linear-to-br {{ $posterTheme }} shadow-[0_10px_28px_rgba(47,58,85,0.10)] transition duration-300 group-hover:-translate-y-1 group-hover:border-brand-coral">
            @if ($literature['cover_url'] !== null)
                <img src="{{ $literature['cover_url'] }}" alt="Cover of {{ $literature['title'] }}" class="absolute inset-0 size-full object-cover" loading="lazy">
                <div class="absolute inset-0 bg-linear-to-t from-ink-950/80 via-transparent to-ink-950/25"></div>
            @else
                <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(115deg,transparent_20%,rgba(255,255,255,.35)_50%,transparent_80%)]"></div>
            @endif
            <div class="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-3 {{ $literature['cover_url'] !== null ? 'text-brand-cream' : '' }}">
                <span class="bg-ink-950/80 px-2 py-1 text-[0.62rem] font-semibold uppercase tracking-[0.14em] text-brand-cream backdrop-blur">{{ $literature['type_label'] }}</span>
                <span class="bg-ink-950/65 px-2 py-1 text-xs font-semibold text-brand-cream backdrop-blur">{{ $literature['year'] }}</span>
            </div>
            <div class="absolute inset-x-0 bottom-0 p-4 {{ $literature['cover_url'] !== null ? 'text-brand-cream' : '' }}">
                @if ($literature['cover_url'] === null)
                    <span class="block font-serif text-4xl font-bold leading-none tracking-tight sm:text-5xl">{{ $literature['initials'] }}</span>
                @endif
                <span class="mt-3 block border-t border-current/40 pt-3 text-xs font-semibold uppercase tracking-[0.18em]">{{ $literature['source'] }}</span>
            </div>
        </div>
        <div class="pt-3">
            <h3 class="truncate font-semibold text-ink-950 transition group-hover:text-brand-coral">{{ $literature['title'] }}</h3>
            <p class="mt-1 truncate text-sm text-ink-950/60">{{ $literature['author'] }}</p>
        </div>
    </a>
</article>
