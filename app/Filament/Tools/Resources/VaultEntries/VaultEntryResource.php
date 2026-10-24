<?php

namespace App\Filament\Tools\Resources\VaultEntries;

use App\Enums\VaultType;
use App\Filament\Tools\Resources\VaultEntries\Pages\CreateVaultEntry;
use App\Filament\Tools\Resources\VaultEntries\Pages\EditVaultEntry;
use App\Filament\Tools\Resources\VaultEntries\Pages\ListVaultEntries;
use App\Filament\Tools\Resources\VaultEntries\RelationManagers\LogsRelationManager;
use App\Filament\Tools\Resources\VaultEntries\RelationManagers\UploadsRelationManager;
use App\Models\Client;
use App\Models\VaultEntry;
use App\Models\VaultUpload;
use App\Support\Vault\FtpConnector;
use App\Support\Vault\LoginBroker;
use App\Support\Vault\VaultException;
use App\Support\Vault\VaultLock;
use App\Support\Vault\WordPressApi;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Trezor přístupů: hesla a klíče klientů na jednom místě, zašifrované.
 * Lidé je po zadání svého hesla zobrazí a zkopírují, agent je nedostane
 * nikdy, jen podle „Co smí agent“ nechá web pracovat za něj (FTP,
 * WordPress, přihlášení do administrace). Viz docs/TREZOR.md.
 */
class VaultEntryResource extends Resource
{
    protected static ?string $model = VaultEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static ?string $navigationLabel = 'Trezor';

    protected static ?string $modelLabel = 'přístup';

    protected static ?string $pluralModelLabel = 'Trezor přístupů';

    protected static ?int $navigationSort = 90;

    protected static ?string $slug = 'trezor';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationBadge(): ?string
    {
        $pending = VaultUpload::query()->where('status', VaultUpload::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Soubory od agenta čekají na schválení';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        $type = fn (Get $get): ?VaultType => $get('type') instanceof VaultType ? $get('type') : VaultType::tryFrom((string) $get('type'));
        $editing = fn (?VaultEntry $record): bool => $record !== null;
        $stored = fn (string $field) => fn (?VaultEntry $record): ?string => $record && filled($record->{$field}) ? 'Uloženo. Vyplň, jen když ho měníš.' : null;

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('Druh')
                        ->options(VaultType::class)
                        ->default(VaultType::Ftp)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, $state): void {
                            $type = $state instanceof VaultType ? $state : VaultType::tryFrom((string) $state);
                            $set('agent_access', match ($type) {
                                VaultType::Ftp => 'approve',
                                VaultType::ApiKey, VaultType::Other, null => 'none',
                                default => 'read',
                            });
                        }),
                    Select::make('client_id')
                        ->label('Klient')
                        ->options(fn (): array => Client::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->placeholder('TAVEO (bez klienta)')
                        ->searchable(),
                    TextInput::make('name')
                        ->label('Název')
                        ->placeholder(fn (Get $get): string => match ($type($get)) {
                            VaultType::Ftp => 'FTP webu na zcomu',
                            VaultType::WordPress => 'Administrace WordPressu',
                            VaultType::Shoptet => 'Administrace Shoptetu',
                            VaultType::ApiKey => 'Klíč k API Upgates',
                            default => 'K čemu přístup je',
                        })
                        ->required()
                        ->maxLength(150)
                        ->columnSpanFull(),
                    TextInput::make('url')
                        ->label(fn (Get $get): string => $type($get)?->urlLabel() ?? 'Adresa')
                        ->placeholder(fn (Get $get): string => match ($type($get)) {
                            VaultType::Ftp => 'ftp.pizzadigomma.eu',
                            VaultType::Shoptet, VaultType::Upgates => 'https://www.eshop.cz',
                            VaultType::ApiKey, VaultType::Other => '',
                            default => 'https://www.web.cz',
                        })
                        ->required(fn (Get $get): bool => $type($get) === VaultType::Ftp || $type($get)?->isLogin())
                        ->maxLength(255),
                    TextInput::make('username')
                        ->label(fn (Get $get): string => $type($get)?->usernameLabel() ?? 'Uživatel')
                        ->autocomplete('off')
                        ->required(fn (Get $get): bool => $type($get) === VaultType::Ftp || $type($get)?->isLogin())
                        ->maxLength(255),
                    TextInput::make('secret')
                        ->label(fn (Get $get): string => $type($get)?->secretLabel() ?? 'Heslo')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->required(fn (?VaultEntry $record, Get $get): bool => $record === null && $type($get) !== VaultType::WordPress)
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText($stored('secret'))
                        ->maxLength(2000),
                    TextInput::make('app_password')
                        ->label('Aplikační heslo WordPressu')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->visible(fn (Get $get): bool => $type($get) === VaultType::WordPress)
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->helperText(fn (?VaultEntry $record): string => $record && filled($record->app_password)
                            ? 'Uloženo. Vyplň, jen když ho měníš.'
                            : 'Pro agenta: ve WordPressu Uživatelé → profil → Aplikační hesla → Přidat. Projde i přes ochranu „nejsem robot“ a jde kdykoli zrušit.')
                        ->maxLength(200),
                    TextInput::make('settings.port')
                        ->label('Port')
                        ->numeric()
                        ->default(21)
                        ->visible(fn (Get $get): bool => $type($get) === VaultType::Ftp),
                    Toggle::make('settings.tls')
                        ->label('Šifrované spojení (FTPS)')
                        ->default(true)
                        ->helperText('Vypni, jen když server šifrování neumí.')
                        ->inline(false)
                        ->visible(fn (Get $get): bool => $type($get) === VaultType::Ftp),
                    TextInput::make('settings.root')
                        ->label('Složka webu')
                        ->placeholder('/www/domains/pizzadigomma.eu')
                        ->default('/')
                        ->helperText('Agent se dostane jen do ní a do jejích podsložek.')
                        ->visible(fn (Get $get): bool => $type($get) === VaultType::Ftp),
                    TextInput::make('settings.login_url')
                        ->label('Adresa přihlášení')
                        ->placeholder(fn (Get $get): string => match ($type($get)) {
                            VaultType::WordPress => 'nevyplněno = /wp-login.php',
                            VaultType::Shoptet => 'nevyplněno = /admin/login/',
                            VaultType::Upgates => 'nevyplněno = /admin/ (ověř, kde se přihlašuješ)',
                            default => 'https://… stránka s přihlašovacím formulářem',
                        })
                        ->url()
                        ->visible(fn (Get $get): bool => (bool) $type($get)?->isLogin()),
                    TextInput::make('totp_secret')
                        ->label('Tajný klíč 2FA')
                        ->password()
                        ->revealable()
                        ->autocomplete('off')
                        ->helperText(fn (?VaultEntry $record): string => $record && filled($record->totp_secret)
                            ? 'Uloženo, trezor ukáže aktuální kód. Vyplň, jen když ho měníš.'
                            : 'Jen když účet chce kód z aplikace: text pod QR kódem při zapínání 2FA. Trezor pak kód ukáže a agenta přihlásí i s ním.')
                        ->visible(fn (Get $get): bool => (bool) $type($get)?->isLogin() || $type($get) === VaultType::Other)
                        ->dehydrated(fn ($state): bool => filled($state))
                        ->maxLength(200),
                    Select::make('agent_access')
                        ->label('Co smí agent')
                        ->options(fn (Get $get): array => $type($get)?->agentAccessOptions() ?? ['none' => 'Nic'])
                        ->default('approve')
                        ->selectablePlaceholder(false)
                        ->required()
                        ->helperText(fn (Get $get): string => match (true) {
                            $type($get)?->isLogin() => 'Heslo agent nikdy neuvidí, trezor ho přihlásí sám. Přihlášený ale smí v administraci, co smí ten účet: dej mu vlastní účet s menšími právy, když to jde.',
                            $type($get) === VaultType::Ftp => 'Heslo agent nikdy neuvidí. Soubory za něj stahuje a nahrává web, před nahráním je záloha a jde to vrátit.',
                            default => 'Klíče a jiná hesla jsou zatím jen pro lidi.',
                        })
                        ->columnSpanFull(),
                    Textarea::make('notes')
                        ->label('Poznámka')
                        ->placeholder('Kdo přístup dal, na co je, co se nesmí…')
                        ->rows(3)
                        ->maxLength(5000)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('client')->withCount(['uploads as pending_uploads_count' => fn ($query) => $query->where('status', VaultUpload::STATUS_PENDING)]))
            ->columns([
                TextColumn::make('name')
                    ->label('Přístup')
                    ->weight('bold')
                    ->description(fn (VaultEntry $record): string => ($record->client?->name ?? 'TAVEO').' · '.$record->type->getLabel())
                    ->icon(fn (VaultEntry $record): Heroicon => $record->type->getIcon())
                    ->iconColor('gray')
                    ->searchable(),
                TextColumn::make('url')
                    ->label('Adresa')
                    ->description(fn (VaultEntry $record): ?string => $record->username)
                    ->limit(32)
                    ->visibleFrom('lg')
                    ->color('gray')
                    ->searchable(['url', 'username']),
                TextColumn::make('agent_access')
                    ->label('Agent')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'read' => 'Číst / přihlásit',
                        'approve' => 'Nahrát po schválení',
                        'write' => 'Nahrát hned',
                        default => 'Jen lidé',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'read' => 'info',
                        'approve' => 'warning',
                        'write' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (VaultEntry $record): string => $record->agentAccessLabel())
                    ->description(fn (VaultEntry $record): ?string => $record->pending_uploads_count > 0 ? $record->pending_uploads_count.' nahrání ke schválení' : null),
                TextColumn::make('last_used_at')->label('Použito')->since()->placeholder('zatím ne')->color('gray')->sortable()->visibleFrom('2xl'),
            ])
            ->filters([
                SelectFilter::make('client')->label('Klient')->relationship('client', 'name')->searchable()->preload(),
                SelectFilter::make('type')->label('Druh')->options(VaultType::class),
            ])
            ->recordActions([
                self::revealAction(),
                EditAction::make()->iconButton()->tooltip('Upravit'),
            ])
            ->emptyStateHeading('Trezor je prázdný')
            ->emptyStateDescription('Přidej první přístup. Hesla se uloží zašifrovaná a agent je nikdy neuvidí.');
    }

    /**
     * Zobrazit heslo: zamčený trezor nejdřív chce heslo do nástrojů (10 minut
     * platí), pak se v tomtéž okně ukáže, co je uložené, s tlačítkem zkopírovat.
     */
    public static function revealAction(): Action
    {
        return Action::make('reveal')
            ->label('Zobrazit')
            ->tooltip('Zobrazit heslo')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (VaultEntry $record): string => $record->name)
            ->modalDescription(fn (): ?string => VaultLock::isUnlocked()
                ? 'Odemčeno do '.VaultLock::until()?->timezone('Europe/Prague')->format('G:i').'. Zobrazení se zapisuje do historie.'
                : 'Pro zobrazení hesla zadej své heslo do nástrojů. Trezor pak zůstane '.VaultLock::MINUTES.' minut odemčený.')
            ->modalWidth('lg')
            ->mountUsing(function (VaultEntry $record): void {
                if (VaultLock::isUnlocked()) {
                    $record->record(VaultLock::actor(), 'revealed');
                }
            })
            // Obě podoby jsou ve formuláři pořád, mění se jen viditelnost: obsah
            // otevřeného okna se po odemčení jinak nepřekreslí.
            ->schema([
                TextInput::make('password')
                    ->label('Tvoje heslo do nástrojů')
                    ->password()
                    ->required(fn (): bool => ! VaultLock::isUnlocked())
                    ->currentPassword()
                    ->validationMessages(['current_password' => 'Heslo nesedí.'])
                    ->autocomplete('current-password')
                    ->visible(fn (): bool => ! VaultLock::isUnlocked()),
                ...self::secrets(),
            ])
            ->modalSubmitActionLabel('Odemknout')
            ->modalSubmitAction(fn (Action $action) => VaultLock::isUnlocked() ? false : $action)
            ->modalCancelActionLabel(fn (): string => VaultLock::isUnlocked() ? 'Zavřít' : 'Zrušit')
            ->action(function (array $data, VaultEntry $record, Action $action): void {
                if (! VaultLock::unlock((string) ($data['password'] ?? ''))) {
                    return;
                }

                $record->record(VaultLock::actor(), 'unlocked');
                $record->record(VaultLock::actor(), 'revealed');
                $action->halt();
            });
    }

    /** @return list<TextEntry> */
    private static function secrets(): array
    {
        $entry = fn (string $name, string|Closure $label, Closure $value): TextEntry => TextEntry::make($name)
            ->label($label)
            ->state($value)
            ->fontFamily('mono')
            ->copyable()
            ->copyMessage('Zkopírováno')
            ->extraAttributes(['class' => 'break-all'])
            ->visible(fn (VaultEntry $record): bool => VaultLock::isUnlocked() && filled($value($record)));

        return [
            $entry('show_url', fn (VaultEntry $record): string => $record->type->urlLabel(), fn (VaultEntry $record): ?string => $record->type->isLogin() ? ($record->loginUrl() ?? $record->url) : $record->url),
            $entry('show_username', fn (VaultEntry $record): string => $record->type->usernameLabel(), fn (VaultEntry $record): ?string => $record->username),
            $entry('show_secret', fn (VaultEntry $record): string => $record->type->secretLabel(), fn (VaultEntry $record): ?string => $record->secret),
            $entry('show_app_password', 'Aplikační heslo', fn (VaultEntry $record): ?string => $record->app_password),
            $entry('show_totp', 'Kód 2FA teď (platí do půl minuty)', fn (VaultEntry $record): ?string => $record->totpCode()),
            $entry('show_root', 'Složka webu', fn (VaultEntry $record): ?string => $record->type === VaultType::Ftp ? $record->setting('root', '/') : null),
            TextEntry::make('show_notes')
                ->label('Poznámka')
                ->state(fn (VaultEntry $record): ?string => $record->notes)
                ->visible(fn (VaultEntry $record): bool => VaultLock::isUnlocked() && filled($record->notes)),
        ];
    }

    /** Vyzkoušet: spojí se a ověří přihlášení, nic nemění a heslo neukazuje. */
    public static function testAction(): Action
    {
        return Action::make('test')
            ->label('Vyzkoušet')
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->visible(fn (VaultEntry $record): bool => $record->type === VaultType::Ftp || $record->type->isLogin())
            ->action(function (VaultEntry $record): void {
                try {
                    $message = match (true) {
                        $record->type === VaultType::Ftp => self::testFtp($record),
                        $record->type === VaultType::WordPress && filled($record->app_password) => self::testWordPress($record),
                        default => self::testLogin($record),
                    };
                } catch (VaultException $e) {
                    $record->record(VaultLock::actor(), 'tested', 'Chyba: '.$e->getMessage());
                    Notification::make()->danger()->title('Nefunguje')->body($e->getMessage())->persistent()->send();

                    return;
                }

                $record->record(VaultLock::actor(), 'tested', $message);
                Notification::make()->success()->title('Funguje')->body($message)->send();
            });
    }

    private static function testFtp(VaultEntry $record): string
    {
        $ftp = app(FtpConnector::class)->open($record);

        try {
            $items = $ftp->list('');
        } finally {
            $ftp->close();
        }

        return 'Přihlášení na FTP prošlo. '.($items === []
            ? 'Složka webu je prázdná, zkontroluj, jestli je nastavená správně.'
            : 'Ve složce webu: '.collect($items)->take(5)->pluck('name')->implode(', ').(count($items) > 5 ? '…' : '.'));
    }

    private static function testWordPress(VaultEntry $record): string
    {
        $response = app(WordPressApi::class)->call($record, 'GET', '/wp/v2/users/me', ['context' => 'edit']);

        if (! $response->successful()) {
            throw new VaultException('WordPress aplikační heslo nepřijal (HTTP '.$response->status().'). '.($response->json('message') ?? ''));
        }

        $roles = implode(', ', (array) $response->json('roles'));

        return 'Aplikační heslo funguje, účet '.$response->json('name').($roles ? ' (role '.$roles.')' : '').'.'
            .(filled($record->secret) ? ' Přihlášení do administrace se zkouší zvlášť, až ho agent bude potřebovat.' : '');
    }

    private static function testLogin(VaultEntry $record): string
    {
        $cookies = app(LoginBroker::class)->login($record);

        return 'Přihlášení do administrace prošlo ('.count($cookies).' cookies pro prohlížeč agenta).';
    }

    public static function getRelations(): array
    {
        return [
            UploadsRelationManager::class,
            LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVaultEntries::route('/'),
            'create' => CreateVaultEntry::route('/create'),
            'edit' => EditVaultEntry::route('/{record}/edit'),
        ];
    }
}
