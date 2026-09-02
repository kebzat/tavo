<?php

namespace App\Filament\Tools\Resources\Deals\Schemas;

use App\Enums\Crm\DealPackage;
use App\Enums\Crm\DealStage;
use App\Models\Crm\Company;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Obchod')
                ->columns(2)
                ->schema([
                    Select::make('company_id')
                        ->label('Firma')
                        ->options(fn (): array => Company::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->columnSpanFull(),

                    TextInput::make('title')->label('Název')->required()->maxLength(255)->columnSpanFull(),

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
                ]),

            Section::make('Postup')
                ->columns(2)
                ->schema([
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
                        ->columnSpanFull()
                        ->visible(fn ($get): bool => self::stageValue($get('stage')) === DealStage::Lost->value),

                    Textarea::make('notes')->label('Poznámky')->rows(4)->columnSpanFull(),
                ]),
        ]);
    }

    /** Formulář drží fázi jednou jako enum (načtení), jindy jako string (výběr). */
    private static function stageValue(mixed $stage): ?string
    {
        return $stage instanceof DealStage ? $stage->value : (is_string($stage) ? $stage : null);
    }
}
