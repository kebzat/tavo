<?php

namespace App\Filament\Tools\Actions\Ads;

use App\Enums\Ads\AdChangeType;
use App\Models\Ads\AdCampaign;
use App\Models\Ads\AdChange;
use App\Models\Client;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;

/**
 * Zápis zásahu do historie: co jsme změnili, kdy a u čeho. Z toho vychází
 * graf, report („Co jsme změnili“) i agent, když hledá příčinu změny.
 */
class AdChangeForm
{
    /** @return list<Component> */
    public static function schema(Client $client, ?int $groupId = null): array
    {
        return [
            Grid::make(2)->schema([
                DatePicker::make('changed_on')->label('Kdy')->native(false)->displayFormat('j. n. Y')->default(now())->required(),
                Select::make('type')->label('Co se změnilo')->options(AdChangeType::class)->required(),
            ]),
            Textarea::make('description')
                ->label('Popis')
                ->placeholder('Rozpočet Prodeje z 500 na 800 Kč denně, nové UGC video do prospectingu…')
                ->rows(3)
                ->required(),
            TextInput::make('expected')
                ->label('Co od toho čekáme')
                ->placeholder('Víc nákupů při ceně do 750 Kč')
                ->helperText('Nepovinné. Pomůže později vyhodnotit, jestli změna zabrala.')
                ->maxLength(255),
            Grid::make(2)->schema([
                Select::make('ad_campaign_group_id')
                    ->label('Skupina kampaní')
                    ->options(fn (): array => $client->adCampaignGroups()->pluck('name', 'id')->all())
                    ->default($groupId)
                    ->placeholder('Celý účet'),
                Select::make('ad_campaign_id')
                    ->label('Kampaň')
                    ->options(fn (): array => AdCampaign::query()->whereIn('ad_account_id', $client->activeAdAccountIds())->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('Žádná konkrétní'),
            ]),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function create(Client $client, array $data): AdChange
    {
        return $client->adChanges()->create([
            'changed_on' => $data['changed_on'],
            'type' => $data['type'],
            'description' => $data['description'],
            'expected' => $data['expected'] ?? null,
            'ad_campaign_group_id' => $data['ad_campaign_group_id'] ?? null,
            'ad_campaign_id' => $data['ad_campaign_id'] ?? null,
            'user_id' => auth()->id(),
        ]);
    }
}
