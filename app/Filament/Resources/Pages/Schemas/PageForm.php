<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;

class PageForm
{
    /** Světlá / tmavá varianta sekce — dvojice, která se opakuje ve víc blocích. */
    private const TONES = [
        'cream' => 'Světlá',
        'ink' => 'Tmavá',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')
                    ->label('Název stránky')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($set, $state, $operation) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),

                TextInput::make('slug')
                    ->label('URL adresa')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Např. „cookies" → /cookies'),

                Toggle::make('published')->label('Zveřejněno')->default(true),

                Textarea::make('perex')->label('Perex')->rows(2)->columnSpanFull(),
            ]),

            Section::make('Hlavička')
                ->description('Nepovinné. Vyplněný nadtitulek udělá z hlavičky poutavý úvod dopadové stránky, prázdný nechá střídmou hlavičku pro právní texty.')
                ->columns(2)
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    TextInput::make('hero_eyebrow')
                        ->label('Nadtitulek')
                        ->helperText('Např. „Pro majitele e-shopů". Zapne velký nadpis přes celou šířku.'),

                    TextInput::make('hero_accent')
                        ->label('Zvýrazněná část nadpisu')
                        ->helperText('Slovo nebo úsek z názvu stránky. Vysází se cihlově kurzívou.'),

                    Toggle::make('hero_cta')
                        ->label('Tlačítka na e-mail a telefon')
                        ->helperText('Kontakty se berou z Nastavení → Kontakt.'),
                ]),

            Section::make('Obsah')
                ->description('Stránka se skládá z bloků. Pořadí měníte tažením, blok jde sbalit.')
                ->columnSpanFull()
                ->schema([
                    Builder::make('blocks')
                        ->hiddenLabel()
                        ->addActionLabel('Přidat blok')
                        ->collapsible()
                        ->blockNumbers(false)
                        ->columnSpanFull()
                        // Nabídka bloků je mřížka náhledů, ne seznam ikon — z drátěnky
                        // je vidět rozvržení dřív, než blok vůbec přidáte.
                        ->blockPickerColumns(3)
                        ->blockPickerWidth(Width::TwoExtraLarge)
                        ->blocks(self::blocks()),
                ]),

            Section::make('SEO')->columns(2)->columnSpanFull()->collapsed()->schema([
                TextInput::make('seo_title')->label('Titulek stránky'),
                Textarea::make('seo_description')->label('Popisek pro vyhledávače')->rows(2),
            ]),
        ]);
    }

    /** @return array<int, Block> */
    private static function blocks(): array
    {
        return [
            self::textBlock(),
            self::imageTextBlock(),
            self::beforeAfterBlock(),
            self::metricsBlock(),
            self::pointsBlock(),
            self::stepsBlock(),
            self::cardsBlock(),
            self::pillsBlock(),
            self::imageBlock(),
            self::quoteBlock(),
            self::ctaBlock(),
        ];
    }

    private static function textBlock(): Block
    {
        return Block::make('text')
            ->label('Text')
            ->icon(self::preview('text'))
            ->schema([
                RichEditor::make('body')->hiddenLabel()->columnSpanFull(),
            ]);
    }

    private static function imageTextBlock(): Block
    {
        return Block::make('image_text')
            ->label('Obrázek a text')
            ->icon(self::preview('image-text'))
            ->columns(2)
            ->schema([
                self::imageUpload()
                    ->helperText('Doporučený poměr 4:3, min. 1200 px na šířku. Bez obrázku se zobrazí jen text.'),

                Select::make('side')
                    ->label('Strana obrázku')
                    ->options(['left' => 'Vlevo', 'right' => 'Vpravo'])
                    ->default('left')
                    ->selectablePlaceholder(false),

                TextInput::make('image_alt')
                    ->label('Popisek obrázku (alt)')
                    ->helperText('Co je na obrázku. Čtou to hlasové čtečky a vyhledávače.'),

                TextInput::make('image_label')
                    ->label('Popisek zástupného vizuálu')
                    ->helperText('Dokud fotku nenahrajete, drží místo šrafované pole s tímhle textem. Prázdné = sekce bude jen text.'),

                Select::make('tone')
                    ->label('Barva sekce')
                    ->options(self::TONES)
                    ->default('cream')
                    ->selectablePlaceholder(false),

                TextInput::make('eyebrow')->label('Nadtitulek')->columnSpanFull(),
                TextInput::make('title')->label('Nadpis')->columnSpanFull(),
                RichEditor::make('body')->label('Text')->columnSpanFull(),
            ]);
    }

    private static function beforeAfterBlock(): Block
    {
        return Block::make('before_after')
            ->label('Před a po')
            ->icon(self::preview('before-after'))
            ->columns(2)
            ->schema([
                TextInput::make('eyebrow')->label('Nadtitulek'),

                Select::make('tone')
                    ->label('Barva sekce')
                    ->options(self::TONES)
                    ->default('ink')
                    ->selectablePlaceholder(false),

                TextInput::make('title')->label('Nadpis')->columnSpanFull(),

                Textarea::make('perex')->label('Perex')->rows(2)->columnSpanFull(),

                FileUpload::make('before')
                    ->label('Obrázek před')
                    ->image()
                    ->imageEditor()
                    ->directory('pages')
                    ->helperText('Bez obou obrázků se sekce nezobrazí. Ideálně stejně široké snímky.'),

                FileUpload::make('after')
                    ->label('Obrázek po')
                    ->image()
                    ->imageEditor()
                    ->directory('pages'),

                TextInput::make('before_alt')->label('Popisek obrázku před (alt)'),
                TextInput::make('after_alt')->label('Popisek obrázku po (alt)'),

                TextInput::make('before_label')
                    ->label('Štítek vlevo')
                    ->placeholder('Před')
                    ->helperText('Prázdné = „Před".'),

                TextInput::make('after_label')
                    ->label('Štítek vpravo')
                    ->placeholder('Po')
                    ->helperText('Prázdné = „Po".'),

                TextInput::make('caption')
                    ->label('Poznámka pod porovnáním')
                    ->columnSpanFull(),
            ]);
    }

    private static function metricsBlock(): Block
    {
        return Block::make('metrics')
            ->label('Statistiky')
            ->icon(self::preview('metrics'))
            ->columns(2)
            ->schema([
                TextInput::make('title')->label('Nadpis'),

                Select::make('tone')
                    ->label('Barva sekce')
                    ->options(self::TONES)
                    ->default('ink')
                    ->selectablePlaceholder(false),

                Repeater::make('items')
                    ->label('Čísla')
                    ->addActionLabel('Přidat číslo')
                    ->columns(2)
                    ->columnSpanFull()
                    ->defaultItems(3)
                    ->schema([
                        TextInput::make('value')->label('Hodnota')->placeholder('+41 %')->required(),
                        TextInput::make('label')->label('Popisek')->placeholder('meziroční růst tržeb'),
                    ]),

                Textarea::make('note')
                    ->label('Poznámka pod čísly')
                    ->rows(2)
                    ->columnSpanFull()
                    ->helperText('Např. odkud čísla pocházejí a za jaké období.'),
            ]);
    }

    private static function pointsBlock(): Block
    {
        return Block::make('points')
            ->label('Očíslované body')
            ->icon(self::preview('points'))
            ->columns(2)
            ->schema([
                TextInput::make('eyebrow')->label('Nadtitulek'),

                Select::make('tone')
                    ->label('Barva sekce')
                    ->options(self::TONES)
                    ->default('ink')
                    ->selectablePlaceholder(false),

                TextInput::make('title')->label('Nadpis')->columnSpanFull(),

                Textarea::make('perex')->label('Perex')->rows(3)->columnSpanFull(),

                Repeater::make('items')
                    ->label('Body')
                    ->addActionLabel('Přidat bod')
                    ->simple(TextInput::make('text')->required())
                    ->columnSpanFull()
                    ->defaultItems(3),
            ]);
    }

    private static function stepsBlock(): Block
    {
        return Block::make('steps')
            ->label('Postup v krocích')
            ->icon(self::preview('steps'))
            ->columns(2)
            ->schema([
                TextInput::make('eyebrow')->label('Nadtitulek'),

                Select::make('tone')
                    ->label('Barva sekce')
                    ->options(self::TONES)
                    ->default('ink')
                    ->selectablePlaceholder(false),

                TextInput::make('title')->label('Nadpis')->columnSpanFull(),

                Repeater::make('items')
                    ->label('Kroky')
                    ->addActionLabel('Přidat krok')
                    ->columnSpanFull()
                    ->defaultItems(4)
                    ->helperText('Číslují se samy podle pořadí. Nejlépe vypadají tři nebo čtyři.')
                    ->schema([
                        TextInput::make('title')->label('Název kroku')->required(),
                        Textarea::make('text')->label('Popis')->rows(2),
                    ]),
            ]);
    }

    private static function pillsBlock(): Block
    {
        return Block::make('pills')
            ->label('Výčet v pilulkách')
            ->icon(self::preview('pills'))
            ->columns(2)
            ->schema([
                TextInput::make('eyebrow')->label('Nadtitulek'),

                Select::make('tone')
                    ->label('Barva sekce')
                    ->options(self::TONES)
                    ->default('cream')
                    ->selectablePlaceholder(false),

                TextInput::make('title')->label('Nadpis')->columnSpanFull(),

                Textarea::make('perex')->label('Perex')->rows(2)->columnSpanFull(),

                Repeater::make('items')
                    ->label('Položky')
                    ->addActionLabel('Přidat položku')
                    ->simple(TextInput::make('text')->required())
                    ->columnSpanFull()
                    ->defaultItems(4)
                    ->helperText('Krátké výrazy, např. názvy platforem. Vysází se vedle sebe jako pilulky.'),
            ]);
    }

    private static function cardsBlock(): Block
    {
        return Block::make('cards')
            ->label('Karty')
            ->icon(self::preview('cards'))
            ->columns(2)
            ->schema([
                TextInput::make('title')->label('Nadpis'),

                Select::make('columns')
                    ->label('Počet sloupců')
                    ->options([2 => '2', 3 => '3'])
                    ->default(3)
                    ->selectablePlaceholder(false),

                Repeater::make('items')
                    ->label('Karty')
                    ->addActionLabel('Přidat kartu')
                    ->columnSpanFull()
                    ->defaultItems(3)
                    ->schema([
                        TextInput::make('title')->label('Nadpis karty')->required(),
                        Textarea::make('text')->label('Text karty')->rows(3),
                    ]),
            ]);
    }

    private static function imageBlock(): Block
    {
        return Block::make('image')
            ->label('Obrázek')
            ->icon(self::preview('image'))
            ->columns(2)
            ->schema([
                self::imageUpload()
                    ->columnSpanFull()
                    ->helperText('Zobrazí se přes celou šířku obsahu, ve vlastním poměru stran.'),

                TextInput::make('image_alt')->label('Popisek obrázku (alt)'),
                TextInput::make('caption')->label('Popisek pod obrázkem'),
            ]);
    }

    private static function quoteBlock(): Block
    {
        return Block::make('quote')
            ->label('Citace')
            ->icon(self::preview('quote'))
            ->schema([
                Textarea::make('text')->label('Citace')->rows(3),
                TextInput::make('author')->label('Kdo to říká'),
            ]);
    }

    private static function ctaBlock(): Block
    {
        return Block::make('cta')
            ->label('Výzva k akci')
            ->icon(self::preview('cta'))
            ->columns(2)
            ->schema([
                TextInput::make('eyebrow')->label('Nadtitulek'),
                TextInput::make('title')->label('Nadpis')->required(),
                Textarea::make('perex')->label('Perex')->rows(2)->columnSpanFull(),
                TextInput::make('secondary_label')->label('Druhé tlačítko — text'),
                TextInput::make('secondary_url')->label('Druhé tlačítko — odkaz')->url(),
            ])
            ->columnSpanFull();
    }

    /**
     * Drátěnka bloku do nabídky „Přidat blok". Filament bere u ikony cestu
     * s lomítkem jako obrázek, takže se místo piktogramu vysází celý náhled.
     *
     * Soubory jsou v public/images/blocks/, vzhled nabídky řeší styl
     * v resources/views/filament/block-picker-styles.blade.php.
     */
    private static function preview(string $block): string
    {
        return asset("images/blocks/{$block}.svg");
    }

    /**
     * Obrázky bloků jdou na disk `public` jako cesta v JSON, ne přes MediaLibrary.
     * Uvnitř Builderu by si bloky se sdílenou kolekcí navzájem mazaly média.
     */
    private static function imageUpload(): FileUpload
    {
        return FileUpload::make('image')
            ->label('Obrázek')
            ->image()
            ->imageEditor()
            ->directory('pages');
    }
}
