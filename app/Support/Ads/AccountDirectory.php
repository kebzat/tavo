<?php

namespace App\Support\Ads;

use App\Enums\Ads\AdPlatform;
use App\Models\Ads\AdAccount;
use App\Support\Ads\Platforms\AccountInfo;
use App\Support\Ads\Platforms\AdsApiException;
use App\Support\Ads\Platforms\Ga4;
use App\Support\Ads\Platforms\Platforms;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Účty, které nám klienti nasdíleli a které ještě nejsou u žádného klienta.
 * Seznam se na pět minut pamatuje, formulář se při psaní překresluje.
 */
class AccountDirectory
{
    public function __construct(private readonly Platforms $platforms) {}

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
        try {
            return $this->available($platform)->mapWithKeys(fn (AccountInfo $account): array => [$account->externalId => $account->label()])->all();
        } catch (AdsApiException) {
            return [];
        }
    }

    /** Proč je nabídka prázdná. Null, když je všechno v pořádku. */
    public function problem(AdPlatform $platform): ?string
    {
        if (! $this->platforms->for($platform)->isConfigured()) {
            return 'Chybí přístup k '.$platform->shortLabel().' v .env (viz docs/ADS.md).';
        }

        try {
            return $this->available($platform)->isEmpty() ? $this->howToShare($platform) : null;
        } catch (AdsApiException $e) {
            return $e->getMessage();
        }
    }

    /** Co musí klient udělat, aby se nám účet objevil v nabídce. */
    public function howToShare(AdPlatform $platform): string
    {
        return 'Žádný nový účet. '.match ($platform) {
            AdPlatform::Meta => 'Klient ho musí nasdílet Business Manageru Taveo jako partnerovi a my ho přiřadit systémovému uživateli.',
            AdPlatform::GoogleAds => 'Klient musí přijmout žádost o propojení s MCC Taveo.',
            AdPlatform::Ga4 => 'Klient musí v GA4 přidat '.(app(Ga4::class)->serviceAccountEmail() ?? 'náš servisní účet').' jako čtenáře.',
            AdPlatform::Demo, AdPlatform::DemoGa4 => 'Všechny ukázkové účty už jsou propojené.',
        };
    }

    public function find(AdPlatform $platform, string $externalId): ?AccountInfo
    {
        return $this->all($platform)->first(fn (AccountInfo $account): bool => $account->externalId === $externalId);
    }

    public function forget(AdPlatform $platform): void
    {
        Cache::forget($this->cacheKey($platform));
    }

    /** @return Collection<int, AccountInfo> */
    private function all(AdPlatform $platform): Collection
    {
        // Cache smí držet jen prostá pole, objekty by se zpátky nerozbalily
        // (cache.serializable_classes). Pamatuje se i chyba: formulář se
        // překresluje při každé změně a bez toho by se Mety ptal pořád dokola.
        $rows = Cache::remember(
            $this->cacheKey($platform),
            now()->addMinutes(5),
            function () use ($platform): array {
                try {
                    return $this->platforms->for($platform)->accounts()->map(fn (AccountInfo $account): array => (array) $account)->all();
                } catch (AdsApiException $e) {
                    return ['error' => $e->getMessage()];
                }
            },
        );

        if (isset($rows['error'])) {
            throw new AdsApiException($rows['error']);
        }

        return collect($rows)->map(fn (array $row): AccountInfo => new AccountInfo(...$row));
    }

    private function cacheKey(AdPlatform $platform): string
    {
        return 'ads.accounts.'.$platform->value;
    }
}
