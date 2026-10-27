<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Enums\WorkArea;
use App\Models\Client;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->live()
                    // Vlastní roli nelze snížit — jinak by si šlo odebrat
                    // přístup ke správě uživatelů a zamknout se ven.
                    ->disabled(fn (?User $record): bool => $record?->getKey() === Auth::id())
                    ->helperText(fn (?User $record): string => $record?->getKey() === Auth::id()
                        ? 'Vlastní roli měnit nelze.'
                        : 'Správce vidí vše včetně nastavení a uživatelů. Redaktor jen obsah. Klient jen svůj účet na /muj-ucet.'),

                // Klient vidí jen data svého klienta, bez něj se do účtu nedostane.
                Select::make('client_id')
                    ->label('Klient')
                    ->options(fn (): array => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->visible(fn (Get $get): bool => self::isClientRole($get('role')))
                    ->required(fn (Get $get): bool => self::isClientRole($get('role')))
                    ->helperText('Účet uvidí jen automatizace tohoto klienta.'),

                Toggle::make('is_reviewer')
                    ->label('Kontroluje výstupy pro klienty')
                    ->helperText('V nástrojích dostane u auditů, nabídek, checklistů a reportů vlastní sloupec „zkontrolováno“.'),

                Select::make('billing_area')
                    ->label('Fakturuje oblast')
                    ->options(WorkArea::class)
                    ->native(false)
                    ->placeholder('Nic')
                    ->helperText('Fakturace v nástrojích mu otevře tuhle oblast a počítá mu nevyfakturované. Až se bude fakturovat společně, nechte u všech prázdné.'),
            ]),
        ]);
    }

    private static function isClientRole(mixed $role): bool
    {
        return ($role instanceof UserRole ? $role : UserRole::tryFrom((string) $role)) === UserRole::Client;
    }
}
