<?php

namespace App\Console\Commands;

use App\Mail\AdsDailyDigest as DigestMail;
use App\Models\Ads\AdAlert;
use App\Models\Client;
use App\Models\User;
use App\Settings\AdsSettings;
use App\Support\Ads\Metrics;
use App\Support\Ads\Period;
use App\Support\Ads\Stats;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Ranní souhrn reklam: co svítí a kolik se včera utratilo. Když není co
 * hlásit a nic se neutratilo, e-mail neodejde. Prázdný souhrn by se
 * naučil nečíst.
 */
class AdsDigest extends Command
{
    protected $signature = 'ads:digest {--dry-run : Jen vypíše, komu by souhrn odešel}';

    protected $description = 'Rozešle ranní souhrn reklam klientů';

    public function handle(AdsSettings $settings): int
    {
        $alerts = AdAlert::query()->open()->with('client')->worstFirst()->get();
        $clients = $this->yesterday();

        if ($alerts->isEmpty() && $clients->isEmpty()) {
            $this->info('Není co hlásit, souhrn se neposílá.');

            return self::SUCCESS;
        }

        foreach ($this->recipients($settings) as $user) {
            if ($this->option('dry-run')) {
                $this->line("Souhrn by šel na {$user->email}.");

                continue;
            }

            try {
                Mail::to($user->email)->send(new DigestMail($user, $alerts, $clients));
                $this->info("Souhrn odeslán na {$user->email}.");
            } catch (\Throwable $e) {
                Log::error('Ranní souhrn reklam se nepodařilo odeslat.', ['email' => $user->email, 'error' => $e->getMessage()]);
                $this->error("Odeslání na {$user->email} selhalo: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Včerejší útrata a konverze po klientech, jen ti, u kterých se něco dělo.
     *
     * @return Collection<int, array{client: Client, metrics: Metrics}>
     */
    private function yesterday(): Collection
    {
        $period = Period::between(now()->subDay(), now()->subDay());

        return Client::query()->withAds()->with(['adAccounts', 'adSettings'])->orderBy('name')->get()
            ->map(fn (Client $client): array => ['client' => $client, 'metrics' => Stats::totals($client->activeAdAccountIds(), $period)])
            ->filter(fn (array $row): bool => $row['metrics']->hasData())
            ->values();
    }

    /** @return Collection<int, User> */
    private function recipients(AdsSettings $settings): Collection
    {
        $configured = collect($settings->digest_recipients)->map(fn ($email): string => trim((string) $email))->filter();

        return User::query()
            ->when($configured->isNotEmpty(), fn ($query) => $query->whereIn('email', $configured))
            ->orderBy('name')
            ->get();
    }
}
