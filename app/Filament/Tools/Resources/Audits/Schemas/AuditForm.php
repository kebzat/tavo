<?php

namespace App\Filament\Tools\Resources\Audits\Schemas;

use App\Models\Audit;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Základ')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Název')
                        ->required()
                        ->placeholder('SEO a GEO audit e-shopu …')
                        ->columnSpanFull(),

                    Select::make('client_id')
                        ->label('Klient')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Na sdílené stránce se audit propojí s checklisty téhož klienta.'),

                    DatePicker::make('audited_at')
                        ->label('Stav k datu')
                        ->native(false)
                        ->displayFormat('j. n. Y')
                        ->default(now()),

                    Textarea::make('intro')
                        ->label('Úvod')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Perex v tmavé hlavičce. Můžete nechat prázdné.'),
                ]),

            Section::make('Čísla pod hlavičkou')
                ->description('Až čtyři dlaždice s hlavními zjištěními. Bez nich se řádek nezobrazí.')
                ->collapsible()
                ->schema([
                    Repeater::make('highlights')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('value')->label('Číslo')->required()->placeholder('1 974'),
                            TextInput::make('label')->label('Popisek')->placeholder('URL v sitemapě, užitečných je jen ~80'),
                        ])
                        ->columns(2)
                        ->maxItems(4)
                        ->defaultItems(0)
                        ->addActionLabel('Přidat číslo')
                        ->reorderable(),
                ]),

            Section::make('Text auditu')
                ->schema([
                    MarkdownEditor::make('body')
                        ->hiddenLabel()
                        ->helperText(
                            'Markdown. ## Nadpis = kapitola v obsahu vlevo. ### Nadpis = nález. '
                            .'Štítek stavu: [[kritické]], [[vysoké]], [[střední]], [[nízké]], [[v pořádku]], '
                            .'v tabulkách třeba [[ano]], [[ne]], [[částečně]]. Citace (> text) se vykreslí '
                            .'jako zvýrazněný blok, hodí se na „Oprava:“. Box (třeba ceník) začíná řádkem '
                            .'„::: box Popisek“ a končí „:::“. Uvnitř ### název a pod ním **cena**. '
                            .'Boxy těsně za sebou se postaví vedle sebe.'
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Sdílení s klientem')
                ->description('Odkaz je veřejný a nechráněný heslem. Kdo ho zná, audit si přečte.')
                ->schema([
                    Toggle::make('is_public')
                        ->label('Zpřístupnit přes odkaz')
                        ->default(true),

                    TextInput::make('public_token')
                        ->label('Odkaz pro klienta')
                        ->disabled()
                        ->dehydrated(false)
                        ->visible(fn ($operation): bool => $operation === 'edit')
                        ->formatStateUsing(fn (?Audit $record): ?string => $record?->publicUrl())
                        ->placeholder('Zapněte sdílení a uložte.'),
                ]),
        ]);
    }
}
