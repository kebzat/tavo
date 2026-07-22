<?php

namespace App\Filament\Resources\Founders\Schemas;

use App\Models\Founder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FounderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')->label('Jméno')->required(),
                TextInput::make('role_label')->label('Role')->helperText('Např. „Marketing & růst"'),
                TextInput::make('external_url')->label('Vlastní web')->url(),
                TextInput::make('order_column')->label('Pořadí')->numeric()->default(0),
                Textarea::make('bio')->label('Popis')->rows(3)->columnSpanFull(),

                Repeater::make('tags')
                    ->label('Štítky')
                    ->simple(TextInput::make('text')->required())
                    ->columnSpanFull()
                    ->defaultItems(0),
            ]),

            Section::make('Fotka')
                ->description('Použije se v sekci „Lidé" na homepage. Stačí jedna společná fotka u prvního zakladatele.')
                ->schema([
                    SpatieMediaLibraryFileUpload::make('photo')
                        ->label('Fotka')
                        ->collection(Founder::MEDIA_PHOTO)
                        ->image()
                        ->imageEditor(),
                ]),
        ]);
    }
}
