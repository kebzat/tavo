@if ($points)
    <svg viewBox="0 0 100 30" preserveAspectRatio="none" aria-hidden="true" {{ $attributes->class(['block h-8 w-full', $class]) }}>
        <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
    </svg>
@endif
