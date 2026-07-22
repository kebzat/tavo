<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Models\Lead;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Poptávka')
                ->description('Údaje přišly z webového formuláře — needitujte je, slouží jako záznam.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Jméno')->disabled(),
                    TextInput::make('company')->label('Firma')->disabled(),
                    TextInput::make('email')->label('E-mail')->disabled(),
                    TextInput::make('phone')->label('Telefon')->disabled(),
                    TextInput::make('topic')->label('O co jde')->disabled(),
                    TextInput::make('budget')->label('Rozpočet')->disabled(),
                    Textarea::make('message')->label('Zpráva')->rows(6)->disabled()->columnSpanFull(),
                ]),

            Section::make('Naše práce s poptávkou')->columns(2)->schema([
                Select::make('status')
                    ->label('Stav')
                    ->options(Lead::STATUSES)
                    ->default('new')
                    ->required(),
                Textarea::make('note')->label('Interní poznámka')->rows(4)->columnSpanFull(),
            ]),

            Section::make('Technické údaje')->collapsed()->columns(2)->schema([
                TextInput::make('source_url')->label('Odkud přišla')->disabled(),
                TextInput::make('ip')->label('IP adresa')->disabled(),
                Textarea::make('user_agent')->label('Prohlížeč')->rows(2)->disabled()->columnSpanFull(),
            ]),
        ]);
    }
}
