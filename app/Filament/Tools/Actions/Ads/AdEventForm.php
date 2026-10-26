<?php

namespace App\Filament\Tools\Actions\Ads;

use App\Enums\Ads\AdEventType;
use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdEvent;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Termín v kalendáři reklam: klient, případně skupina, kampaň a konkrétní
 * reklamy, datum, druh, poznámka a kolik dní předem upozornit. U licence
 * UGC i odkaz na podmínky a videa. Reklamu vypíná Pavel ručně.
 */
class AdEventForm
{
    /** @return list<Component> */
    public static function schema(?Client $client = null): array
    {
        $clientId = fn (Get $get): ?int => $client?->getKey() ?? (is_numeric($get('client_id')) ? (int) $get('client_id') : null);

        return [
            Select::make('client_id')
                ->label('Klient')
                ->options(fn (): array => Client::query()->withAds()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->required()
                ->live()
                ->visible($client === null),
            Grid::make(2)->schema([
                Select::make('type')
                    ->label('Druh')
                    ->options(AdEventType::class)
                    ->default(AdEventType::LicenseEnd->value)
                    ->required()
                    ->live(),
                DatePicker::make('due_on')->label('Kdy')->native(false)->displayFormat('j. n. Y')->required(),
            ]),
            TextInput::make('title')
                ->label('Co')
                ->placeholder('Licence na video od @tvurce končí, Black Friday startuje…')
                ->required()
                ->maxLength(255),
            Grid::make(2)->schema([
                Select::make('ad_campaign_group_id')
                    ->label('Skupina kampaní')
                    ->options(fn (Get $get): array => ($id = $clientId($get)) ? Client::find($id)?->adCampaignGroups()->pluck('name', 'id')->all() ?? [] : [])
                    ->placeholder('Celý účet'),
                Select::make('ad_campaign_id')
                    ->label('Kampaň')
                    ->options(fn (Get $get): array => ($id = $clientId($get)) ? AdCampaign::query()->whereIn('ad_account_id', Client::find($id)?->activeAdAccountIds() ?? [])->orderBy('name')->pluck('name', 'id')->all() : [])
                    ->searchable()
                    ->placeholder('Žádná konkrétní'),
            ]),
            TextInput::make('ads')
                ->label('Konkrétní reklamy nebo videa')
                ->placeholder('UGC_Petra_batoh_v2, UGC_Petra_batoh_v3')
                ->maxLength(255),
            TextInput::make('terms_url')
                ->label('Podmínky licence (odkaz)')
                ->url()
                ->maxLength(500)
                ->visible(fn (Get $get): bool => self::type($get) === AdEventType::LicenseEnd),
            Repeater::make('links')
                ->label('Odkazy na videa a podklady')
                ->schema([
                    TextInput::make('label')->label('Popis')->required()->maxLength(80),
                    TextInput::make('url')->label('Odkaz')->url()->required()->maxLength(500),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Přidat odkaz')
                ->collapsible(),
            Grid::make(2)->schema([
                Select::make('remind_days')
                    ->label('Upozornit předem')
                    ->options([0 => 'V den termínu', 1 => 'Den předem', 3 => '3 dny předem', 7 => 'Týden předem', 14 => '2 týdny předem', 30 => 'Měsíc předem'])
                    ->default(7)
                    ->required(),
            ]),
            Textarea::make('note')->label('Poznámka')->rows(3),
        ];
    }

    /** @return array<string, mixed> */
    public static function fill(AdEvent $event): array
    {
        return $event->only(['client_id', 'title', 'ad_campaign_group_id', 'ad_campaign_id', 'ads', 'terms_url', 'links', 'remind_days', 'note'])
            + ['type' => $event->type->value, 'due_on' => $event->due_on->toDateString()];
    }

    /** @param  array<string, mixed>  $data */
    public static function save(array $data, ?Client $client = null, ?AdEvent $event = null): AdEvent
    {
        $event ??= new AdEvent(['user_id' => auth()->id()]);
        $event->fill([
            'client_id' => $client?->getKey() ?? $data['client_id'] ?? $event->client_id,
            'type' => $data['type'],
            'title' => $data['title'],
            'due_on' => $data['due_on'],
            'remind_days' => (int) ($data['remind_days'] ?? 7),
            'ad_campaign_group_id' => $data['ad_campaign_group_id'] ?? null,
            'ad_campaign_id' => $data['ad_campaign_id'] ?? null,
            'ads' => $data['ads'] ?? null,
            'terms_url' => $data['terms_url'] ?? null,
            'links' => array_values($data['links'] ?? []) ?: null,
            'note' => $data['note'] ?? null,
        ])->save();

        return $event;
    }

    private static function type(Get $get): ?AdEventType
    {
        $type = $get('type');

        return $type instanceof AdEventType ? $type : AdEventType::tryFrom((string) $type);
    }
}
