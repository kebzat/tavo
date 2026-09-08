<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ověření Cloudflare Turnstile u poptávkového formuláře.
 *
 * Honeypot chytí jen roboty, kteří slepě vyplní každé pole. Turnstile pozná
 * i ty, co si formulář umí přečíst, a přitom po návštěvníkovi nechce opisovat
 * zdeformovaná písmena.
 *
 * Bez vyplněných klíčů se ověření nepoužije, viz `enabled()`. Lokální vývoj
 * i testy tak běží bez účtu u Cloudflare.
 */
class Turnstile implements ValidationRule
{
    private const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private ?string $ip = null) {}

    /** Ověřuje se jen tehdy, když je v `.env` tajný klíč. */
    public static function enabled(): bool
    {
        return filled(config('services.turnstile.secret'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $response = Http::asForm()->timeout(5)->post(self::ENDPOINT, array_filter([
                'secret' => config('services.turnstile.secret'),
                'response' => is_string($value) ? $value : '',
                'remoteip' => $this->ip,
            ]));
        } catch (\Throwable $e) {
            // Cloudflare je nedostupný. Poptávku pustíme dál: přijít o skutečnou
            // zakázku bolí víc než pustit jeden spam. Do logu to ale patří.
            Log::warning('Turnstile se nepodařilo ověřit, poptávka prošla bez kontroly', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($response->failed()) {
            Log::warning('Turnstile odpověděl chybou, poptávka prošla bez kontroly', [
                'status' => $response->status(),
            ]);

            return;
        }

        if ($response->json('success') === true) {
            return;
        }

        Log::info('Turnstile odmítl odeslání formuláře', [
            'codes' => $response->json('error-codes'),
        ]);

        $fail('Ověření, že nejste robot, se nepodařilo. Načtěte prosím stránku znovu a zkuste to ještě jednou.');
    }
}
