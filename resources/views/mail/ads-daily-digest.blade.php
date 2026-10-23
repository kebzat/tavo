<x-mail::message>
# Dobré ráno, {{ $recipient->name }}

@if ($summary)
**Dnes ráno:** {{ $summary }}.
@endif

@if ($newAlerts->isNotEmpty())
## Nové ({{ $newAlerts->count() }})

@foreach ($newAlerts as $alert)
- **{{ $alert->client->name }}**: {{ $alert->title }} ({{ $alert->severity->getLabel() }})
@endforeach
@endif

@if ($ongoingAlerts->isNotEmpty())
## Trvá beze změny ({{ $ongoingAlerts->count() }})

@foreach ($ongoingAlerts as $alert)
- {{ $alert->client->name }}: {{ $alert->title }}, od {{ $alert->detected_on->format('j. n.') }}
@endforeach
@endif

@if ($alerts->isEmpty())
Žádné upozornění. Reklamy běží, jak mají.
@endif

@if ($events->isNotEmpty())
## Termíny ({{ $events->count() }})

@foreach ($events as $event)
- **{{ $event->client->name }}**: {{ $event->title }}, {{ $event->due_on->format('j. n.') }} ({{ $event->whenLabel() }}). {{ $event->type->todo() }}
@endforeach
@endif

@if ($recovered->isNotEmpty())
## Vrátilo se do normálu

@foreach ($recovered as $alert)
- {{ $alert->client->name }}: {{ $alert->title }}
@endforeach
@endif

@if ($briefings > 0)
## Ranní přehled od agenta

Pro {{ $briefings }} {{ $briefings === 1 ? 'klienta' : 'klientů' }}: co se změnilo, jestli jsou data spolehlivá a navržený další krok ke schválení.

<x-mail::button :url="$briefingsUrl">
Otevřít ranní přehled
</x-mail::button>
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
