<?php

namespace App\Support\Ads\Ai;

use Anthropic\Client as Anthropic;
use Anthropic\Core\Exceptions\APIException;
use App\Models\Client;
use App\Support\Ads\Metrics;
use App\Support\Ads\PerformanceView;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Claude dostane čísla klienta za období po kampaních a živá upozornění
 * a navrhne, co v reklamách upravit. Nic nevymýšlí: pracuje jen s čísly
 * z podkladu a o kreativách, cílení ani webu neví nic, co v nich není.
 */
class ClaudeAdsAdvisor implements AdsAdvisor
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private const SYSTEM = <<<'TXT'
        Jsi zkušený specialista na výkonnostní reklamu na Metě a radíš Pavlovi z TAVEO, který reklamy klienta spravuje. Píšeš jemu, ne klientovi.
        Pravidla:
        - Vycházej jen z čísel v podkladu. Nevymýšlej údaje o kreativách, cílení, webu ani konkurenci. Když k doporučení chybí informace, napiš, co si má Pavel ověřit.
        - Malé rozpočty mají málo dat. U kampaní s pár konverzemi výslovně upozorni, že jde o náhodu, a nedoporučuj podle nich velké změny.
        - Konverze a ROAS jsou atribuce Mety, ne zisk.
        - Česky, stručně, v Markdownu. Nejvýš 6 doporučení seřazených podle dopadu. U každého: co udělat, proč (číslo z podkladu) a jak poznat, že to zabralo.
        - Žádná pomlčka „—“, žádné obecné fráze.
        TXT;

    private ?string $lastError = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly ?string $workspaceId = null,
    ) {}

    public function enabled(): bool
    {
        return true;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function advise(Client $client, array $snapshot, array $alerts): ?string
    {
        $this->lastError = null;
        $view = new PerformanceView($snapshot);
        $settings = $client->adSettings;

        $campaigns = collect($snapshot['campaigns'] ?? [])->map(function (array $row) use ($view): array {
            $m = Metrics::fromArray($row['totals']);

            return [
                'kampaň' => $row['name'],
                'útrata' => round($m->spend()),
                'konverze' => $m->conversions($view->goal),
                'cena_za_konverzi' => $m->costPerConversion($view->goal) !== null ? round($m->costPerConversion($view->goal), 2) : null,
                'roas' => $m->roas() !== null ? round($m->roas(), 2) : null,
                'ctr_%' => $m->ctr() !== null ? round($m->ctr(), 2) : null,
                'frekvence_odhad' => $m->frequency() !== null ? round($m->frequency(), 2) : null,
                'útrata_minulé_období' => round(Metrics::fromArray($row['previous_totals'] ?? [])->spend()),
            ];
        })->all();

        $prompt = "Klient: {$client->name}".($client->website_url ? " ({$client->website_url})" : '')."\n"
            ."Hlavní cíl: {$view->goal->getLabel()}\n"
            ."Měna: {$view->currency}\n"
            .'Rozpočet na měsíc: '.($settings?->monthly_budget ?? 'neurčen')
            .', cílová cena za konverzi: '.($settings?->target_cpa ?? 'neurčena')
            .', cílový ROAS: '.($settings?->target_roas ?? 'neurčen')."\n"
            ."Období: {$view->period->label()}, srovnání s {$view->previousPeriod->label()}\n\n"
            ."Souhrn (období / minulé období):\n"
            .collect($view->tiles())->map(fn (array $tile): string => "- {$tile['label']}: {$tile['value']} / {$tile['previous']}")->implode("\n")
            ."\n\nKampaně:\n".json_encode($campaigns, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ."\n\nŽivá upozornění z denní kontroly:\n".($alerts ? '- '.implode("\n- ", $alerts) : 'žádná')
            ."\n\nCo má Pavel v reklamách tohoto klienta upravit?";

        try {
            $response = $this->client()->beta->messages->create(
                model: $this->model,
                maxTokens: 3000,
                system: self::SYSTEM,
                messages: [['role' => 'user', 'content' => $prompt]],
                outputConfig: ['effort' => 'medium'],
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
            );
        } catch (APIException $e) {
            $this->lastError = Str::limit($e->getMessage(), 300);
            Log::warning('Claude: návrh úprav reklam selhal', ['error' => $e->getMessage()]);

            return null;
        }

        if (in_array($response->stopReason, ['refusal', 'max_tokens'], true)) {
            $this->lastError = 'Odpověď bez výsledku: '.$response->stopReason;

            return null;
        }

        $text = collect($response->content)->filter(fn ($block): bool => $block->type === 'text')->map(fn ($block): string => $block->text)->implode('');

        return $text !== '' ? str_replace(' — ', ', ', trim($text)) : null;
    }

    private function client(): Anthropic
    {
        return new Anthropic(apiKey: $this->apiKey, requestOptions: array_filter([
            'timeout' => 120,
            'extraHeaders' => filled($this->workspaceId) ? ['anthropic-workspace-id' => $this->workspaceId] : null,
        ]));
    }
}
