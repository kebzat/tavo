<x-mail::message>
# Dobré ráno, {{ $recipient->name }}

@if ($alerts->isNotEmpty())
## Upozornění ({{ $alerts->count() }})

@foreach ($alerts as $alert)
- **{{ $alert->client->name }}**: {{ $alert->title }} ({{ $alert->severity->getLabel() }})
@endforeach
@else
Žádné nové upozornění. Reklamy běží, jak mají.
@endif

@if ($rows)
## Včera

<x-mail::table>
| Klient | Útrata | Konverze | Cena za konverzi |
|:--|--:|--:|--:|
@foreach ($rows as $row)
| {{ $row['client'] }} | {{ $row['spend'] }} | {{ $row['conversions'] }} | {{ $row['cost'] }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="$overviewUrl">
Otevřít reklamy
</x-mail::button>

Taveo
</x-mail::message>
