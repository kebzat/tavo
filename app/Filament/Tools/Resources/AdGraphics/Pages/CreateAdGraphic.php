<?php

namespace App\Filament\Tools\Resources\AdGraphics\Pages;

use App\Filament\Tools\Resources\AdGraphics\AdGraphicResource;
use App\Models\AdGraphic;
use App\Settings\AdGraphicsSettings;
use App\Support\AdGraphics\Formats;
use App\Support\AdGraphics\GraphicsAgent;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateAdGraphic extends CreateRecord
{
    protected static string $resource = AdGraphicResource::class;

    protected static ?string $title = 'Nová reklamní grafika';

    protected static bool $canCreateAnother = false;

    /** Formuláře se ukládají jinak, než vypadají: předvolby + vlastní rozměry, cesty + jména souborů. */
    protected function handleRecordCreation(array $data): Model
    {
        $formats = Formats::resolve($data['presets'] ?? [], $data['custom_formats'] ?? []);

        if ($formats === []) {
            throw ValidationException::withMessages(['data.presets' => 'Vyberte aspoň jeden formát nebo zadejte vlastní rozměr.']);
        }

        $names = $data['asset_names'] ?? [];
        $graphic = AdGraphic::create([
            'client_id' => $data['client_id'] ?? null,
            'project' => $data['project'],
            'title' => $data['title'],
            'brief' => $data['brief'],
            'formats' => $formats,
            'variants' => (int) $data['variants'],
            'every_format' => (bool) ($data['every_format'] ?? false),
            'website_url' => $data['website_url'] ?? null,
            'fonts' => $data['fonts'] ?? null,
            'colors' => array_values(array_filter($data['colors'] ?? [])),
            'inspiration' => $data['inspiration'] ?? null,
            'assets' => collect($data['asset_paths'] ?? [])->values()->map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)])->all(),
            'ai_images' => (bool) ($data['ai_images'] ?? false) && app(AdGraphicsSettings::class)->imagesEnabled(),
            'author' => auth()->user()?->name,
        ]);

        app(GraphicsAgent::class)->start($graphic);

        return $graphic->refresh();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Poslat agentovi');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->getRecord()->status === AdGraphic::STATUS_FAILED
            ? 'Zadání je uložené, ale agenta se nepodařilo spustit'
            : 'Agent se pustil do práce';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
