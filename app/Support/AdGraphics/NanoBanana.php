<?php

namespace App\Support\AdGraphics;

use App\Settings\AdGraphicsSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Obrázky přes Google Gemini (Nano Banana). Klíč je v nastavení grafiky
 * a ven z webu nejde: agent posílá jen popis a předlohy, dostane obrázek.
 */
class NanoBanana
{
    private const API = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(private readonly AdGraphicsSettings $settings) {}

    public function configured(): bool
    {
        return $this->settings->imagesEnabled();
    }

    /**
     * Jeden obrázek podle popisu. Předlohy (fotky produktu, styl) jdou s ním,
     * model z nich vychází.
     *
     * @param  list<array{mime: string, data: string}>  $references  data jako binární obsah
     * @return array{mime: string, data: string} binární obsah obrázku
     */
    public function generate(string $prompt, string $aspectRatio = '1:1', array $references = []): array
    {
        $parts = [['text' => $prompt]];

        foreach ($references as $reference) {
            $parts[] = ['inlineData' => ['mimeType' => $reference['mime'], 'data' => base64_encode($reference['data'])]];
        }

        $response = $this->send(fn (PendingRequest $http): Response => $http->timeout(120)->post($this->model().':generateContent', [
            'contents' => [['parts' => $parts]],
            'generationConfig' => [
                'responseModalities' => ['IMAGE'],
                'imageConfig' => ['aspectRatio' => $aspectRatio],
            ],
        ]));

        $image = collect($response->json('candidates.0.content.parts') ?? [])
            ->map(fn (array $part): ?array => $part['inlineData'] ?? $part['inline_data'] ?? null)
            ->filter(fn (?array $data): bool => filled($data['data'] ?? null))
            ->first();

        if (! $image) {
            $reason = $response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason') ?? 'bez obrázku';

            throw new RuntimeException('Gemini obrázek nevrátil ('.$reason.').');
        }

        return [
            'mime' => (string) ($image['mimeType'] ?? $image['mime_type'] ?? 'image/png'),
            'data' => (string) base64_decode((string) $image['data'], true),
        ];
    }

    /** Ověří klíč a model bez generování (nic se neplatí). Vrátí název modelu. */
    public function check(): string
    {
        return (string) ($this->send(fn (PendingRequest $http): Response => $http->get($this->model()))->json('displayName') ?: $this->model());
    }

    private function model(): string
    {
        return rawurlencode(trim($this->settings->gemini_model) ?: 'gemini-2.5-flash-image');
    }

    /** @param  callable(PendingRequest): Response  $call */
    private function send(callable $call): Response
    {
        if (! $this->configured()) {
            throw new RuntimeException('Chybí klíč ke Gemini (Reklamy → Nastavení grafiky).');
        }

        try {
            $response = $call(Http::baseUrl(self::API)
                ->withHeaders(['x-goog-api-key' => (string) $this->settings->gemini_api_key])
                ->acceptJson()
                ->timeout(15));
        } catch (ConnectionException $e) {
            throw new RuntimeException('Gemini neodpovídá: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException('Gemini odmítl požadavek ('.$response->status().'): '.($response->json('error.message') ?? 'bez popisu'));
        }

        return $response;
    }
}
