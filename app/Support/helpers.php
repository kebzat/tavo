<?php

use App\Support\WebTexts;

if (! function_exists('text')) {
    /**
     * Statický text webu editovatelný v administraci.
     *
     * Výchozí znění zůstává v kódu, databáze drží jen to, co správce přepsal.
     * Viz App\Support\WebTexts.
     */
    function text(string $key, string $default, ?string $group = null, ?string $note = null): string
    {
        return WebTexts::get($key, $default, $group, $note);
    }
}
