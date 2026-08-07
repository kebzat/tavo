<?php

namespace App\Filament\Tools\Resources\Checklists\RelationManagers;

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/**
 * Hlavní pracovní obrazovka. Všechny položky checklistu na jednom místě,
 * seskupené po sekcích, se stavem přepínatelným přímo v řádku.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Položky';

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->formComponents());
    }

    /** @return array<int, mixed> */
    private function formComponents(): array
    {
        return [
            Select::make('checklist_section_id')
                ->label('Sekce')
                ->options(fn (): array => $this->sectionOptions())
                ->searchable()
                ->required(),

            TextInput::make('title')
                ->label('Položka')
                ->required(),

            Textarea::make('description')
                ->label('Vysvětlivka')
                ->rows(3)
                ->helperText('Zobrazí se klientovi pod názvem položky.'),

            Textarea::make('internal_note')
                ->label('Interní poznámka')
                ->rows(3)
                ->helperText('Jen pro nás. Na sdílenou stránku se nedostane.'),

            Select::make('priority')
                ->label('Priorita')
                ->options(ChecklistPriority::class)
                ->default(ChecklistPriority::Must)
                ->native(false),

            Select::make('status')
                ->label('Stav')
                ->options(ChecklistItemStatus::class)
                ->default(ChecklistItemStatus::Todo)
                ->native(false),

            TextInput::make('order_column')
                ->label('Pořadí v sekci')
                ->numeric()
                ->default(0),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            // Položky visí na checklistu napřímo, takže pořadí kategorií a sekcí
            // musíme dotáhnout joinem. Bez něj by se seskupení seřadilo podle
            // abecedy a rozpadlo by se pořadí, ve kterém checklist dává smysl číst.
            ->modifyQueryUsing(fn ($query) => $query
                ->with('section.category')
                ->select('checklist_items.*')
                ->join('checklist_sections', 'checklist_sections.id', '=', 'checklist_items.checklist_section_id')
                ->join('checklist_categories', 'checklist_categories.id', '=', 'checklist_sections.checklist_category_id')
                ->orderBy('checklist_categories.order_column')
                ->orderBy('checklist_sections.order_column')
                ->orderBy('checklist_items.order_column')
                ->orderBy('checklist_items.id'))
            ->groups([
                Group::make('section.title')
                    ->label('Sekce')
                    ->orderQueryUsing(fn ($query, string $direction) => $query
                        ->orderBy('checklist_categories.order_column', $direction)
                        ->orderBy('checklist_sections.order_column', $direction))
                    ->collapsible(),
            ])
            ->defaultGroup('section.title')
            ->columns([
                TextColumn::make('title')
                    ->label('Položka')
                    ->searchable()
                    ->wrap()
                    ->weight('bold')
                    ->description(fn (ChecklistItem $record): ?string => $record->description),

                SelectColumn::make('status')
                    ->label('Stav')
                    ->options(ChecklistItemStatus::class)
                    ->selectablePlaceholder(false)
                    ->width('12rem'),

                TextColumn::make('priority')
                    ->label('Priorita')
                    ->badge(),

                IconColumn::make('internal_note')
                    ->label('Poznámka')
                    ->boolean()
                    ->tooltip(fn (ChecklistItem $record): ?string => $record->internal_note),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(ChecklistItemStatus::class),

                SelectFilter::make('priority')
                    ->label('Priorita')
                    ->options(ChecklistPriority::class),

                SelectFilter::make('checklist_section_id')
                    ->label('Sekce')
                    ->options(fn (): array => $this->sectionOptions())
                    ->attribute('checklist_items.checklist_section_id'),
            ])
            ->headerActions([
                // Položky visí na sekci, ne na checklistu, takže je nelze
                // založit přes vztah — sekci vybíráme ručně.
                Action::make('addItem')
                    ->label('Přidat položku')
                    ->schema($this->formComponents())
                    ->action(function (array $data): void {
                        ChecklistItem::create($data);

                        Notification::make()->success()->title('Položka přidána')->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    /**
     * Sekce s předřazenou kategorií, ať se v roletce pozná „Nástroje"
     * z Měření od „Nástroje" odjinud.
     *
     * Klíč musí zůstat ID sekce. Skládá se proto ručně: flatMap() uvnitř
     * volá array_merge, který celočíselné klíče přečísluje na 0, 1, 2…
     * Select by pak nabízel neexistující ID a ukládání by spadlo na validaci.
     *
     * @return array<int, string>
     */
    private function sectionOptions(): array
    {
        /** @var Checklist $checklist */
        $checklist = $this->getOwnerRecord();

        $categories = $checklist->categories()
            ->ordered()
            ->with(['sections' => fn ($query) => $query->ordered()])
            ->get();

        $options = [];

        foreach ($categories as $category) {
            foreach ($category->sections as $section) {
                $options[$section->id] = $category->title.': '.$section->title;
            }
        }

        return $options;
    }
}
