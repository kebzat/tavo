<?php

namespace App\Filament\Resources\Founders\Schemas;

use App\Filament\Schemas\ImageUpload;
use App\Models\Founder;
use Filament\Forms\Components\Repeater;
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
                TextInput::make('phone')
                    ->label('Telefon')
                    ->tel()
                    ->helperText('Nabídne se na webu jako číslo pro spěchající. Prázdné pole číslo nikde nezobrazí.'),
                TextInput::make('external_url')->label('Vlastní web')->url(),
                TextInput::make('order_column')->label('Pořadí')->numeric()->default(0),
                Textarea::make('bio')->label('Popis')->rows(3)->columnSpanFull(),

                Repeater::make('tags')
                    ->label('Štítky')
                    ->addActionLabel('Přidat štítek')
                    ->simple(TextInput::make('text')->required())
                    ->columnSpanFull()
                    ->defaultItems(0),
            ]),

            Section::make('Fotky')
                ->columns(2)
                ->schema([
                    ImageUpload::media('portrait')
                        ->label('Profilová fotka')
                        ->helperText('Kroužek v úvodu homepage. Ořízne se na čtverec podle středu, hlava by tedy měla být uprostřed. Doladit jde tlačítkem úprav.')
                        ->collection(Founder::MEDIA_PORTRAIT)
                        ->imageEditor()
                        ->imageEditorAspectRatios(['1:1']),
                    ImageUpload::media('photo')
                        ->label('Společná fotka')
                        ->helperText('Sekce „Lidé" na homepage. Stačí jedna u prvního zakladatele.')
                        ->collection(Founder::MEDIA_PHOTO)
                        ->imageEditor(),
                ]),
        ]);
    }
}
