@extends('install.layout')

@section('title', 'Instalace dokončena')

@section('content')
    @php($failed = collect($steps)->contains(fn (array $step): bool => ! $step['ok']))

    <span class="brand">INSTALACE</span>

    @if ($failed)
        <h1>Instalace skončila s chybou</h1>
        <p class="lead">
            Konfigurace je zapsaná, ale některý krok neproběhl. Podrobnosti níže —
            podle nich lze problém dořešit a krok zopakovat z administrace
            (Nastavení → Údržba).
        </p>
    @else
        <h1>Hotovo, web běží</h1>
        <p class="lead">
            Konfigurace je zapsaná, databáze připravená. Průvodce je teď zamčený
            a už se znovu nespustí.
        </p>
    @endif

    <div class="card">
        <h2>Průběh</h2>
        <ul class="checks">
            @foreach ($steps as $step)
                <li>
                    <span>{{ $step['label'] }}</span>
                    <span class="{{ $step['ok'] ? 'ok' : 'bad' }}">{{ $step['ok'] ? '✓ hotovo' : '✕ chyba' }}</span>
                </li>
                @if (! $step['ok'] && $step['output'] !== '')
                    <pre>{{ $step['output'] }}</pre>
                @endif
            @endforeach
        </ul>
    </div>

    @unless ($failed)
        <div class="card alert success">
            <strong>Zbývá poslední krok:</strong> vytvořte si přihlášení do administrace
            a začněte plnit obsah.
        </div>
    @endunless

    <p><a class="cta" href="{{ $adminUrl }}">Přejít do administrace</a></p>
@endsection
