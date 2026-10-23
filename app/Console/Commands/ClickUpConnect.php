<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Support\ClickUp\ClickUp;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Napojení ClickUpu na agenta (docs/AGENT.md, ClickUp).
 *
 * Bez parametrů jen ukáže seznamy z ClickUpu, ke kterému klientovi by patřily
 * a kam by šel agent. Nic nemění. S --ulozit přiřadí seznamy klientům se
 * stejným názvem (jen těm, kteří seznam ještě nemají). S --webhook založí
 * v ClickUpu webhook na nové komentáře a vypíše jeho tajemství do .env.
 * V ClickUpu se nic jiného nemění.
 */
class ClickUpConnect extends Command
{
    protected $signature = 'clickup:napojit
        {--ulozit : Přiřadit seznamy klientům se stejným názvem}
        {--webhook : Založit webhook na nové komentáře, pokud ještě není}';

    protected $description = 'Přiřadí seznamy z ClickUpu klientům a založí webhook pro agenta';

    public function handle(ClickUp $clickUp): int
    {
        if (! $clickUp->configured() || blank(config('services.clickup.team_id'))) {
            $this->error('Chybí CLICKUP_API_TOKEN nebo CLICKUP_TEAM_ID v .env.');

            return self::FAILURE;
        }

        $clients = Client::query()->get()->keyBy(fn (Client $client): string => $this->key($client->name));
        $spaces = config('services.clickup.space_projects');
        $rows = [];

        foreach ($clickUp->lists() as $list) {
            $client = $clients->get($this->key($list['name']));

            if ($client && $this->option('ulozit') && blank($client->clickup_list_id)
                && Client::query()->where('clickup_list_id', $list['id'])->doesntExist()) {
                $client->update(['clickup_list_id' => $list['id']]);
            }

            $mapped = $client && $client->clickup_list_id === $list['id'];
            $project = ($mapped ? $client->agent_project : null) ?: ($spaces[$list['space_id']] ?? null);

            $rows[] = [$list['space'], $list['name'], $client ? $client->name.($mapped ? '' : ' (nepřiřazeno)') : '–', $project ?? 'agent nereaguje'];
        }

        $this->table(['Prostor', 'Seznam', 'Klient v nástrojích', 'Projekt agenta'], $rows);

        if ($this->option('webhook')) {
            return $this->webhook($clickUp);
        }

        return self::SUCCESS;
    }

    private function webhook(ClickUp $clickUp): int
    {
        $endpoint = route('clickup.webhook');
        $existing = collect($clickUp->webhooks())->firstWhere('endpoint', $endpoint);

        if ($existing) {
            $this->info("Webhook už existuje ({$existing['id']}), nic nezakládám.");

            return self::SUCCESS;
        }

        $webhook = $clickUp->createWebhook($endpoint);
        $this->info("Webhook založený ({$webhook['id']}) na {$endpoint}.");
        $this->line('Do .env doplňte a pak php artisan config:cache:');
        $this->line('CLICKUP_WEBHOOK_SECRET='.$webhook['secret']);

        return self::SUCCESS;
    }

    /** „Svět Cejlonu“ = „svet-cejlonu“, „MIRNIX Hockey“ = „Mirnix Hockey“. */
    private function key(string $name): string
    {
        return Str::slug($name);
    }
}
