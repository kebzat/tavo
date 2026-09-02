<x-mail::message>
# Dobré ráno, {{ $recipient->name }}

@if ($overdue->isEmpty() && $dueToday->isEmpty() && $demands->isEmpty() && $stale->isEmpty())
Dneska tě v CRM nic nečeká. Klidný den.
@endif

@if ($overdue->isNotEmpty())
## Po termínu ({{ $overdue->count() }})

@foreach ($overdue as $company)
- **{{ $company->name }}** — termín byl {{ $company->next_action_at->format('j. n. Y') }}@if ($company->activities->first()), naposledy {{ $company->activities->first()->type->getLabel() }} {{ $company->activities->first()->happened_at->format('j. n.') }}@endif
@endforeach
@endif

@if ($dueToday->isNotEmpty())
## Dnes ({{ $dueToday->count() }})

@foreach ($dueToday as $company)
- **{{ $company->name }}**@if ($company->activities->first()) — naposledy {{ $company->activities->first()->type->getLabel() }} {{ $company->activities->first()->happened_at->format('j. n.') }}@endif
@endforeach
@endif

@if ($demands->isNotEmpty())
## Nové poptávky ({{ $demands->count() }})

@foreach ($demands as $demand)
- [{{ $demand->title }}]({{ $demand->url }}) — {{ $demand->source->getLabel() }}, priorita {{ $demand->priority->short() }}
@endforeach
@endif

@if ($stale->isNotEmpty())
## Bez pohybu ({{ $stale->count() }})

Rozjednané firmy, u kterých se týden nic nestalo.

@foreach ($stale as $company)
- **{{ $company->name }}** — {{ $company->status->getLabel() }}@if ($company->last_activity_at), naposledy {{ $company->last_activity_at->format('j. n. Y') }}@endif
@endforeach
@endif

<x-mail::button :url="$todayUrl">
Otevřít CRM
</x-mail::button>

Taveo
</x-mail::message>
