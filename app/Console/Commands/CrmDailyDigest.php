<?php

namespace App\Console\Commands;

use App\Mail\CrmDailyDigest as DigestMail;
use App\Models\Crm\Company;
use App\Models\Crm\Demand;
use App\Models\User;
use App\Settings\CrmSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Ranní souhrn obchodu do e-mailu.
 *
 * Obsahem odpovídá přehledu „Dnes" — smyslem je, aby se do CRM nemuselo chodit
 * jen kvůli zjištění, jestli tam něco je.
 */
class CrmDailyDigest extends Command
{
    protected $signature = 'crm:daily-digest {--dry-run : Jen vypíše, komu by souhrn odešel}';

    protected $description = 'Rozešle ranní souhrn follow-upů a poptávek';

    public function handle(CrmSettings $settings): int
    {
        $recipients = $this->recipients($settings);

        if ($recipients->isEmpty()) {
            $this->warn('Žádný příjemce — souhrn se neposílá.');

            return self::SUCCESS;
        }

        // Bloky jsou pro všechny stejné. CRM vedeme ve dvou nad společnými
        // firmami, takže dělit souhrn podle vlastníka by jen skryl to,
        // co druhému uteklo.
        $overdue = $this->companies(fn ($query) => $query->overdue());
        $dueToday = $this->companies(fn ($query) => $query->dueToday());
        $stale = $this->companies(fn ($query) => $query->stale()->orderBy('last_activity_at'), 10);
        $demands = Demand::query()->untouched()->limit(10)->get();

        foreach ($recipients as $user) {
            if ($this->option('dry-run')) {
                $this->line("Souhrn by šel na {$user->email}.");

                continue;
            }

            try {
                Mail::to($user->email)->send(new DigestMail($user, $overdue, $dueToday, $demands, $stale));

                $this->info("Souhrn odeslán na {$user->email}.");
            } catch (\Throwable $e) {
                // Nefunkční mailer nesmí shodit naplánovaný běh — a hlavně
                // nesmí zůstat bez stopy.
                Log::error('Ranní souhrn CRM se nepodařilo odeslat.', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);

                $this->error("Odeslání na {$user->email} selhalo: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Komu souhrn chodí. Nastavení má přednost; když je prázdné, dostanou ho
     * všechny účty — dva lidi v CRM znamenají, že adresář nemá co skrývat.
     *
     * @return Collection<int, User>
     */
    private function recipients(CrmSettings $settings): Collection
    {
        $configured = collect($settings->digest_recipients)
            ->map(fn ($email): string => trim((string) $email))
            ->filter();

        if ($configured->isEmpty()) {
            return User::query()->orderBy('name')->get();
        }

        return User::query()->whereIn('email', $configured)->orderBy('name')->get();
    }

    /** @return Collection<int, Company> */
    private function companies(callable $filter, int $limit = 25): Collection
    {
        return $filter(Company::query())
            ->with(['activities' => fn ($query) => $query->latest('happened_at')->limit(1)])
            ->workOrder()
            ->limit($limit)
            ->get();
    }
}
