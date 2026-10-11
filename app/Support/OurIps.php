<?php

namespace App\Support;

use App\Models\ClientView;
use App\Models\StaffIp;
use App\Settings\CrmSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Naše IP adresy: otevření sdíleného dokumentu z nich není otevření klientem.
 *
 * Dva zdroje. Adresy, ze kterých byl někdo z nás přihlášený (staff_ips,
 * zapisuje RememberStaffIp), a ruční seznam v Nastavení CRM. Přihlášenou
 * adresu bereme za naši jen měsíc před a po tom, kdy jsme na ní byli,
 * protože domácí a mobilní adresy se po čase přidělí někomu jinému.
 * Ruční seznam platí napořád.
 *
 * IPv6 se porovnává podle prefixu /64. Konec adresy se u jednoho připojení
 * mění, prefix zůstává.
 */
class OurIps
{
    private const WINDOW_DAYS = 30;

    public function __construct(private CrmSettings $settings) {}

    /** IPv4 adresa beze změny, u IPv6 její síť /64. Neplatná adresa vrátí null. */
    public static function network(?string $ip): ?string
    {
        $ip = preg_replace('~/\d+$~', '', trim((string) $ip));

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return inet_ntop(substr(inet_pton($ip), 0, 8).str_repeat("\0", 8)).'/64';
        }

        return null;
    }

    /**
     * Zapíše adresu přihlášeného a přepočítá dokumenty, které z ní někdo
     * otevřel. Tím zmizí i naše otevření z doby, kdy jsme přihlášení nebyli.
     */
    public function remember(string $ip, ?int $userId): void
    {
        $network = self::network($ip);

        if ($network === null) {
            return;
        }

        $staff = StaffIp::firstOrNew(['network' => $network]);
        $staff->fill(['ip' => $ip, 'user_id' => $userId ?? $staff->user_id, 'last_seen_at' => now()]);
        $staff->first_seen_at ??= now();
        $staff->save();

        $this->recount(ClientView::query()->where('network', $network));
    }

    /** Přepočítá všechny dokumenty. Po změně ručního seznamu. */
    public function recountAll(): void
    {
        $this->recount(ClientView::query());
    }

    /**
     * Id otevření, která jsou naše.
     *
     * @param  Collection<int, ClientView>  $views
     * @return list<int>
     */
    public function oursIds(Collection $views): array
    {
        $manual = collect($this->settings->ignored_ips)->map(fn ($ip): ?string => self::network((string) $ip))->filter()->all();
        $staff = StaffIp::query()
            ->whereIn('network', $views->pluck('network')->filter()->unique())
            ->get()
            ->keyBy('network');

        return $views
            ->filter(function (ClientView $view) use ($manual, $staff): bool {
                if ($view->network === null) {
                    return false;
                }

                if (in_array($view->network, $manual, true)) {
                    return true;
                }

                $seen = $staff->get($view->network);

                return $seen !== null && $view->viewed_at->between(
                    $seen->first_seen_at->copy()->subDays(self::WINDOW_DAYS),
                    $seen->last_seen_at->copy()->addDays(self::WINDOW_DAYS),
                );
            })
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ClientView>  $views
     * @return Collection<int, ClientView>
     */
    public function withoutOurs(Collection $views): Collection
    {
        $ours = $this->oursIds($views);

        return $views->reject(fn (ClientView $view): bool => in_array($view->id, $ours, true))->values();
    }

    /** @param  Builder<ClientView>  $views */
    private function recount(Builder $views): void
    {
        $views->select('viewable_type', 'viewable_id')
            ->distinct()
            ->get()
            ->each(fn (ClientView $view) => $view->viewable?->recountViews());
    }
}
