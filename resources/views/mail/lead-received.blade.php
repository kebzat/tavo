<x-mail::message>
# Nová poptávka z webu

**Jméno:** {{ $lead->name }}
@if ($lead->company)
**Firma:** {{ $lead->company }}
@endif
**E-mail:** {{ $lead->email }}
@if ($lead->phone)
**Telefon:** {{ $lead->phone }}
@endif
@if ($lead->topic)
**O co jde:** {{ $lead->topic }}
@endif
@if ($lead->budget)
**Orientační rozpočet:** {{ $lead->budget }}
@endif

**Zpráva:**

{{ $lead->message }}

<x-mail::button :url="url('/admin/leads/'.$lead->id)">
Otevřít v administraci
</x-mail::button>

Odesláno {{ $lead->created_at->format('j. n. Y H:i') }}@if ($lead->source_url) ze stránky {{ $lead->source_url }}@endif.
</x-mail::message>
