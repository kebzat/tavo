<?php

namespace App\Filament\Tools\Pages;

use App\Filament\Tools\Resources\Demands\DemandResource;
use App\Support\Crm\DemandCsvImporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Import otevřených poptávek z tabulky rešerše.
 *
 * Protějšek importu firem. Tentýž soubor mívá druhý list s poptávkami
 * z portálů a bez téhle stránky by se daly dostat dovnitř jen strojovým
 * endpointem, tedy přes curl a token.
 */
class ImportDemands extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import poptávek';

    protected static ?string $title = 'Import poptávek z CSV';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 65;

    protected string $view = 'filament.tools.pages.import-demands';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public array $header = [];

    public array $preview = [];

    public int $rowCount = 0;

    public ?array $summary = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Soubor')
                    ->description('CSV v kódování UTF-8. Oddělovač čárka nebo středník, rozpozná se sám.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('List s poptávkami')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->storeFiles(false)
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->readFile($state))
                            ->required(),
                    ]),

                Section::make('Mapování sloupců')
                    ->description('Předvolba sedí na obvyklou hlavičku. Zkontroluj ji a případně přemapuj.')
                    ->columns(2)
                    ->visible(fn (): bool => $this->header !== [])
                    ->schema($this->mappingFields()),
            ])
            ->statePath('data');
    }

    /** @return array<int, Select> */
    private function mappingFields(): array
    {
        $fields = [];

        foreach (DemandCsvImporter::FIELDS as $field => $definition) {
            $fields[] = Select::make('mapping.'.$field)
                ->label($definition['label'])
                ->options(fn (): array => $this->headerOptions())
                ->native(false)
                ->placeholder('Neimportovat')
                ->required($field === 'url');
        }

        return $fields;
    }

    /** @return array<int, string> */
    public function headerOptions(): array
    {
        $options = [];

        foreach ($this->header as $index => $name) {
            $options[$index] = $name !== '' ? $name : 'sloupec '.($index + 1);
        }

        return $options;
    }

    public function readFile(mixed $state): void
    {
        $file = is_array($state) ? reset($state) : $state;

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $importer = new DemandCsvImporter;
        $parsed = $importer->read($file->getRealPath());

        $this->header = $parsed['header'];
        $this->preview = array_slice($parsed['rows'], 0, 5);
        $this->rowCount = count($parsed['rows']);
        $this->summary = null;

        $this->data['mapping'] = $importer->guessMapping($this->header);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Importovat')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (): bool => $this->header !== [])
                ->requiresConfirmation()
                ->modalHeading('Spustit import?')
                ->modalDescription(fn (): string => 'Projde se '.$this->rowCount.' řádků. Poptávka, kterou už podle odkazu vedeme, se jen aktualizuje; její stav a poznámky zůstanou beze změny.')
                ->modalSubmitActionLabel('Importovat')
                ->action(fn () => $this->import()),
        ];
    }

    public function import(): void
    {
        $state = $this->form->getState();
        $file = is_array($state['file'] ?? null) ? reset($state['file']) : ($state['file'] ?? null);

        if (! $file instanceof TemporaryUploadedFile) {
            Notification::make()->danger()->title('Soubor se nepodařilo načíst')->send();

            return;
        }

        $importer = new DemandCsvImporter;
        $parsed = $importer->read($file->getRealPath());

        $mapping = collect($state['mapping'] ?? [])
            ->map(fn ($value) => $value === null || $value === '' ? null : (int) $value)
            ->all();

        $this->summary = $importer->import($parsed['rows'], $mapping);

        Notification::make()
            ->success()
            ->title('Import dokončen')
            ->body($this->summary['created'].' nových, '.$this->summary['updated'].' aktualizovaných.')
            ->send();
    }

    public function demandsUrl(): string
    {
        return DemandResource::getUrl('index');
    }
}
