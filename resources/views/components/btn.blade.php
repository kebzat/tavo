@props([
    'href' => null,
    'variant' => 'primary', // primary | dark | ghost | ghost-dark
    'size' => 'md',         // md | lg
])

@php
    $base = 'inline-flex items-center justify-center rounded-pill font-bold transition duration-300 ease-tavo';

    $sizes = [
        'md' => 'px-[30px] py-[17px] text-base',
        'lg' => 'px-9 py-[19px] text-[17px]',
    ];

    $variants = [
        'primary' => 'bg-brick text-cream hover:-translate-y-[3px] hover:bg-brick-dark hover:shadow-[0_14px_30px_-10px_rgba(219,75,36,.6)]',
        'dark' => 'bg-ink text-cream hover:-translate-y-[3px] hover:shadow-[0_16px_34px_-12px_rgba(19,17,16,.55)]',
        'ghost' => 'border-[1.5px] border-ink/28 text-ink hover:-translate-y-[3px] hover:border-ink hover:bg-ink hover:text-cream',
        'ghost-dark' => 'border-[1.5px] border-ink/40 text-ink hover:-translate-y-[3px] hover:bg-ink hover:text-cream',
    ];

    $classes = trim($base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']));
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
