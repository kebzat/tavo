<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;

/**
 * Skládaný obsah pro statické stránky i detaily referencí. Sada bloků je pro obě
 * místa stejná schválně: co jde poskládat na stránce, má jít poskládat i u reference.
 *
 * Vykreslení řeší komponenty v resources/views/components/blocks/,
 * čtení na modelu trait App\Models\Concerns\HasContentBlocks.
 */
class ContentBlocks
{
    /** Světlá / tmavá varianta sekce — dvojice, která se opakuje ve víc blocích. */
    private const TONES = [
        'cream' => 'Světlá',
        'ink' => 'Tmavá',
    ];

    /**
     * Builder pro formulář. `$directory` říká, kam se ukládají nahrané obrázky,
     * ať se nemíchají soubory stránek a referencí do jedné složky.
     */
    public static function builder(string $directory): Builder
    {
        return Builder::make('blocks')
            ->hiddenLabel()
            ->addActionLabel('Přidat blok')
            ->collapsible()
            ->blockNumbers(false)
            ->columnSpanFull()
            // Nabídka bloků je mřížka náhledů, ne seznam ikon — z drátěnky
            // je vidět rozvržení dřív, než blok vůbec přidáte.
            ->blockPickerColumns(3)
            ->blockPickerWidth(Width::TwoExtraLarge)
            ->blocks(self::blocks($directory));
    }

    /** @return array<int, Block> */
    private static function blocks(string $directory): array
    {
        return [
            self::textBlock(),
            self::imageTextBlock($directory),
            self::beforeAfterBlock($directory),
            self::metricsBlock(),
            self::pointsBlock(),
            self::bulletsBlock(),
            self::stepsBlock(),
            self::cardsBlock(),
            self::pillsBlock(),
            self::imageBlock($directory),
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

    private static function imageTextBlock(string $directory): Block
    {
        return Block::make('image_text')
            ->label('Obrázek a text')
            ->icon(self::preview('image-text'))
            ->columns(2)
            ->schema([
                self::imageUpload($directory)
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

    private static function beforeAfterBlock(string $directory): Block
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
                    ->directory($directory)
                    ->helperText('Bez obou obrázků se sekce nezobrazí. Ideálně stejně široké snímky.'),

                FileUpload::make('after')
                    ->label('Obrázek po')
                    ->image()
                    ->imageEditor()
                    ->directory($directory),

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
                    // Cihlová je tu navíc kvůli sekci „Výsledek" na detailu reference.
                    ->options(self::TONES + ['brick' => 'Cihlová'])
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

    private static function bulletsBlock(): Block
    {
        return Block::make('bullets')
            ->label('Odrážky ve sloupcích')
            ->icon(self::preview('bullets'))
            ->columns(2)
            ->schema([
                TextInput::make('title')->label('Nadpis'),

                Select::make('tone')
                    ->label('Barva sekce')
                    ->options(self::TONES)
                    ->default('ink')
                    ->selectablePlaceholder(false),

                Textarea::make('perex')->label('Perex')->rows(2)->columnSpanFull(),

                Repeater::make('columns')
                    ->label('Sloupce')
                    ->addActionLabel('Přidat sloupec')
                    ->columnSpanFull()
                    ->defaultItems(1)
                    ->maxItems(2)
                    ->helperText('Jeden sloupec zabere celou šířku, dva se rozdělí na půl.')
                    ->schema([
                        TextInput::make('label')
                            ->label('Nadtitulek sloupce')
                            ->helperText('Např. „Role marketingu". Vysází se cihlově verzálkami.'),

                        Repeater::make('items')
                            ->label('Odrážky')
                            ->addActionLabel('Přidat odrážku')
                            ->simple(TextInput::make('text')->required())
                            ->defaultItems(3),
                    ]),

                Textarea::make('note')
                    ->label('Poznámka pod odrážkami')
                    ->rows(2)
                    ->columnSpanFull()
                    ->helperText('Drobným písmem, např. odkud čísla pocházejí.'),
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

    private static function imageBlock(string $directory): Block
    {
        return Block::make('image')
            ->label('Obrázek')
            ->icon(self::preview('image'))
            ->columns(2)
            ->schema([
                self::imageUpload($directory)
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
    private static function imageUpload(string $directory): FileUpload
    {
        return FileUpload::make('image')
            ->label('Obrázek')
            ->image()
            ->imageEditor()
            ->directory($directory);
    }
}
