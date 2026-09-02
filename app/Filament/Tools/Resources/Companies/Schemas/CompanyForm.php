<?php

namespace App\Filament\Tools\Resources\Companies\Schemas;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Enums\Crm\Priority;
use App\Models\Crm\Company;
use App\Models\Crm\Tag;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Firma')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Název')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('website')
                        ->label('Web')
                        ->placeholder('example.cz')
                        ->maxLength(255)
                        // Duplicitu hlásíme, ale neblokujeme. Dvě karty na jednu
                        // doménu mají občas důvod (jiná pobočka, jiný kontakt)
                        // a rozhodnout to umí jen člověk.
                        ->live(onBlur: true)
                        ->hintColor('danger')
                        // Ikona i text visí na stejné podmínce. Samotná ikona
                        // bez textu by u každé firmy bez duplicity hlásila
                        // problém, který tam není.
                        ->hintIcon(fn ($state, ?Company $record) => Company::withDomain($state, $record?->getKey()) !== null
                            ? Heroicon::OutlinedExclamationTriangle
                            : null)
                        ->hint(function ($state, ?Company $record): ?string {
                            $duplicate = Company::withDomain($state, $record?->getKey());

                            return $duplicate !== null
                                ? 'Tuhle doménu už vedeme jako „'.$duplicate->name.'"'
                                : null;
                        }),

                    TextInput::make('city')->label('Město')->maxLength(255),
                    TextInput::make('industry')->label('Obor')->maxLength(255),

                    Select::make('segment')
                        ->label('Segment')
                        ->options(CompanySegment::class)
                        ->default(CompanySegment::Local)
                        ->native(false)
                        ->required(),

                    TextInput::make('platform')
                        ->label('Platforma')
                        ->maxLength(255)
                        ->datalist(['Shoptet', 'WooCommerce', 'Upgates', 'Shopify', 'WordPress', 'Laravel'])
                        ->helperText('Na čem web běží.'),
                ]),

            Section::make('Obchodní úhel')
                ->description('Co firmě chybí a čím ji oslovíme. Dosazuje se do šablon zpráv.')
                ->schema([
                    Textarea::make('pain')
                        ->label('Bolest')
                        ->rows(2)
                        ->placeholder('Web není na mobilu použitelný, objednávka trvá pět kroků…'),

                    Textarea::make('offer')
                        ->label('Nabídka')
                        ->rows(2)
                        ->placeholder('Co jí nabízíme.'),

                    TextInput::make('reference_to_use')
                        ->label('Reference')
                        ->maxLength(255)
                        ->helperText('Kterou naší prací argumentovat.'),
                ]),

            Section::make('Vedení')
                ->columns(2)
                ->schema([
                    Select::make('status')
                        ->label('Stav')
                        ->options(CompanyStatus::class)
                        ->default(CompanyStatus::New)
                        ->native(false)
                        ->live()
                        ->required(),

                    Select::make('priority')
                        ->label('Priorita')
                        ->options(Priority::class)
                        ->default(Priority::B)
                        ->native(false)
                        ->required(),

                    Select::make('source')
                        ->label('Zdroj')
                        ->options(CompanySource::class)
                        ->default(CompanySource::Research)
                        ->native(false)
                        ->required(),

                    Select::make('owner_id')
                        ->label('Vede')
                        ->options(fn (): array => User::orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn () => Auth::id())
                        ->native(false),

                    Select::make('tags')
                        ->label('Štítky')
                        ->relationship('tags', 'name')
                        ->multiple()
                        ->preload()
                        // Štítky jsou volné, zakládají se za běhu — číselník
                        // by u nich znamenal jen překlikávání do jiné sekce.
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('Název')
                                ->required()
                                ->unique(Tag::class, 'name'),
                        ])
                        ->columnSpanFull(),

                    TextInput::make('lost_reason')
                        ->label('Důvod prohry')
                        ->maxLength(255)
                        ->columnSpanFull()
                        ->visible(fn ($get): bool => $get('status') === CompanyStatus::Lost->value
                            || $get('status') === CompanyStatus::Lost),
                ]),

            Section::make('Poznámky')
                ->collapsed()
                ->schema([
                    Textarea::make('notes')->label('Poznámky')->rows(4),
                ]),
        ]);
    }
}
