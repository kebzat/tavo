<?php

namespace App\Filament\Resources\ProcessSteps\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProcessStepForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->label('Číslo')->maxLength(8)->required(),
            TextInput::make('title')->label('Název kroku')->required(),
            Textarea::make('text')->label('Popis')->rows(3)->columnSpanFull(),
            TextInput::make('order_column')->label('Pořadí')->numeric()->default(0),
            Toggle::make('highlight')
                ->label('Zvýraznit')
                ->helperText('Horní linka kroku bude cihlová místo černé.'),
        ]);
    }
}
