@props([
    'tone' => 'ink',   // ink = na krémovém a cihlovém podkladu | cream = na tmavém
])

{{--
    Telefony na Pavla a Toma pro toho, kdo nechce čekat na odpověď z formuláře.
    Čísla drží zakladatelé (Nastavení → Lidé), takže se mění na jednom místě
    a bez čísla se člověk nenabídne vůbec.

    Seznam sem sdílí AppServiceProvider jako $contactPeople.
--}}

@if ($contactPeople->isNotEmpty())
    <div {{ $attributes->class(['flex flex-wrap items-center gap-x-4 gap-y-2.5 text-[15px]']) }}>
        <span class="{{ $tone === 'cream' ? 'text-cream/70' : 'text-ink/70' }}">Když to spěchá, zavolejte:</span>

        @foreach ($contactPeople as $person)
            <a href="{{ $person->phoneHref() }}"
               class="{{ $tone === 'cream' ? 'text-cream hover:text-brick' : 'text-ink hover:text-brick' }} inline-flex items-center gap-2 font-bold transition-colors">
                {{ $person->name }}
                <span class="{{ $tone === 'cream' ? 'border-cream/30' : 'border-ink/30' }} border-b-2 font-semibold tabular-nums">{{ $person->phoneLabel() }}</span>
            </a>
        @endforeach
    </div>
@endif
