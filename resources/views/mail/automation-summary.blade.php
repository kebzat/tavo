<x-mail::message>
# Dobré ráno, {{ $recipient->name }}

Co automatizace zásilek za {{ $day }} poslala zákazníkům:

@if (array_sum($sent) === 0)
Včera se nic neposílalo.
@else
@foreach ($sent as $label => $count)
- {{ $label }}: **{{ $count }}**@if (! empty($orders[$label])) ({{ implode(', ', $orders[$label]) }})@endif
@endforeach
@endif

@if ($failed > 0)
**Pozor:** {{ $failed }} {{ $failed === 1 ? 'zpráva se nepovedla' : 'zpráv se nepovedlo' }}, podrobnosti jsou v účtu.
@endif

Na výdejních místech teď leží **{{ $parcels }}** {{ $parcels === 1 ? 'zásilka' : ($parcels > 1 && $parcels < 5 ? 'zásilky' : 'zásilek') }}.

<x-mail::button :url="$url">
Otevřít automatizace
</x-mail::button>

Taveo
</x-mail::message>
