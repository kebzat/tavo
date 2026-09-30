<?php

namespace App\Filament\Tools\Resources\Proposals\Schemas;

use App\Filament\Schemas\ImageUpload;
use App\Models\Proposal;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * Každá záložka je jedna sekce stránky, v pořadí, v jakém je čte klient.
 * Prázdná sekce se na stránce nezobrazí.
 */
class ProposalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->persistTabInQueryString()->tabs([
                self::basics(),
                self::timeline(),
                self::findings(),
                self::recommendations(),
                self::steps(),
                self::examples(),
                self::principles(),
                self::sharing(),
            ]),
        ]);
    }

    private static function basics(): Tab
    {
        return Tab::make('Úvod')->schema([
            Section::make()->columns(2)->schema([
                TextInput::make('company_name')
                    ->label('Firma')
                    ->required()
                    ->placeholder('IQ Hračky')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($set, $state, $operation): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),

                TextInput::make('slug')
                    ->label('Adresa')
                    ->prefix('/potencialni-spoluprace/')
                    ->required()
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->helperText('Vyplní se z názvu firmy. Po odeslání odkazu klientovi už neměňte.'),

                TextInput::make('title')
                    ->label('Nadpis')
                    ->required()
                    ->placeholder('Co bychom s IQ Hračkami udělali do Vánoc')
                    ->columnSpanFull(),

                Textarea::make('intro')
                    ->label('Úvod')
                    ->rows(3)
                    ->columnSpanFull()
                    ->helperText('Pár vět pod nadpisem: z čeho vycházíme a co na stránce najdou.'),

                Select::make('client_id')
                    ->label('Klient')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Nepovinné. Propojí stránku se sdíleným auditem a otevření zapíše do CRM.'),

                DatePicker::make('prepared_at')
                    ->label('Připraveno')
                    ->native(false)
                    ->displayFormat('j. n. Y')
                    ->default(now()),
            ]),

            Section::make('Čísla pod úvodem')
                ->description('Až čtyři dlaždice. Jen čísla, která jde doložit. Bez nich se řádek nezobrazí.')
                ->collapsible()
                ->schema([
                    Repeater::make('highlights')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('value')->label('Číslo')->required()->placeholder('5 / 5'),
                            TextInput::make('label')->label('Popisek')->placeholder('hodnocení obchodu na Heurece'),
                        ])
                        ->columns(2)
                        ->maxItems(4)
                        ->defaultItems(0)
                        ->addActionLabel('Přidat číslo')
                        ->reorderable(),
                ]),
        ]);
    }

    private static function timeline(): Tab
    {
        return Tab::make('Kdysi a dnes')->schema([
            Textarea::make('timeline_intro')
                ->label('Perex sekce')
                ->rows(2),

            Repeater::make('timeline')
                ->label('Podoby webu')
                ->helperText('Vedle sebe, zleva doprava. Poslední se zvýrazní, to je návrh s námi. Stačí horní část stránky, na kliknutí se otevře celý obrázek.')
                ->schema([
                    TextInput::make('label')->label('Štítek')->placeholder('2016'),
                    TextInput::make('title')->label('Nadpis')->required()->placeholder('Kdysi'),
                    Textarea::make('body')->label('Text')->rows(2)->columnSpanFull(),
                    ImageUpload::file('image')
                        ->label('Screenshot')
                        ->directory('spoluprace')
                        ->columnSpanFull(),
                    TextInput::make('image_alt')->label('Popisek obrázku (alt)')->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => trim(($state['label'] ?? '').' '.($state['title'] ?? '')) ?: null)
                ->maxItems(3)
                ->defaultItems(0)
                ->addActionLabel('Přidat podobu webu')
                ->reorderable(),
        ]);
    }

    private static function findings(): Tab
    {
        return Tab::make('Co jsme objevili')->schema([
            Textarea::make('findings_intro')
                ->label('Perex sekce')
                ->rows(2),

            Repeater::make('findings')
                ->label('Zjištění')
                ->schema([
                    Select::make('tone')
                        ->label('Štítek')
                        ->options(collect(Proposal::FINDING_TONES)->map(fn (array $tone): string => $tone['label'])->all())
                        ->placeholder('Bez štítku'),
                    TextInput::make('title')->label('Nadpis')->required(),
                    Textarea::make('body')->label('Text')->rows(3)->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->defaultItems(0)
                ->addActionLabel('Přidat zjištění')
                ->reorderable(),
        ]);
    }

    private static function recommendations(): Tab
    {
        return Tab::make('Co doporučujeme')->schema([
            Textarea::make('recommendations_intro')
                ->label('Perex sekce')
                ->rows(2),

            Repeater::make('recommendations')
                ->label('Doporučení')
                ->schema([
                    TextInput::make('title')->label('Nadpis')->required(),
                    TextInput::make('who')
                        ->label('Kdo to udělá')
                        ->datalist(['Pavel', 'Tom', 'Pavel a Tom', 'Pavel s partnerem na Google Ads', 'Pavel a externí tvůrkyně']),
                    Textarea::make('body')->label('Text')->rows(3)->columnSpanFull(),
                ])
                ->columns(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->defaultItems(0)
                ->addActionLabel('Přidat doporučení')
                ->reorderable(),
        ]);
    }

    private static function steps(): Tab
    {
        return Tab::make('Akční kroky')->schema([
            Textarea::make('steps_intro')
                ->label('Perex sekce')
                ->rows(2),

            Repeater::make('steps')
                ->label('Kroky')
                ->schema([
                    TextInput::make('when')->label('Kdy')->placeholder('1.–2. týden'),
                    TextInput::make('title')->label('Nadpis')->required(),
                    Textarea::make('body')->label('Text')->rows(2)->columnSpanFull(),
                    Toggle::make('later')
                        ->label('Až potom')
                        ->helperText('Krok se ukáže pod „Potom postupně“, odděleně od prvních týdnů.'),
                ])
                ->columns(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => trim(($state['when'] ?? '').' '.($state['title'] ?? '')) ?: null)
                ->defaultItems(0)
                ->addActionLabel('Přidat krok')
                ->reorderable(),
        ]);
    }

    private static function examples(): Tab
    {
        return Tab::make('Ukázky')->schema([
            Repeater::make('examples')
                ->label('Ukázky mezi sekcemi')
                ->helperText('Co umíme: návrh webu z AI, fotky nebo videa od konkurence, ukázka reklamy. Každá ukázka je tmavý pruh mezi sekcemi.')
                ->schema([
                    Select::make('placement')
                        ->label('Kde na stránce')
                        ->options(Proposal::EXAMPLE_PLACEMENTS)
                        ->default('after_findings')
                        ->selectablePlaceholder(false),
                    TextInput::make('kind')->label('Štítek')->placeholder('Web s pomocí AI'),
                    TextInput::make('title')->label('Nadpis')->required()->columnSpanFull(),
                    Textarea::make('body')->label('Text')->rows(3)->columnSpanFull(),

                    ImageUpload::file('image')
                        ->label('Obrázek')
                        ->directory('spoluprace')
                        ->columnSpanFull()
                        ->helperText('Screenshot návrhu, fotka nebo náhled videa. Bez obrázku zůstane jen text.'),
                    TextInput::make('image_alt')->label('Popisek obrázku (alt)'),
                    Toggle::make('scroll')
                        ->label('Dlouhý screenshot')
                        ->helperText('Obrázek se ukáže v okně prohlížeče, ve kterém se dá scrollovat.'),

                    TextInput::make('link_url')->label('Odkaz')->url()->placeholder('https://www.instagram.com/reel/…'),
                    TextInput::make('link_label')->label('Text odkazu')->placeholder('Otevřít ukázku'),
                ])
                ->columns(2)
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->defaultItems(0)
                ->addActionLabel('Přidat ukázku')
                ->reorderable(),
        ]);
    }

    private static function principles(): Tab
    {
        return Tab::make('Jak přemýšlíme')->schema([
            Repeater::make('principles')
                ->label('Zásady')
                ->helperText('Tmavá sekce před závěrem. Jak k spolupráci přistupujeme a co pro nás má přednost.')
                ->schema([
                    TextInput::make('title')->label('Nadpis')->required(),
                    Textarea::make('body')->label('Text')->rows(2),
                ])
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->maxItems(4)
                ->defaultItems(0)
                ->addActionLabel('Přidat zásadu')
                ->reorderable(),
        ]);
    }

    private static function sharing(): Tab
    {
        return Tab::make('Sdílení')->schema([
            Section::make()
                ->description('Odkaz je veřejný a nechráněný heslem. Kdo zná adresu, stránku si přečte. Do vyhledávačů se nedostane.')
                ->schema([
                    Toggle::make('is_public')
                        ->label('Zpřístupnit přes odkaz')
                        ->helperText('Dokud je vypnuté, stránku vidíte jen vy přihlášení (tlačítko Náhled nahoře).')
                        ->default(false),

                    TextEntry::make('views')
                        ->label('Otevřeno')
                        ->visible(fn ($operation): bool => $operation === 'edit')
                        ->state(fn (?Proposal $record): string => $record?->viewSummary('Klient stránku zatím neotevřel.') ?? ''),

                    TextInput::make('share_url')
                        ->label('Odkaz pro klienta')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn ($operation): bool => $operation === 'edit')
                        ->formatStateUsing(fn (?Proposal $record): ?string => $record?->publicUrl())
                        ->placeholder('Zapněte sdílení a uložte.'),
                ]),
        ]);
    }
}
