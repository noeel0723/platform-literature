@props(['level' => 2])

@if ((int) $level === 3)
    <h3 {{ $attributes->class(['font-serif text-sm font-semibold uppercase tracking-[0.14em] text-ink-950']) }}>{{ $slot }}</h3>
@elseif ((int) $level === 4)
    <h4 {{ $attributes->class(['font-serif text-sm font-semibold uppercase tracking-[0.14em] text-ink-950']) }}>{{ $slot }}</h4>
@else
    <h2 {{ $attributes->class(['font-serif text-sm font-semibold uppercase tracking-[0.14em] text-ink-950']) }}>{{ $slot }}</h2>
@endif
