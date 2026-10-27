<?php

namespace App\Support\Agent;

use App\Models\Client;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

/**
 * Projekt agenta bez kódu pro klienta (Projekty → Založit agenty klientům,
 * Klienti → Načíst z ClickUpu) a pro skill (Mozek → Skilly). Zakládá se na pozadí
 * (`agent:projekty-klientu`) po jednom s odstupem: frontu na serveru spouští systemd
 * a víc než 5 spuštění za 10 s ji vypne (26. 10. 2026 se tak zastavila po pátém projektu).
 */
class ClientProjects
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_FAILED = 'failed';

    /** Odstup mezi dvěma projekty v sekundách. */
    public const PAUSE = 3;

    public function __construct(private AgentServer $agent) {}

    public function request(Client $client, ?string $author = null): void
    {
        if (filled($client->agent_project)) {
            return;
        }

        $client->forceFill(['agent_project_request' => array_filter([
            'status' => self::STATUS_QUEUED,
            'requested_at' => now()->toIso8601String(),
            'author' => $author,
        ])])->save();
    }

    /** Skill dostane projekt s názvem z žádosti (obsazený název jde změnit), jinak se slugem. */
    public function requestSkill(Skill $skill, ?string $author = null, ?string $project = null): void
    {
        if (filled($skill->agent_project)) {
            return;
        }

        $skill->forceFill(['agent_project_request' => array_filter([
            'status' => self::STATUS_QUEUED,
            'requested_at' => now()->toIso8601String(),
            'author' => $author,
            'project' => $project ?: $skill->projectName(),
        ])])->save();
    }

    /** @return Collection<int, Client|Skill> klienti, pak skilly */
    public static function queued(): Collection
    {
        return self::requests(self::STATUS_QUEUED);
    }

    /** @return Collection<int, Client|Skill> */
    public static function failed(): Collection
    {
        return self::requests(self::STATUS_FAILED);
    }

    /** Aktivní klienti bez projektu, pro které se nic nezakládá. @return Collection<int, Client> */
    public static function missing(): Collection
    {
        return Client::query()->active()->whereNull('agent_project')->orderBy('name')->get()
            ->reject(fn (Client $client): bool => ($client->agent_project_request['status'] ?? null) === self::STATUS_QUEUED)
            ->values();
    }

    /**
     * Založí čekající projekty. Když fronta na serveru neodpovídá, ostatní
     * nečekají po 3 minutách každý, skončí se stejnou chybou hned.
     */
    public function run(): int
    {
        $created = 0;
        $clients = self::queued();

        foreach ($clients as $i => $client) {
            if ($i > 0) {
                Sleep::for(self::PAUSE)->seconds();
            }

            [$error, $outage] = $this->create($client);

            if ($error === null) {
                $client->forceFill(['agent_project_request' => null])->save();
                $created++;

                continue;
            }

            $failed = $outage ? $clients->slice($i) : collect([$client]);
            $failed->each(fn (Model $c) => $c->forceFill(['agent_project_request' => [
                ...$c->agent_project_request ?? [],
                'status' => self::STATUS_FAILED,
                'error' => $error,
                'finished_at' => now()->toIso8601String(),
            ]])->save());

            if ($outage) {
                break;
            }
        }

        return $created;
    }

    /**
     * Projekt se stejným názvem jako zkratka klienta (skill: název ze žádosti).
     * Když projekt už existuje a nepatří jinému klientovi ani skillu, jen se přiřadí.
     *
     * @return array{0: ?string, 1: bool} chyba (null = hotovo) a jestli nejde služba
     */
    private function create(Client|Skill $client): array
    {
        $name = $client instanceof Skill ? $client->projectName() : $client->slug;
        $slug = Str::limit(trim(preg_replace('/[^a-z0-9-]+/', '-', Str::slug($name)), '-'), 40, '');

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $slug)) {
            return ['zkratka „'.$name.'“ nejde použít jako název projektu, přidejte ho v Projekty → Přidat projekt', false];
        }

        try {
            $existing = collect($this->agent->projects())->pluck('slug');
            $taken = Client::query()->where('agent_project', $slug)->when($client instanceof Client, fn ($q) => $q->whereKeyNot($client->getKey()))->exists()
                || Skill::query()->where('agent_project', $slug)->when($client instanceof Skill, fn ($q) => $q->whereKeyNot($client->getKey()))->exists();

            if ($existing->contains($slug) && $taken) {
                return ['projekt „'.$slug.'“ už patří jinému klientovi nebo skillu, přidejte jiný v Projekty → Přidat projekt', false];
            }

            $result = $existing->contains($slug) ? ['ok' => true] : $this->agent->addProject($slug, 'prazdny');
        } catch (AgentUnavailable) {
            return ['služba agenta neodpovídá', true];
        }

        if (! $result['ok']) {
            $output = trim((string) ($result['vystup'] ?? ''));

            // Text služby, když root frontu nezpracoval (server.js, fronta()).
            return str_contains($output, 'neodpověděl')
                ? ['fronta na serveru nezakládá projekty, Tom ji musí spustit znovu (docs/AGENT.md, Fronta)', true]
                : [Str::limit($output, 200), false];
        }

        $client->forceFill(['agent_project' => $slug])->save();

        return [null, false];
    }

    /** @return Collection<int, Client|Skill> */
    private static function requests(string $status): Collection
    {
        return collect([
            ...Client::query()->whereNull('agent_project')->whereNotNull('agent_project_request')->orderBy('name')->get(),
            ...Skill::query()->whereNull('agent_project')->whereNotNull('agent_project_request')->orderBy('sort')->get(),
        ])
            ->filter(fn (Model $owner): bool => ($owner->agent_project_request['status'] ?? null) === $status)
            ->values();
    }
}
