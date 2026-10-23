<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AdGraphic;
use App\Settings\AdGraphicsSettings;
use App\Support\AdGraphics\Formats;
use App\Support\AdGraphics\NanoBanana;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agent při práci na reklamní grafice: stáhne nahrané podklady a nechá si
 * vygenerovat obrázek přes Gemini. Přístup jen s tokenem zadání (hlavička
 * X-Grafika-Token) a jen dokud úloha běží. Klíč ke Gemini zůstává tady.
 */
class AdGraphicApiController extends Controller
{
    public function asset(Request $request, AdGraphic $graphic, int $index): Response
    {
        $this->authorizeAgent($request, $graphic);

        $asset = ($graphic->assets ?? [])[$index] ?? null;
        abort_unless(is_array($asset) && Storage::disk('local')->exists($asset['path'] ?? ''), 404);

        return Storage::disk('local')->download($asset['path'], basename($asset['name'] ?? $asset['path']));
    }

    public function image(Request $request, AdGraphic $graphic, NanoBanana $images, AdGraphicsSettings $settings): Response
    {
        $this->authorizeAgent($request, $graphic);
        abort_unless($graphic->ai_images && $images->configured(), 403, 'Generování obrázků je u tohohle zadání vypnuté.');
        abort_if($graphic->ai_images_used >= $settings->max_images, 429, 'Vyčerpaný počet obrázků na zadání ('.$settings->max_images.').');

        $data = $request->validate([
            'popis' => ['required', 'string', 'max:4000'],
            'pomer' => ['nullable', 'string', 'regex:/^\d{1,4}:\d{1,4}$/'],
            'predloha' => ['nullable', 'array', 'max:3'],
            'predloha.*' => ['file', 'mimes:png,jpg,jpeg,webp', 'max:8192'],
        ]);

        [$width, $height] = array_map('intval', explode(':', $data['pomer'] ?? '1:1'));
        $references = collect($request->file('predloha', []))
            ->map(fn (UploadedFile $file): array => ['mime' => (string) $file->getMimeType(), 'data' => (string) $file->get()])
            ->all();

        // Počítá se i nepovedený pokus, platí se za něj taky.
        $graphic->increment('ai_images_used');
        set_time_limit(180);

        try {
            $image = $images->generate($data['popis'], Formats::aspectRatio($width ?: 1, $height ?: 1), $references);
        } catch (RuntimeException $e) {
            Log::info('Reklamní grafika: Gemini obrázek nevytvořil.', ['graphic' => $graphic->getKey(), 'error' => $e->getMessage()]);

            return response()->json(['chyba' => $e->getMessage()], 502);
        }

        return response($image['data'], 200, [
            'Content-Type' => $image['mime'],
            'X-Obrazku-Zbyva' => (string) max(0, $settings->max_images - $graphic->ai_images_used),
        ]);
    }

    private function authorizeAgent(Request $request, AdGraphic $graphic): void
    {
        abort_unless($graphic->tokenMatches($request->header('X-Grafika-Token')), 401, 'Neplatný token.');
        abort_unless($graphic->status === AdGraphic::STATUS_RUNNING, 409, 'Zadání už neběží.');
    }
}
