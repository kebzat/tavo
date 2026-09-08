{{--
    Upozornění na novou poptávku.

    Údaje jsou seznam, ne řádky pod sebou: markdown sousední řádky slepí do
    jednoho odstavce a v e-mailu se pak jméno, telefon a rozpočet válely za
    sebou v jedné větě. Tabulka by srovnala sloupce líp, ale komponenta
    `x-mail::table` vyžaduje hlavičku a prázdná hlavička nechá nad údaji
    useknutou čáru.

    E-mail a telefon jsou odkazy, ať se z mobilu dá rovnou volat.
--}}
<x-mail::message>
# Nová poptávka z webu

- **Jméno:** {{ $lead->name }}
@if ($lead->company)
- **Firma:** {{ $lead->company }}
@endif
- **E-mail:** [{{ $lead->email }}](mailto:{{ $lead->email }})
@if ($lead->phone)
- **Telefon:** [{{ $lead->phone }}](tel:{{ preg_replace('/[^0-9+]/', '', $lead->phone) }})
@endif
@if ($lead->topic)
- **O co jde:** {{ $lead->topic }}
@endif
@if ($lead->budget)
- **Rozpočet:** {{ $lead->budget }}
@endif

**Zpráva**

<x-mail::panel>
{{ $lead->message }}
</x-mail::panel>

<x-mail::button :url="url('/admin/leads/'.$lead->id)">
Otevřít v administraci
</x-mail::button>

Odpověď na tenhle e-mail půjde rovnou na {{ $lead->email }}.

Odesláno {{ $lead->created_at->format('j. n. Y H:i') }}@if ($lead->source_url) ze stránky {{ $lead->source_url }}@endif.
</x-mail::message>
