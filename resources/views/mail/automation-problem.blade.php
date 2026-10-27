<x-mail::message>
# {{ $automation->type->getLabel() }} nejede

Dobrý den, {{ $recipient->name }},

u e-shopu **{{ $automation->client->name }}** se automatizace zasekla:

{{ $problem }}

**Co dělat:** {{ $advice }}

<x-mail::button :url="$url">
Otevřít automatizace
</x-mail::button>

Stejné upozornění pošleme nejdřív za {{ \App\Support\Automations\AutomationAlerts::THROTTLE_HOURS }} hodin.

Taveo
</x-mail::message>
