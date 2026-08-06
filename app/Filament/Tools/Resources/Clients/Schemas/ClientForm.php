<?php

namespace App\Filament\Tools\Resources\Clients\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Klient')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($set, $state, $operation) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Zkratka')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Interní označení, nikde se nezveřejňuje.'),

                    TextInput::make('website_url')
                        ->label('Web')
                        ->url()
                        ->placeholder('https://'),

                    Toggle::make('is_archived')
                        ->label('Archivovaný')
                        ->helperText('Schová klienta z běžného výpisu.'),
                ]),

            Section::make('Kontakt')
                ->columns(2)
                ->schema([
                    TextInput::make('contact_name')->label('Kontaktní osoba'),
                    TextInput::make('contact_email')->label('E-mail')->email(),
                    Textarea::make('note')
                        ->label('Interní poznámka')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Vidíme jen my, na sdílené stránce se nezobrazuje.'),
                ]),
        ]);
    }
}
