<?php

namespace App\Filament\Tools\Pages;

use App\Filament\Tools\Resources\Companies\CompanyResource;
use App\Support\Crm\CompanyCsvImporter;
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
 * Import firem z tabulky rešerše.
 *
 * Postup je záměrně ve třech krocích: nahrát → zkontrolovat mapování a náhled →
 * importovat. Rešerše se dělá ručně a hlavička se mění, takže naslepo
 * naimportovaný soubor by se pak čistil hůř, než kdyby se nenaimportoval vůbec.
 */
class ImportCompanies extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Import firem';

    protected static ?string $title = 'Import firem z CSV';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 60;

    protected string $view = 'filament.tools.pages.import-companies';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Hlavička nahraného souboru. Prázdná, dokud se soubor nenačte. */
    public array $header = [];

    /** Prvních pět řádků k oční kontrole. */
    public array $preview = [];

    /** Kolik datových řádků soubor obsahuje. */
    public int $rowCount = 0;

    /** Souhrn po importu. Null, dokud import neproběhl. */
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
                    ->description('CSV v kódování UTF-8. Oddělovač čárka nebo středník — rozpozná se sám.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('Tabulka rešerše')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            // Soubor se nikam neukládá, jen se přečte. Nemá smysl
                            // držet na disku kopii tabulky, kterou máme v Google Sheets.
                            ->storeFiles(false)
                            ->live()
                            ->afterStateUpdated(fn ($state) => $this->readFile($state))
                            ->required(),
                    ]),

                Section::make('Mapování sloupců')
                    ->description('Předvolba sedí na obvyklou hlavičku rešerše. Zkontroluj ji a případně přemapuj.')
                    ->columns(2)
                    ->visible(fn (): bool => $this->header !== [])
                    ->schema($this->mappingFields()),
            ])
            ->statePath('data');
    }

    /**
     * Roletka pro každé pole karty firmy.
     *
     * @return array<int, Select>
     */
    private function mappingFields(): array
    {
        $fields = [];

        foreach (CompanyCsvImporter::FIELDS as $field => $definition) {
            $fields[] = Select::make('mapping.'.$field)
                ->label($definition['label'])
                ->options(fn (): array => $this->headerOptions())
                ->native(false)
                ->placeholder('Neimportovat')
                ->required($field === 'name');
        }

        return $fields;
    }

    /** @return array<int, string> */
    public function headerOptions(): array
    {
        $options = [];

        foreach ($this->header as $index => $name) {
            // Prázdný název sloupce se pozná aspoň podle pořadí.
            $options[$index] = $name !== '' ? $name : 'sloupec '.($index + 1);
        }

        return $options;
    }

    /** Načtení souboru hned po nahrání — hlavička, náhled a předvolba mapování. */
    public function readFile(mixed $state): void
    {
        $file = is_array($state) ? reset($state) : $state;

        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $importer = new CompanyCsvImporter;
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
                ->modalDescription(fn (): string => 'Založí se firmy z '.$this->rowCount.' řádků. Firmy s doménou, kterou už vedeme, se přeskočí.')
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

        $importer = new CompanyCsvImporter;
        $parsed = $importer->read($file->getRealPath());

        // Nenamapovaná pole přicházejí z formuláře jako prázdný řetězec.
        $mapping = collect($state['mapping'] ?? [])
            ->map(fn ($value) => $value === null || $value === '' ? null : (int) $value)
            ->all();

        $this->summary = $importer->import($parsed['rows'], $mapping, auth()->id());

        Notification::make()
            ->success()
            ->title('Import dokončen')
            ->body($this->summary['created'].' firem založeno, '.$this->summary['skipped'].' přeskočeno jako duplicita.')
            ->send();
    }

    public function companiesUrl(): string
    {
        return CompanyResource::getUrl('index');
    }
}
