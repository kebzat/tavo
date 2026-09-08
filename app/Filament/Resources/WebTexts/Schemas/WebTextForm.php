<?php

namespace App\Filament\Resources\WebTexts\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WebTextForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('key')
                    ->label('Klíč')
                    ->disabled()
                    ->helperText('Určuje ho šablona, měnit se nedá.'),

                TextInput::make('group')
                    ->label('Skupina')
                    ->helperText('Jen pro řazení v tomhle výpisu.'),

                TextInput::make('note')
                    ->label('Kde se text zobrazuje')
                    ->disabled()
                    ->columnSpanFull(),

                Textarea::make('value')
                    ->label('Text')
                    ->rows(4)
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Zobrazí se na webu hned po uložení. Tlačítkem „Vrátit původní" se text vrátí do znění, které je v šabloně.'),
            ]),
        ]);
    }
}
