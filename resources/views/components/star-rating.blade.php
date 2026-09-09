@props(['rating' => 0, 'size' => 'md'])

@php
    $value = max(0, min(5, (float) $rating));
    $sizeClass = match ($size) {
        'sm' => 'text-base gap-0.5',
        'lg' => 'text-3xl gap-1',
        default => 'text-xl gap-0.5',
    };
@endphp

<span {{ $attributes->class(['inline-flex '.$sizeClass]) }} role="img" aria-label="{{ number_format($value, 1) }} out of 5 stars">
    @foreach (range(1, 5) as $star)
        <span class="relative inline-block leading-none text-ink-950" aria-hidden="true">
            &#9733;
            @if ($value >= $star)
                <span class="absolute inset-0 text-brand-coral">&#9733;</span>
            @elseif ($value >= $star - 0.5)
                <span class="absolute inset-y-0 left-0 w-1/2 overflow-hidden text-brand-coral"><span class="block w-max">&#9733;</span></span>
            @endif
        </span>
    @endforeach
</span>
