@extends('install.layout')

@section('title', 'Instalace webu')

@section('content')
    <span class="brand">INSTALACE</span>
    <h1>Spuštění webu na serveru</h1>
    <p class="lead">
        Vyplňte údaje k databázi. Průvodce zapíše konfiguraci, vytvoří tabulky
        a připraví web k provozu — do terminálu už nemusíte.
    </p>

    @if ($problems !== [])
        <div class="alert">
            <strong>Instalaci nelze dokončit:</strong>
            <ul>
                @foreach ($problems as $problem)
                    <li>{{ $problem }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <h2>Kontrola serveru</h2>
        <ul class="checks">
            @foreach ($requirements as $check)
                <li>
                    <span>{{ $check['label'] }}</span>
                    <span class="{{ $check['ok'] ? 'ok' : 'bad' }}">
                        {{ $check['ok'] ? '✓' : '✕' }} {{ $check['detail'] }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>

    <form method="post" action="{{ route('install.run') }}">
        <div class="card">
            <h2>Web</h2>

            <div class="field">
                <label for="app_name">Název webu</label>
                <input type="text" id="app_name" name="app_name" value="{{ $values['app_name'] }}" required>
            </div>

            <div class="field">
                <label for="app_url">
                    Adresa webu
                    <span class="hint">— včetně https://, bez lomítka na konci</span>
                </label>
                <input type="text" id="app_url" name="app_url" value="{{ $values['app_url'] }}" required>
            </div>
        </div>

        <div class="card">
            <h2>Databáze</h2>

            <div class="row">
                <div class="field">
                    <label for="db_host">Server <span class="hint">— obvykle 127.0.0.1</span></label>
                    <input type="text" id="db_host" name="db_host" value="{{ $values['db_host'] }}" required>
                </div>
                <div class="field">
                    <label for="db_port">Port</label>
                    <input type="text" id="db_port" name="db_port" value="{{ $values['db_port'] }}">
                </div>
            </div>

            <div class="field">
                <label for="db_database">Název databáze</label>
                <input type="text" id="db_database" name="db_database" value="{{ $values['db_database'] }}" required>
            </div>

            <div class="row">
                <div class="field">
                    <label for="db_username">Uživatel</label>
                    <input type="text" id="db_username" name="db_username" value="{{ $values['db_username'] }}" required>
                </div>
                <div class="field">
                    <label for="db_password">Heslo</label>
                    <input type="password" id="db_password" name="db_password" value="{{ $values['db_password'] }}">
                </div>
            </div>

            <div class="field">
                <label for="seed">
                    <input type="checkbox" id="seed" name="seed" value="1" @checked($values['seed'])>
                    Naplnit výchozím obsahem
                    <span class="hint">— vypněte, pokud budete importovat vlastní databázi</span>
                </label>
            </div>
        </div>

        <button type="submit">Nainstalovat web</button>
        <p class="note">Instalace může trvat i půl minuty. Nezavírejte stránku.</p>
    </form>
@endsection
