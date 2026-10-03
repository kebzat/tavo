<?php

namespace App\Filament\Tools\Resources\Clients\Schemas;

use App\Enums\WorkArea;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Klient')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($set, $state, $operation) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    TextInput::make('slug')
                        ->label('Zkratka')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Interní označení, nikde se nezveřejňuje.'),

                    TextInput::make('website_url')
                        ->label('Web')
                        ->url()
                        ->placeholder('https://'),

                    Toggle::make('is_archived')
                        ->label('Archivovaný')
                        ->helperText('Schová klienta z běžného výpisu.'),
                ]),

            Section::make('Kontakt')
                ->columns(2)
                ->schema([
                    TextInput::make('contact_name')->label('Kontaktní osoba'),
                    TextInput::make('contact_email')->label('E-mail')->email(),
                    Textarea::make('note')
                        ->label('Interní poznámka')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Vidíme jen my, na sdílené stránce se nezobrazuje.'),
                ]),

            Section::make('Pravidelná spolupráce')
                ->description('Paušál po oblastech a přehled pro klienta: hodiny, hotová práce, co čeká na něj a plán.')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    DatePicker::make('started_on')
                        ->label('Spolupráce od')
                        ->native(false)
                        ->displayFormat('j. n. Y')
                        ->helperText('Od tohoto měsíce začíná přehled a graf hodin.'),

                    Toggle::make('dashboard_enabled')
                        ->label('Přehled vidí klient')
                        ->helperText('Bez zapnutí odkaz vrací 404. Přihlášení ho vidí vždy.'),

                    TextEntry::make('dashboard_link')
                        ->label('Odkaz pro klienta')
                        ->visible(fn ($operation): bool => $operation === 'edit')
                        ->state(fn (?Client $record): ?string => $record?->dashboardPreviewUrl())
                        ->url(fn (?Client $record): ?string => $record?->dashboardPreviewUrl(), shouldOpenInNewTab: true)
                        ->copyable()
                        ->columnSpanFull(),

                    Repeater::make('retainers')
                        ->label('Paušál')
                        ->relationship()
                        ->orderColumn('order_column')
                        ->addActionLabel('Přidat oblast')
                        ->defaultItems(0)
                        ->columns(6)
                        ->columnSpanFull()
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        ->schema([
                            Select::make('area')
                                ->label('Oblast')
                                ->options(WorkArea::class)
                                ->default(WorkArea::Web)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($set, $get, $state): void {
                                    $area = $state instanceof WorkArea ? $state : WorkArea::tryFrom((string) $state);

                                    if ($area && blank($get('label'))) {
                                        $set('label', $area->getLabel());
                                    }
                                }),
                            TextInput::make('label')
                                ->label('Název pro klienta')
                                ->required()
                                ->default(WorkArea::Web->getLabel()),
                            TextInput::make('monthly_fee')
                                ->label('Měsíčně')
                                ->numeric()
                                ->minValue(0)
                                ->suffix('Kč')
                                ->required(),
                            TextInput::make('included_hours')
                                ->label('Hodin v paušálu')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.5)
                                ->suffix('h')
                                ->helperText('Prázdné = hodiny jen ukazujeme, nad rámec neúčtujeme.'),
                            DatePicker::make('starts_on')->label('Od')->native(false)->displayFormat('j. n. Y'),
                            DatePicker::make('ends_on')->label('Do')->native(false)->displayFormat('j. n. Y'),
                        ]),
                ]),
        ]);
    }
}
