@props([
    // Na jakém podkladu komponenta leží. Řídí barvu textu i zvýraznění při
    // najetí myší: na cihlovém pruhu nemůže být hover cihlový, ztratil by se.
    'tone' => 'cream',   // cream | brick | ink
])

@php
    $tones = [
        'cream' => [
            'label' => 'text-ink/70',
            'link' => 'text-ink hover:text-brick',
            'underline' => 'border-ink/30 group-hover:border-brick',
        ],
        'brick' => [
            'label' => 'text-ink/70',
            'link' => 'text-ink hover:text-cream',
            'underline' => 'border-ink/40 group-hover:border-cream',
        ],
        'ink' => [
            'label' => 'text-cream/70',
            'link' => 'text-cream hover:text-brick',
            'underline' => 'border-cream/30 group-hover:border-brick',
        ],
    ];

    $style = $tones[$tone] ?? $tones['cream'];
@endphp

{{--
    Telefony na Pavla a Toma pro toho, kdo nechce čekat na odpověď z formuláře.
    Čísla drží zakladatelé (Nastavení → Lidé), takže se mění na jednom místě
    a bez čísla se člověk nenabídne vůbec.

    Seznam sem sdílí AppServiceProvider jako $contactPeople.
--}}

@if ($contactPeople->isNotEmpty())
    <div {{ $attributes->class(['flex flex-wrap items-center gap-x-4 gap-y-2.5 text-[15px]']) }}>
        <span class="{{ $style['label'] }}">Když to spěchá, zavolejte:</span>

        @foreach ($contactPeople as $person)
            <a href="{{ $person->phoneHref() }}"
               class="group inline-flex items-center gap-2 font-bold transition-colors {{ $style['link'] }}">
                {{ $person->name }}
                <span class="border-b-2 font-semibold tabular-nums transition-colors {{ $style['underline'] }}">{{ $person->phoneLabel() }}</span>
            </a>
        @endforeach
    </div>
@endif
