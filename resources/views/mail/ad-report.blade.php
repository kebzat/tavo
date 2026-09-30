<x-mail::message>
# {{ $report->type->title() }}

{{ $report->client->name }}, {{ $period }}

<x-mail::table>
| | | proti minulému období |
|:--|--:|--:|
@foreach ($tiles as $tile)
| {{ $tile['label'] }} | **{{ $tile['value'] }}** | {{ $tile['change'] ?? '–' }} |
@endforeach
</x-mail::table>

Graf po dnech, výsledky jednotlivých kampaní a náš komentář k tomu, co jsme dělali a co chystáme dál, najdete v přehledu.

<x-mail::button :url="$url">
Otevřít přehled
</x-mail::button>

Když vám něco v číslech nesedí, odpovězte na tenhle e-mail.

Pavel a Tom, Taveo
</x-mail::message>
