<?php

namespace App\Filament\Tools\Resources\Checklists\Schemas;

use App\Models\Checklist;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ChecklistForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Základ')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->required()
                        ->columnSpanFull(),

                    Toggle::make('is_template')
                        ->label('Šablona')
                        ->live()
                        ->helperText('Šablona slouží jen jako podklad ke klonování. Nedá se sdílet a nepatří ke klientovi.'),

                    Select::make('client_id')
                        ->label('Klient')
                        ->relationship('client', 'name')
                        ->searchable()
                        ->preload()
                        ->visible(fn ($get): bool => ! $get('is_template')),

                    // Není to sloupec v databázi. Hodnotu vyzvedne a odstraní
                    // CreateChecklist, který po uložení přelije strukturu.
                    Select::make('template_id')
                        ->label('Předvyplnit ze šablony')
                        ->options(fn (): array => Checklist::templates()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?int => Checklist::templates()->value('id'))
                        ->placeholder('Založit prázdný')
                        ->columnSpanFull()
                        ->visible(fn ($operation, $get): bool => $operation === 'create' && ! $get('is_template'))
                        ->helperText('Zkopíruje kategorie, sekce i položky. Stavy začnou na „Čeká".'),

                    Textarea::make('intro')
                        ->label('Úvod')
                        ->rows(4)
                        ->columnSpanFull()
                        ->helperText('Zobrazí se nahoře na sdílené stránce. Můžete nechat prázdné.'),
                ]),

            Section::make('Sdílení s klientem')
                ->description('Odkaz je veřejný a nechráněný heslem. Kdo ho zná, checklist uvidí a může v něm odškrtávat.')
                // Při zakládání ještě není co sdílet, odkaz vznikne až s záznamem.
                ->visible(fn ($operation, $get): bool => $operation === 'edit' && ! $get('is_template'))
                ->schema([
                    Toggle::make('is_public')
                        ->label('Zpřístupnit přes odkaz'),

                    TextInput::make('public_token')
                        ->label('Odkaz pro klienta')
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (?Checklist $record): ?string => $record?->publicUrl())
                        ->placeholder('Vznikne po uložení a zapnutí sdílení.'),
                ]),
        ]);
    }
}
