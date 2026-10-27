<?php

namespace App\Support\Automations;

use App\Enums\UserRole;
use App\Mail\AutomationProblem;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Upozornění e-mailem, když automatizace nejede: správcům TAVEO a účtům
 * klienta. Stejný problém nejvýš jednou za 6 hodin, běh je každou hodinu
 * a e-mail každou hodinu by se přestal číst.
 */
class AutomationAlerts
{
    public const THROTTLE_HOURS = 6;

    /** @return bool jestli upozornění odešlo (false = stejné už odešlo nedávno) */
    public function problem(Automation $automation, string $key, string $problem, string $advice): bool
    {
        if (! Cache::add('automation-alert:'.$automation->getKey().':'.$key, true, now()->addHours(self::THROTTLE_HOURS))) {
            return false;
        }

        foreach (self::recipients($automation) as $user) {
            try {
                Mail::to($user->email)->send(new AutomationProblem($automation, $user, $problem, $advice));
            } catch (Throwable $e) {
                Log::error('Upozornění automatizace se nepodařilo odeslat.', ['automation' => $automation->getKey(), 'user' => $user->getKey(), 'error' => $e->getMessage()]);
            }
        }

        return true;
    }

    /**
     * Správci a účty klienta, kterému automatizace patří.
     *
     * @return Collection<int, User>
     */
    public static function recipients(Automation $automation): Collection
    {
        return User::query()
            ->where(fn ($query) => $query
                ->where('role', UserRole::Admin->value)
                ->orWhere(fn ($query) => $query->where('role', UserRole::Client->value)->where('client_id', $automation->client_id)))
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, User> jen účty klienta (ranní souhrn) */
    public static function clientUsers(Automation $automation): Collection
    {
        return User::query()->where('role', UserRole::Client->value)->where('client_id', $automation->client_id)->orderBy('id')->get();
    }
}
