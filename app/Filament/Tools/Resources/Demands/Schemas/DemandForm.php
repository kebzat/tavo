<?php

namespace App\Filament\Tools\Resources\Demands\Schemas;

use App\Enums\Crm\DemandSource;
use App\Enums\Crm\DemandStatus;
use App\Enums\Crm\Priority;
use App\Models\Crm\Company;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DemandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Poptávka')
                ->columns(2)
                ->schema([
                    TextInput::make('title')->label('Název')->required()->maxLength(255)->columnSpanFull(),

                    TextInput::make('url')
                        ->label('Odkaz')
                        ->url()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->columnSpanFull()
                        ->helperText('Podle odkazu se poznává, jestli ranní import poptávku zakládá, nebo aktualizuje.'),

                    Select::make('source')
                        ->label('Zdroj')
                        ->options(DemandSource::class)
                        ->default(DemandSource::Other)
                        ->native(false)
                        ->required(),

                    DatePicker::make('posted_at')->label('Zveřejněno'),

                    TextInput::make('budget_estimate')
                        ->label('Odhad rozpočtu')
                        ->maxLength(255)
                        ->helperText('Tak, jak ho uvádí portál.'),

                    Select::make('priority')
                        ->label('Priorita')
                        ->options(Priority::class)
                        ->default(Priority::B)
                        ->native(false)
                        ->required(),

                    Textarea::make('summary')->label('Shrnutí')->rows(4)->columnSpanFull(),
                ]),

            Section::make('Jak jsme ji vyřídili')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Stav')
                        ->options(DemandStatus::class)
                        ->default(DemandStatus::New)
                        ->native(false)
                        ->required(),

                    DateTimePicker::make('replied_at')->label('Reagováno')->seconds(false),

                    Select::make('company_id')
                        ->label('Navázaná firma')
                        ->options(fn (): array => Company::orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->native(false)
                        ->placeholder('Zatím žádná')
                        ->columnSpanFull(),

                    Textarea::make('notes')->label('Poznámky')->rows(3)->columnSpanFull(),
                ]),
        ]);
    }
}
