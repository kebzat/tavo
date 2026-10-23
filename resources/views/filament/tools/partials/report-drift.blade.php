{{-- Nad úpravou reportu: čísla v datech se od zmrazení změnila. Report zůstává, jak je. --}}
<div class="rounded-xl bg-info-50 p-4 text-sm text-info-800 ring-1 ring-info-200 dark:bg-info-500/10 dark:text-info-300 dark:ring-info-500/30">
    <p class="font-semibold">Od zmrazení Meta čísla dopočítala</p>
    <ul class="mt-1 space-y-0.5">
        @foreach ($drift as $row)
            <li>{{ $row['label'] }}: v reportu {{ $row['frozen'] }}, teď v datech {{ $row['now'] }}</li>
        @endforeach
    </ul>
    <p class="mt-1 text-xs">
        @if ($report->status->value === 'draft')
            Koncept jde přepočítat tlačítkem Obnovit čísla.
        @else
            Report je schválený, čísla v něm zůstávají tak, jak jste je schválili.
        @endif
    </p>
</div>
