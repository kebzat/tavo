<?php

namespace App\Filament\Resources\ClientLogos\Schemas;

use App\Filament\Schemas\ImageUpload;
use App\Models\ClientLogo;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ClientLogoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Klient')
                ->helperText('Čtou ho hlasové čtečky a vyhledávače místo loga.')
                ->required(),
            Toggle::make('published')->label('Zobrazit na webu')->default(true),
            ImageUpload::media('logo')
                ->label('Logo')
                ->collection(ClientLogo::MEDIA_LOGO)
                ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/webp', 'image/jpeg'])
                ->helperText('Nejlíp SVG, jinak PNG s průhledným pozadím. Logo musí být čitelné na světlém podkladu, bílá loga pro tmavé hlavičky na webu zmizí.')
                ->required()
                ->columnSpanFull(),
        ]);
    }
}
