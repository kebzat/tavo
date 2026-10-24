<?php

namespace App\Filament\Tools\Pages\Ads;

use App\Models\AdGraphic;
use App\Settings\AdGraphicsSettings;
use App\Support\AdGraphics\NanoBanana;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * Nastavení reklamní grafiky: klíč ke Google Gemini (Nano Banana). Bez klíče
 * agent obrázky negeneruje a pracuje jen s nahranými podklady a fotkami
 * z webu klienta. Klíč je v databázi šifrovaně a agent ho nevidí.
 */
class ManageAdGraphics extends SettingsPage
{
    protected static string $settings = AdGraphicsSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Nastavení grafiky';

    protected static ?string $slug = 'reklamy/grafika-nastaveni';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Generování obrázků (Nano Banana)')
                ->columnSpanFull()
                ->description(new HtmlString('Klíč z <a class="underline" href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studia</a> (Get API key). Platí se za obrázek, u Nano Banana zhruba 1 Kč. Bez klíče agent obrázky negeneruje, ostatní funguje.'))
                ->schema([
                    TextEntry::make('state')
                        ->label('Stav')
                        ->state(fn (): string => app(AdGraphicsSettings::class)->imagesEnabled() ? 'Zapnuté, klíč je uložený' : 'Vypnuté, chybí klíč')
                        ->color(fn (): string => app(AdGraphicsSettings::class)->imagesEnabled() ? 'success' : 'gray'),
                    TextEntry::make('last_error')
                        ->label('Poslední chyba při generování')
                        ->state(fn (): ?string => ($graphic = $this->lastError()) ? $graphic->image_error.' ('.$graphic->updated_at->format('j. n. H:i').', zadání „'.$graphic->title.'“)' : null)
                        ->color('danger')
                        ->visible(fn (): bool => $this->lastError() !== null),
                    TextInput::make('gemini_api_key')
                        ->label('API klíč')
                        ->password()
                        ->revealable()
                        ->autocomplete('off')
                        ->maxLength(200)
                        ->helperText('Ukládá se šifrovaně. Smazáním klíče se generování vypne.'),
                    TextInput::make('gemini_model')
                        ->label('Model')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Nano Banana je gemini-2.5-flash-image. Novější model jde přepsat tady, když ho Google vydá.'),
                    TextInput::make('max_images')
                        ->label('Nejvýš obrázků na zadání')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(50)
                        ->required()
                        ->helperText('Pojistka útraty. Počítá se i nepovedený pokus.'),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('check')
                ->label('Ověřit klíč')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('gray')
                ->visible(fn (): bool => app(AdGraphicsSettings::class)->imagesEnabled())
                ->action(function (NanoBanana $images): void {
                    try {
                        Notification::make()->title('Klíč platí')->body('Model: '.$images->check().'. Nic se nezaplatilo. Jestli jdou i obrázky (na bezplatném tarifu Googlu ne), ukáže Vyzkoušet obrázek.')->success()->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->title('Klíč nefunguje')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            Action::make('try')
                ->label('Vyzkoušet obrázek')
                ->icon(Heroicon::OutlinedPhoto)
                ->color('gray')
                ->visible(fn (): bool => app(AdGraphicsSettings::class)->imagesEnabled())
                ->requiresConfirmation()
                ->modalDescription('Vygeneruje jeden malý zkušební obrázek. U Googlu to stojí zhruba 1 Kč.')
                ->action(function (NanoBanana $images): void {
                    try {
                        $image = $images->generate('A simple photo of a cup of tea on a wooden table, soft daylight.');
                        Notification::make()->title('Obrázky fungují')->body('Google vrátil obrázek ('.round(strlen($image['data']) / 1024).' kB).')->success()->send();
                    } catch (RuntimeException $e) {
                        Notification::make()->title('Obrázek se nevygeneroval')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }

    /** Zadání s poslední chybou generování obrázků (za týden). */
    private function lastError(): ?AdGraphic
    {
        return AdGraphic::query()->whereNotNull('image_error')->where('updated_at', '>', now()->subWeek())->latest('updated_at')->first();
    }
}
