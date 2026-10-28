{{-- Číslo objednávky s odkazem do detailu v administraci Upgates, když už známe její ID (App\Support\Automations\UpgatesOrderLinks). --}}
@php($orderUrl = \App\Support\Automations\UpgatesOrderLinks::url($automation, (string) $number))
@if ($orderUrl)
    <a href="{{ $orderUrl }}" target="_blank" rel="noopener" title="Otevřít objednávku v Upgates" class="text-primary-600 underline decoration-primary-600/30 underline-offset-2 hover:decoration-primary-600 dark:text-primary-400 dark:decoration-primary-400/30">{{ $number }}</a>
@else
    {{ $number }}
@endif
