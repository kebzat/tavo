<?php

namespace App\Support\Ads;

use App\Enums\Ads\AdPlatform;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Platforms\AccountInfo;
use App\Support\Ads\Platforms\AdsApiException;
use App\Support\Ads\Platforms\Ga4;
use App\Support\Ads\Platforms\Platforms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Účty, které nám klienti nasdíleli a které ještě nejsou u žádného klienta.
 *
 * Seznam stahuje jen ranní `ads:sync` (refresh(), jeden dotaz na platformu)
 * a ukládá do ad_shared_accounts. Nabídka při propojování čte jen odsud, na
 * Metu nesahá. Nově nasdílený účet se v nabídce objeví druhý den ráno.
 * Ukázkové platformy nic nestahují, ty se čtou rovnou.
 */
class AccountDirectory
{
    public function __construct(private readonly Platforms $platforms) {}

    /** Stáhne seznam z platformy. Volá jen ranní stažení. */
    public function refresh(AdPlatform $platform): int
    {
        try {
            $accounts = $this->platforms->for($platform)->accounts();
        } catch (AdsApiException $e) {
            Cache::forever($this->stateKey($platform), ['checked_at' => now()->timestamp, 'error' => $e->getMessage()]);

            throw $e;
        }

        $now = now();

        DB::transaction(function () use ($platform, $accounts, $now): void {
            DB::table('ad_shared_accounts')->upsert(
                $accounts->map(fn (AccountInfo $account): array => [
                    'platform' => $platform->value,
                    'external_id' => $account->externalId,
                    'name' => $account->name,
                    'currency' => $account->currency ?: null,
                    'timezone' => $account->timezone,
                    'status' => $account->status,
                    'business' => $account->business,
                    'seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['platform', 'external_id'],
                ['name', 'currency', 'timezone', 'status', 'business', 'seen_at', 'updated_at'],
            );

            // Co dnes platforma nevrátila, nám klient přestal sdílet.
            DB::table('ad_shared_accounts')->where('platform', $platform->value)->where('seen_at', '<', $now)->delete();
        });

        Cache::forever($this->stateKey($platform), ['checked_at' => $now->timestamp, 'error' => null]);

        return $accounts->count();
    }

    /** @return Collection<int, AccountInfo> */
    public function available(AdPlatform $platform): Collection
    {
        $connected = AdAccount::query()->where('platform', $platform)->pluck('external_id')->all();

        return $this->all($platform)
            ->reject(fn (AccountInfo $account): bool => in_array($account->externalId, $connected, true))
            ->values();
    }

    /** @return array<string, string> external_id => popisek do výběru */
    public function options(AdPlatform $platform): array
    {
        return $this->available($platform)->mapWithKeys(fn (AccountInfo $account): array => [$account->externalId => $account->label()])->all();
    }

    /** Proč je nabídka prázdná. Null, když je všechno v pořádku. */
    public function problem(AdPlatform $platform): ?string
    {
        if (! $this->platforms->for($platform)->isConfigured()) {
            return 'Chybí přístup k '.$platform->shortLabel().' v .env (viz docs/ADS.md).';
        }

        if ($error = $this->lastError($platform)) {
            return 'Ranní stažení seznamu účtů selhalo: '.$error;
        }

        if (! $platform->isDemo() && $this->checkedAt($platform) === null) {
            return 'Seznam nasdílených účtů se stáhne zítra ráno v 6:00.';
        }

        return $this->available($platform)->isEmpty() ? $this->howToShare($platform) : null;
    }

    /** Kdy ranní stažení naposledy načetlo seznam. */
    public function checkedAt(AdPlatform $platform): ?Carbon
    {
        $timestamp = $this->state($platform)['checked_at'] ?? null;

        return $timestamp ? Carbon::createFromTimestamp($timestamp) : null;
    }

    /** Chyba posledního ranního stažení seznamu. */
    public function lastError(AdPlatform $platform): ?string
    {
        return $this->state($platform)['error'] ?? null;
    }

    public function count(AdPlatform $platform): int
    {
        return $this->all($platform)->count();
    }

    /** Co musí klient udělat, aby se nám účet objevil v nabídce. */
    public function howToShare(AdPlatform $platform): string
    {
        return 'Žádný nový účet. '.match ($platform) {
            AdPlatform::Meta => 'Klient ho musí nasdílet Business Manageru Taveo jako partnerovi a my ho přiřadit systémovému uživateli. V nabídce se objeví druhý den ráno.',
            AdPlatform::GoogleAds => 'Klient musí přijmout žádost o propojení s MCC Taveo. V nabídce se objeví druhý den ráno.',
            AdPlatform::Ga4 => 'Klient musí v GA4 přidat '.(app(Ga4::class)->serviceAccountEmail() ?? 'náš servisní účet').' jako čtenáře. V nabídce se objeví druhý den ráno.',
            AdPlatform::Demo, AdPlatform::DemoGa4 => 'Všechny ukázkové účty už jsou propojené.',
        };
    }

    public function find(AdPlatform $platform, string $externalId): ?AccountInfo
    {
        return $this->all($platform)->first(fn (AccountInfo $account): bool => $account->externalId === $externalId);
    }

    /** @return Collection<int, AccountInfo> */
    private function all(AdPlatform $platform): Collection
    {
        // Ukázková data nikam nevolají, generují se na místě.
        if ($platform->isDemo()) {
            return $this->platforms->for($platform)->accounts();
        }

        return DB::table('ad_shared_accounts')
            ->where('platform', $platform->value)
            ->orderBy('name')
            ->get()
            ->map(fn (object $row): AccountInfo => new AccountInfo(
                externalId: $row->external_id,
                name: $row->name,
                currency: (string) $row->currency,
                timezone: $row->timezone,
                status: $row->status,
                business: $row->business,
            ));
    }

    /** @return array{checked_at?: int, error?: ?string} */
    private function state(AdPlatform $platform): array
    {
        return Cache::get($this->stateKey($platform), []);
    }

    private function stateKey(AdPlatform $platform): string
    {
        return 'ads.directory.'.$platform->value;
    }
}
