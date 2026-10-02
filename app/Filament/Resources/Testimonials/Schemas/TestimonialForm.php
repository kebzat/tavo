<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Models\Testimonial;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Textarea::make('text')
                ->label('Text recenze')
                ->helperText('Doslova, jak ji klient napsal. Uvozovky doplní web sám.')
                ->rows(5)
                ->required()
                ->columnSpanFull(),
            TextInput::make('author')
                ->label('Kdo ji napsal')
                ->helperText('Jméno člověka nebo název firmy.')
                ->required(),
            TextInput::make('role')
                ->label('Role nebo obor')
                ->helperText('Např. „Zakladatel, svetcejlonu.cz" nebo „zubní laboratoř, Chrudim".'),
            Select::make('person')
                ->label('Komu')
                ->options(Testimonial::PEOPLE)
                ->placeholder('Oběma / společná zakázka'),
            TextInput::make('source_url')
                ->label('Web klienta')
                ->url()
                ->helperText('Jméno pod citací povede sem, ať jde ověřit, že klient existuje. Prázdné = bez odkazu.'),
            Toggle::make('published')->label('Zobrazit na webu')->default(true),
        ]);
    }
}
