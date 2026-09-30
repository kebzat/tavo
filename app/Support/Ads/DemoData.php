<?php

namespace App\Support\Ads;

use App\Enums\Ads\AdPlatform;
use App\Enums\Ads\ReportType;
use App\Models\Ads\AdAccount;
use App\Models\Client;
use App\Models\User;
use App\Support\Ads\Platforms\DemoCatalog;
use App\Support\UniqueSlug;
use Illuminate\Support\Carbon;

/**
 * Založí a smaže ukázkové klienty. Čísla nevkládá přímo: klienti dostanou
 * účty na ukázkové platformě a čísla se stáhnou běžnou synchronizací.
 * Denní `ads:sync` je pak doplňuje sám, takže data nestárnou.
 *
 * Mazání bere jen klienty, kteří mají výhradně ukázkové účty.
 */
class DemoData
{
    public function __construct(
        private readonly AccountSync $sync,
        private readonly AlertEngine $alerts,
        private readonly ReportBuilder $reports,
    ) {}

    public function installed(): bool
    {
        return AdAccount::query()->whereIn('platform', [AdPlatform::Demo, AdPlatform::DemoGa4])->exists();
    }

    /** @return int Počet založených klientů */
    public function install(int $days = 90): int
    {
        $period = Period::between(now()->subDays($days), now()->subDay());
        $userId = User::query()->orderBy('id')->value('id');
        $created = 0;

        foreach (DemoCatalog::CLIENTS as $name => $spec) {
            $client = Client::query()->firstOrCreate(['name' => $name], [
                'slug' => UniqueSlug::for(new Client, $name),
                'website_url' => $spec['website'],
                'note' => 'Ukázková data pro vyzkoušení reklam. Smazat: php artisan ads:demo --remove',
            ]);
            $created += (int) $client->wasRecentlyCreated;

            $client->adSettings()->updateOrCreate(['client_id' => $client->getKey()], $spec['settings'] + ['primary_goal' => $spec['goal']]);

            $accounts = collect($spec['accounts'])->map(fn (array $account, string $id): array => [$id, $account, AdPlatform::Demo])
                ->concat(collect($spec['ga4'])->map(fn (array $account, string $id): array => [$id, $account, AdPlatform::DemoGa4]));

            foreach ($accounts as [$externalId, $account, $platform]) {
                $model = AdAccount::query()->updateOrCreate(
                    ['platform' => $platform, 'external_id' => $externalId],
                    ['client_id' => $client->getKey(), 'name' => $account['name'], 'currency' => 'CZK', 'timezone' => 'Europe/Prague', 'is_active' => true],
                );

                $this->sync->sync($model, $period);
            }

            $this->time($client, $userId);
        }

        $this->alerts->run();
        $this->sampleReport();

        return $created;
    }

    /** @return int Počet smazaných klientů */
    public function remove(): int
    {
        $isDemo = fn (AdAccount $account): bool => $account->platform->isDemo() || str_starts_with($account->external_id, 'demo_');

        $clients = Client::query()->whereHas('adAccounts')->with('adAccounts')->get()
            ->filter(fn (Client $client): bool => $client->adAccounts->every($isDemo));

        $clients->each->delete();

        return $clients->count();
    }

    /** Pár zápisů času v tomto měsíci, ať je co vidět ve Fakturaci. */
    private function time(Client $client, ?int $userId): void
    {
        if ($client->timeEntries()->exists()) {
            return;
        }

        $entries = [
            [2, 45, 'Týdenní kontrola kampaní a report'],
            [5, 90, 'Nové kreativy do prospectingu'],
            [9, 45, 'Týdenní kontrola kampaní a report'],
            [12, 120, 'Nastavení remarketingu a vyloučení publik'],
        ];

        foreach ($entries as [$daysAgo, $minutes, $description]) {
            $day = Carbon::today()->subDays($daysAgo);

            if (! $day->isSameMonth(Carbon::today())) {
                continue;
            }

            $client->timeEntries()->create([
                'user_id' => $userId,
                'worked_on' => $day,
                'minutes' => $minutes,
                'description' => $description,
                'billable' => true,
            ]);
        }
    }

    /** Jeden hotový report s komentářem a funkčním odkazem, ať je vidět, co klient dostane. */
    private function sampleReport(): void
    {
        $client = Client::query()->where('name', array_key_first(DemoCatalog::CLIENTS))->with(['adAccounts', 'adSettings'])->first();
        $period = Period::week(now()->subWeek());

        if ($client === null || $this->reports->exists($client, ReportType::Weekly, $period)) {
            return;
        }

        $this->reports->create($client, ReportType::Weekly, $period)->update([
            'is_public' => true,
            'summary' => "Tento týden jsme přesunuli část rozpočtu z Performance Max do remarketingu na Metě, který přináší nákupy nejlevněji.\n\n"
                ."- Prospecting s výběrovou kávou držíme, cena za nákup je pod cílem.\n"
                ."- Ve vyhledávání na značku přidáváme rozšíření o dopravě zdarma.\n\n"
                .'Příští týden otestujeme dvě nová videa z pražírny. Ukázková data, čísla jsou vymyšlená.',
        ]);
    }
}
