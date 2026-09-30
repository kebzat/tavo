<?php

namespace App\Support\Ads\Platforms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
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
        try {
            $response = $call(Http::acceptJson()->timeout(60)->retry(2, 1000, throw: false)->withToken($this->accessToken()));
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

        if ($status === 'UNAUTHENTICATED' || $response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        throw new AdsApiException(
            match (true) {
                $status === 'UNAUTHENTICATED' || $response->status() === 401 => $this->serviceName().': neplatné přihlášení ('.$message.')',
                $status === 'PERMISSION_DENIED' || $response->status() === 403 => $this->serviceName().': k účtu nemáme přístup. Nasdílel ho klient Taveo? ('.$message.')',
                $status === 'RESOURCE_EXHAUSTED' || $response->status() === 429 => $this->serviceName().' omezil počet dotazů, zkusíme to příště ('.$message.')',
                default => $this->serviceName().' vrátil chybu: '.$message,
            },
            authFailed: $status === 'UNAUTHENTICATED',
        );
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
