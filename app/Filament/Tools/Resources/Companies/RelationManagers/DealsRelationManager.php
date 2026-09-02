<?php

namespace App\Filament\Tools\Resources\Companies\RelationManagers;

use App\Enums\Crm\DealPackage;
use App\Enums\Crm\DealStage;
use App\Models\Crm\Deal;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DealsRelationManager extends RelationManager
{
    protected static string $relationship = 'deals';

    protected static ?string $title = 'Obchody';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Název')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('package')
                ->label('Balíček')
                ->options(DealPackage::class)
                ->default(DealPackage::Other)
                ->native(false)
                ->required(),

            TextInput::make('value_czk')
                ->label('Hodnota')
                ->numeric()
                ->minValue(0)
                ->suffix('Kč'),

            Select::make('stage')
                ->label('Fáze')
                ->options(DealStage::class)
                ->default(DealStage::Lead)
                ->native(false)
                ->live()
                ->required(),

            TextInput::make('probability')
                ->label('Pravděpodobnost')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                // Předvyplní se podle fáze, ale zůstává přepsatelná — u konkrétního
                // obchodu víme víc než tabulka výchozích hodnot.
                ->placeholder(fn ($get): string => (string) (DealStage::tryFrom((string) $get('stage'))?->defaultProbability() ?? 5))
                ->helperText('Prázdné = podle fáze.'),

            DatePicker::make('expected_close_at')->label('Očekávané uzavření'),

            Select::make('owner_id')
                ->label('Vede')
                ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->all())
                ->default(fn () => Auth::id())
                ->native(false),

            TextInput::make('lost_reason')
                ->label('Důvod prohry')
                ->maxLength(255)
                ->visible(fn ($get): bool => ($get('stage') instanceof DealStage ? $get('stage')->value : $get('stage')) === DealStage::Lost->value),

            Textarea::make('notes')->label('Poznámky')->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Obchod')
                    ->weight('bold')
                    ->wrap()
                    ->description(fn (Deal $record): string => $record->package->getLabel()),

                TextColumn::make('stage')->label('Fáze')->badge(),

                TextColumn::make('value_czk')
                    ->label('Hodnota')
                    ->money('CZK', locale: 'cs')
                    ->placeholder('—'),

                TextColumn::make('probability')->label('Pravděp.')->suffix(' %'),

                TextColumn::make('expected_close_at')
                    ->label('Uzavření')
                    ->date('j. n. Y')
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Přidat obchod')
                    // Název obchodu chceme mít předvyplněný firmou — psát ho
                    // celý ručně u každé příležitosti zdržuje.
                    ->mutateDataUsing(function (array $data): array {
                        $data['title'] = $data['title'] ?: $this->getOwnerRecord()->name;

                        return $data;
                    }),
            ])
            ->recordActions([EditAction::make()->label('')->iconButton()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Žádný obchod')
            ->emptyStateDescription('Až se rozjede konkrétní příležitost, založ obchod — objeví se v pipeline.');
    }
}
