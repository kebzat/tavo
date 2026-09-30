<?php

namespace App\Support\Ads\Ai;

use App\Models\Client;

/**
 * Návrh úprav reklam od Clauda. Placené volání, spouští se jen ručně
 * tlačítkem na detailu klienta a jen se zapnutým ANTHROPIC_ENABLED.
 */
interface AdsAdvisor
{
    public function enabled(): bool;

    /**
     * @param  array<string, mixed>  $snapshot  Tvar z ClientPerformance::build()
     * @param  list<string>  $alerts  Titulky živých upozornění
     * @return string|null Markdown s doporučeními, null při chybě
     */
    public function advise(Client $client, array $snapshot, array $alerts): ?string;

    public function lastError(): ?string;
}
