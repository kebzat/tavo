<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('name')
                    ->label('Jméno')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
            ]),

            Section::make('Heslo')
                ->description('Při úpravě účtu nechte prázdné, pokud heslo měnit nechcete.')
                ->schema([
                    TextInput::make('password')
                        ->label('Heslo')
                        ->password()
                        ->revealable()
                        ->minLength(10)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        // Prázdné pole se do ukládaných dat vůbec nedostane,
                        // takže úpravou jména se stávající heslo nesmaže.
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText('Alespoň 10 znaků. Model si heslo zahashuje sám.'),
                ]),

            Section::make('Oprávnění')->schema([
                Select::make('role')
                    ->label('Role')
                    ->options(UserRole::class)
                    ->default(UserRole::Editor)
                    ->required()
                    ->native(false)
                    // Vlastní roli nelze snížit — jinak by si šlo odebrat
                    // přístup ke správě uživatelů a zamknout se ven.
                    ->disabled(fn (?User $record): bool => $record?->getKey() === Auth::id())
                    ->helperText(fn (?User $record): string => $record?->getKey() === Auth::id()
                        ? 'Vlastní roli měnit nelze.'
                        : 'Správce vidí vše včetně nastavení a uživatelů. Redaktor jen obsah.'),
            ]),
        ]);
    }
}
