<?php

namespace App\Filament\Pages\Settings;

use App\Settings\ContactSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageContact extends SettingsPage
{
    protected static string $settings = ContactSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = 'Kontakt';

    protected static ?string $title = 'Kontaktní údaje';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 20;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kontakt')
                ->description('Tyto údaje se propíšou do navigace, patičky i všech CTA sekcí. Nikde je needitujte zvlášť.')
                ->columns(2)
                ->schema([
                    TextInput::make('email')->label('E-mail')->email()->required(),
                    TextInput::make('phone')->label('Telefon')->required(),
                    TextInput::make('company_name')->label('Název firmy'),
                    TextInput::make('ico')->label('IČO'),
                    TextInput::make('dic')->label('DIČ'),
                    Textarea::make('address')->label('Adresa')->rows(2),
                ]),

            Section::make('Sociální sítě')->schema([
                Repeater::make('socials')
                    ->label('Profily')
                    ->addActionLabel('Přidat profil')
                    ->schema([
                        TextInput::make('label')->label('Název')->required(),
                        TextInput::make('url')->label('Odkaz')->url()->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(0),
            ]),

            Section::make('Poptávky')
                ->description('Kam se posílá e-mail, když někdo odešle formulář na webu.')
                ->schema([
                    Repeater::make('lead_recipients')
                        ->label('Příjemci notifikací')
                        ->addActionLabel('Přidat příjemce')
                        ->simple(TextInput::make('email')->email()->required())
                        ->defaultItems(1),
                ]),
        ]);
    }
}
