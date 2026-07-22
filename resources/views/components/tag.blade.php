@props([
    'tone' => 'light', // light | dark | brick | ghost
    'size' => 'sm',
])

@php
    $tones = [
        'light' => 'bg-ink/6 text-ink',
        'dark' => 'bg-ink text-cream',
        'brick' => 'bg-brick text-white',
        'ghost' => 'bg-cream/10 text-cream',
    ];
    $sizes = [
        'xs' => 'px-3 py-1.5 text-[11px] tracking-[.1em] uppercase',
        'sm' => 'px-[13px] py-[7px] text-xs',
    ];
@endphp

<span {{ $attributes->class(['rounded-pill font-semibold', $tones[$tone] ?? $tones['light'], $sizes[$size] ?? $sizes['sm']]) }}>
    {{ $slot }}
</span>
