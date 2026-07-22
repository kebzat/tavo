@props([
    'rule' => false,     // vodorovná čárka před textem (hero)
    'tone' => 'brick',   // brick | ink | cream
])

@php
    $tones = [
        'brick' => 'text-brick',
        'ink' => 'text-ink',
        'cream' => 'text-cream',
    ];
@endphp

<div {{ $attributes->class(['inline-flex items-center gap-2.5 text-[13px] font-semibold tracking-[.16em] uppercase', $tones[$tone] ?? $tones['brick']]) }}>
    @if ($rule)
        <span class="h-0.5 w-[26px] bg-current"></span>
    @endif
    {{ $slot }}
</div>
