@props([
    'progress',
])

{{-- Proužek s poměrem hotových položek. Šířka jde přes style, protože
     skládaná Tailwind třída by se do CSS vůbec nedostala. --}}
<div {{ $attributes }}>
    <p class="mb-2 text-sm font-semibold text-muted tabular-nums">
        <span data-progres-hotovo>{{ $progress['done'] }}</span> / {{ $progress['total'] }} hotovo
    </p>
    <div class="h-1.5 w-full overflow-hidden rounded-pill bg-ink/10">
        <div class="h-full rounded-pill bg-brick transition-[width] duration-500 ease-tavo"
             data-progres-vypln
             style="width: {{ $progress['percent'] }}%"></div>
    </div>
</div>
