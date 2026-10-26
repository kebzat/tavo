<?php

namespace App\Support\Ads\Platforms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Společné volání Google API: přístupový token (hodinu platný, drží se
 * v cache) a převod chyb do české hlášky pro log synchronizace.
 */
trait GoogleApi
{
    /** Název služby do hlášek, třeba „Google Ads“. */
    abstract protected function serviceName(): string;

    /** Klíč cache pro přístupový token. */
    abstract protected function tokenCacheKey(): string;

    /** Klíč platformy pro ApiGuard a config (google_ads, ga4). */
    abstract protected function guardKey(): string;

    /**
     * Získá nový přístupový token. Vrací tělo odpovědi token endpointu.
     *
     * @return array<string, mixed>
     */
    abstract protected function fetchToken(): array;

    protected function accessToken(): string
    {
        return Cache::remember($this->tokenCacheKey(), now()->addMinutes(50), function (): string {
            $body = $this->fetchToken();

            if (blank($body['access_token'] ?? null)) {
                throw new AdsApiException(
                    $this->serviceName().': přístup odmítnut ('.($body['error_description'] ?? $body['error'] ?? 'bez tokenu').')',
                    authFailed: true,
                );
            }

            return (string) $body['access_token'];
        });
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    protected function google(callable $call): array
    {
        $guard = ApiGuard::for($this->guardKey());
        $guard->before();

        try {
            // Znovu jen při výpadku spojení nebo chybě serveru, nikdy po „moc dotazů“.
            $response = $call(Http::acceptJson()->timeout(60)->retry(
                2,
                2000,
                fn (\Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            )->withToken($this->accessToken()));
        } catch (ConnectionException $e) {
            throw new AdsApiException($this->serviceName().' neodpovídá: '.$e->getMessage());
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        // Google Ads vrací pole chyb, ostatní API jeden objekt.
        $error = $response->json('error') ?? $response->json('0.error') ?? [];
        $status = (string) ($error['status'] ?? '');
        $message = (string) ($error['message'] ?? 'HTTP '.$response->status());
        $failure = $this->adsFailure($error);

        if ($status === 'UNAUTHENTICATED' || $response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        if ($status === 'RESOURCE_EXHAUSTED' || $response->status() === 429) {
            $guard->pauseUntilTomorrow($this->serviceName().' omezil počet dotazů: '.$message);
        }

        throw new AdsApiException(
            match (true) {
                $status === 'UNAUTHENTICATED' || $response->status() === 401 => $this->serviceName().': neplatné přihlášení ('.$message.')',
                $failure !== null => $this->serviceName().': '.$failure,
                $status === 'PERMISSION_DENIED' || $response->status() === 403 => $this->serviceName().': k účtu nemáme přístup. Nasdílel ho klient Taveo? ('.$message.')',
                $status === 'RESOURCE_EXHAUSTED' || $response->status() === 429 => $this->serviceName().' omezil počet dotazů. Stahování je pozastavené do zítřejšího rána ('.$message.')',
                default => $this->serviceName().' vrátil chybu: '.$message,
            },
            authFailed: $status === 'UNAUTHENTICATED',
        );
    }

    /**
     * Google Ads posílá obecné „The caller does not have permission“ a skutečný
     * důvod (kód a hlášku) v details. Známé kódy převede na radu, co udělat.
     *
     * @param  array<string, mixed>  $error
     */
    private function adsFailure(array $error): ?string
    {
        $first = collect($error['details'] ?? [])->pluck('errors')->flatten(1)->first();

        if (! is_array($first) || ! is_array($first['errorCode'] ?? null)) {
            return null;
        }

        $code = (string) collect($first['errorCode'])->first();
        $detail = $code.(filled($first['message'] ?? null) ? ': '.$first['message'] : '');

        return match ($code) {
            'DEVELOPER_TOKEN_NOT_APPROVED' => 'developer token má jen testovací přístup. V MCC → Nástroje → Centrum API požádejte o Základní přístup. ('.$detail.')',
            'DEVELOPER_TOKEN_PROHIBITED' => 'developer token nejde použít s tímto Google Cloud projektem. ('.$detail.')',
            'USER_PERMISSION_DENIED' => 'Google účet, kterým vznikl refresh token, nemá přístup k MCC nebo k účtu klienta. Refresh token vytvořte účtem, který je uživatelem MCC. ('.$detail.')',
            'CUSTOMER_NOT_ENABLED' => 'účet není aktivní (zrušený nebo nedokončený). ('.$detail.')',
            default => $detail,
        };
    }

    /**
     * Token z OAuth endpointu Googlu. Chybu vrátí v těle, ať ji accessToken() popíše.
     *
     * @param  array<string, string>  $form
     * @return array<string, mixed>
     */
    protected function tokenRequest(string $url, array $form): array
    {
        try {
            return Http::asForm()->timeout(30)->post($url, $form)->json() ?? [];
        } catch (ConnectionException $e) {
            throw new AdsApiException($this->serviceName().' neodpovídá: '.$e->getMessage());
        }
    }
}
