<?php

namespace App\Filament\Tools\Resources\Checklists\RelationManagers;

use App\Enums\ChecklistItemStatus;
use App\Models\Checklist;
use App\Models\ChecklistSection;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Podnadpisy uvnitř kategorií. Sahá se sem jen při úpravě struktury,
 * běžná práce se odehrává v seznamu položek.
 */
class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    protected static ?string $title = 'Sekce';

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->formComponents());
    }

    /** @return array<int, mixed> */
    private function formComponents(): array
    {
        return [
            Select::make('checklist_category_id')
                ->label('Kategorie')
                ->options(fn (): array => $this->categoryOptions())
                ->required(),

            TextInput::make('title')
                ->label('Název sekce')
                ->required(),

            Textarea::make('description')
                ->label('Popis')
                ->rows(2)
                ->helperText('Uvozuje sekci na sdílené stránce. Můžete nechat prázdné.'),

            TextInput::make('order_column')
                ->label('Pořadí v kategorii')
                ->numeric()
                ->default(0),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->defaultSort('order_column')
            ->columns([
                TextColumn::make('category.title')
                    ->label('Kategorie')
                    ->badge(),

                TextColumn::make('title')
                    ->label('Sekce')
                    ->weight('bold')
                    ->description(fn (ChecklistSection $record): ?string => $record->description),

                TextColumn::make('items_count')
                    ->label('Položek')
                    ->counts('items'),

                TextColumn::make('finished_items_count')
                    ->label('Hotovo')
                    ->counts([
                        'items as finished_items_count' => fn ($query) => $query->whereIn('status', [
                            ChecklistItemStatus::Done->value,
                            ChecklistItemStatus::Skipped->value,
                        ]),
                    ]),
            ])
            ->filters([
                SelectFilter::make('checklist_category_id')
                    ->label('Kategorie')
                    ->options(fn (): array => $this->categoryOptions()),
            ])
            ->headerActions([
                // Sekce visí na kategorii, ne na checklistu, takže je nelze
                // založit přes vztah. Kategorii vybíráme ručně.
                Action::make('addSection')
                    ->label('Přidat sekci')
                    ->schema($this->formComponents())
                    ->action(function (array $data): void {
                        ChecklistSection::create($data);

                        Notification::make()->success()->title('Sekce přidána')->send();
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

    /** @return array<int, string> */
    private function categoryOptions(): array
    {
        /** @var Checklist $checklist */
        $checklist = $this->getOwnerRecord();

        return $checklist->categories()->ordered()->pluck('title', 'id')->all();
    }
}
