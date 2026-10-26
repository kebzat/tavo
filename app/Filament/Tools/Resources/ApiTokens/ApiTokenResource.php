<?php

namespace App\Filament\Tools\Resources\ApiTokens;

use App\Enums\ApiAbility;
use App\Filament\Tools\Resources\ApiTokens\Pages\ListApiTokens;
use App\Models\ApiToken;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tokeny agentů pro strojové rozhraní CRM (nastroje/api). Každý agent má
 * vlastní token jen s tím, co potřebuje. Uniklý token se tu jedním
 * kliknutím zruší a historie ukáže, co s ním kdo dělal.
 *
 * Token se ukáže jednou při založení, v databázi je jen jeho otisk.
 */
class ApiTokenResource extends Resource
{
    protected static ?string $model = ApiToken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $navigationLabel = 'Přístupy agentů';

    protected static ?string $modelLabel = 'přístup agenta';

    protected static ?string $pluralModelLabel = 'Přístupy agentů';

    protected static string|\UnitEnum|null $navigationGroup = 'Nastavení';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $breadcrumb = 'Přístupy agentů';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Agent')
                ->helperText('Kdo token používá a kde běží, třeba „/audit-eshopu – Tomův notebook“.')
                ->required()
                ->maxLength(255),
            CheckboxList::make('abilities')
                ->label('Smí')
                ->options(collect(ApiAbility::cases())->mapWithKeys(fn (ApiAbility $a): array => [$a->value => $a->getLabel()]))
                ->descriptions(collect(ApiAbility::cases())->mapWithKeys(fn (ApiAbility $a): array => [$a->value => $a->getDescription()]))
                ->helperText('Jen to, co agent opravdu potřebuje.')
                ->required(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Agent')->searchable()->weight('bold')
                    ->description(fn (ApiToken $record): ?string => $record->isRevoked() ? 'Zrušený '.$record->revoked_at->format('j. n. Y') : null),
                TextColumn::make('abilities')->label('Smí')->badge()
                    ->formatStateUsing(fn (string $state): string => ApiAbility::tryFrom($state)?->getLabel() ?? $state),
                TextColumn::make('last_used_at')->label('Naposledy')->since()->placeholder('nikdy')
                    ->description(fn (ApiToken $record): ?string => $record->last_used_ip)
                    ->sortable(),
                TextColumn::make('creator.name')->label('Založil')->placeholder('—')
                    ->description(fn (ApiToken $record): string => $record->created_at->format('j. n. Y')),
            ])
            ->recordClasses(fn (ApiToken $record): ?string => $record->isRevoked() ? 'opacity-50' : null)
            ->recordActions([
                Action::make('calls')
                    ->label('')
                    ->tooltip('Historie volání')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->modalHeading(fn (ApiToken $record): string => 'Historie volání – '.$record->name)
                    ->modalContent(fn (ApiToken $record) => view('filament.tools.api-token-calls', [
                        'calls' => $record->calls()->latest('called_at')->limit(100)->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Zavřít'),
                EditAction::make()->label('')->tooltip('Upravit')->iconButton()
                    ->visible(fn (ApiToken $record): bool => ! $record->isRevoked()),
                Action::make('revoke')
                    ->label('')
                    ->tooltip('Zrušit token')
                    ->iconButton()
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (ApiToken $record): bool => ! $record->isRevoked())
                    ->requiresConfirmation()
                    ->modalHeading('Zrušit token?')
                    ->modalDescription('Agent, který ho používá, okamžitě přestane mít přístup. Vrátit to nejde, jen založit nový token.')
                    ->modalSubmitActionLabel('Zrušit token')
                    ->action(function (ApiToken $record): void {
                        $record->revoke();

                        Notification::make()->title('Token je zrušený')->success()->send();
                    }),
            ])
            ->emptyStateHeading('Žádní agenti')
            ->emptyStateDescription('Každý agent, který volá nastroje/api, má mít vlastní token jen s tím, co potřebuje.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiTokens::route('/'),
        ];
    }
}
