@props(['literature', 'compact' => false])

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

<article class="group min-w-0" data-literature-card @if ($compact) data-literature-card-size="compact" @endif>
    <a href="{{ route('literatures.show', $literature['slug']) }}" class="block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-coral focus-visible:ring-offset-4 focus-visible:ring-offset-brand-cream">
        <div class="poster-shine relative aspect-[2/3] overflow-hidden border border-ink-950/15 bg-linear-to-br {{ $posterTheme }} shadow-[0_10px_28px_rgba(47,58,85,0.10)] transition duration-300 group-hover:-translate-y-1 group-hover:border-brand-coral">
            @if ($literature['cover_url'] !== null)
                <img src="{{ $literature['cover_url'] }}" alt="Cover of {{ $literature['title'] }}" class="absolute inset-0 size-full object-cover" loading="lazy">
                <div class="absolute inset-0 bg-linear-to-t from-ink-950/80 via-transparent to-ink-950/25"></div>
            @else
                <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(115deg,transparent_20%,rgba(255,255,255,.35)_50%,transparent_80%)]"></div>
            @endif
            <div class="absolute inset-x-0 top-0 flex items-start justify-between {{ $compact ? 'gap-1 p-1.5' : 'gap-2 p-3' }} {{ $literature['cover_url'] !== null ? 'text-brand-cream' : '' }}">
                <span class="bg-ink-950/80 font-semibold uppercase text-brand-cream backdrop-blur {{ $compact ? 'px-1.5 py-1 text-[0.5rem] tracking-[0.1em]' : 'px-2 py-1 text-[0.62rem] tracking-[0.14em]' }}">{{ $literature['type_label'] }}</span>
                <span class="bg-ink-950/65 font-semibold text-brand-cream backdrop-blur {{ $compact ? 'px-1.5 py-1 text-[0.55rem]' : 'px-2 py-1 text-xs' }}">{{ $literature['year'] }}</span>
            </div>
            <div class="absolute inset-x-0 bottom-0 {{ $compact ? 'p-2' : 'p-4' }} {{ $literature['cover_url'] !== null ? 'text-brand-cream' : '' }}">
                @if ($literature['cover_url'] === null)
                    <span class="block font-serif font-bold leading-none tracking-tight {{ $compact ? 'text-2xl' : 'text-4xl sm:text-5xl' }}">{{ $literature['initials'] }}</span>
                @endif
                <span class="block border-t border-current/40 font-semibold uppercase {{ $compact ? 'mt-1.5 truncate pt-1.5 text-[0.5rem] tracking-[0.1em]' : 'mt-3 pt-3 text-xs tracking-[0.18em]' }}">{{ $literature['source'] }}</span>
            </div>
        </div>
        <div class="{{ $compact ? 'pt-2' : 'pt-3' }}">
            <h3 class="truncate font-semibold text-ink-950 transition group-hover:text-brand-coral {{ $compact ? 'text-sm leading-5' : '' }}">{{ $literature['title'] }}</h3>
            <p class="truncate text-ink-950/60 {{ $compact ? 'mt-0.5 text-xs' : 'mt-1 text-sm' }}">{{ $literature['author'] }}</p>
        </div>
    </a>
</article>
