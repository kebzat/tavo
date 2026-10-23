<?php

namespace App\Filament\Tools\Actions\Ads;

use App\Enums\Ads\PrimaryGoal;
use App\Models\Ads\AdCampaignGroup;
use App\Models\Client;
use App\Settings\AdsSettings;
use App\Support\Ads\CampaignGroups;
use App\Support\Ads\MetricCatalog;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Formulář skupiny kampaní: název, typ (šablona čísel), zaškrtnuté kampaně,
 * pravidlo pro automatické zařazení nových kampaní, cíle, rozpočet, vlastní
 * čísla a prahy upozornění.
 */
class CampaignGroupForm
{
    /** @return list<Component> */
    public static function schema(Client $client): array
    {
        $currency = $client->adCurrency();
        $settings = app(AdsSettings::class);

        return [
            Grid::make(2)->schema([
                TextInput::make('name')->label('Název skupiny')->placeholder('Prodej, Brand, Nábor…')->required()->maxLength(60),
                Select::make('goal')
                    ->label('Typ a šablona čísel')
                    ->options(collect(PrimaryGoal::cases())->mapWithKeys(fn (PrimaryGoal $goal): array => [$goal->value => $goal->templateLabel().': '.$goal->getLabel()])->all())
                    ->default(PrimaryGoal::Purchases->value)
                    ->required()
                    ->live(),
            ]),
            CheckboxList::make('campaigns')
                ->label('Kampaně ve skupině')
                ->options(fn (): array => CampaignGroups::options($client))
                ->helperText('Kampaň je nejvýš v jedné skupině. Zaškrtnutím ji přesunete sem i z jiné skupiny. Celková útrata účtu zůstává vidět v „Celý účet“.')
                ->searchable()
                ->bulkToggleable()
                ->columns(1),
            TextInput::make('name_rule')
                ->label('Nové kampaně zařadit podle názvu')
                ->placeholder('sales, prodej, asc')
                ->helperText('Slova oddělená čárkou. Nová kampaň, jejíž název některé obsahuje, se při ranním stažení zařadí sama. Ostatní nové kampaně čekají na zařazení.'),
            Section::make('Cíle a rozpočet skupiny')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('monthly_budget')->label('Rozpočet na měsíc')->numeric()->minValue(0)->suffix($currency),
                        TextInput::make('target_cpa')->label('Cílová cena za konverzi')->numeric()->minValue(0)->suffix($currency)
                            ->visible(fn (Get $get): bool => self::goal($get)->isPerformance()),
                        TextInput::make('target_roas')->label('Cílový ROAS')->numeric()->minValue(0)->step(0.1)
                            ->visible(fn (Get $get): bool => self::goal($get)->hasValue()),
                    ]),
                ]),
            Section::make('Čísla')
                ->description('Prázdné = šablona typu skupiny z Nastavení reklam.')
                ->collapsed()
                ->schema([
                    CheckboxList::make('dashboard.tiles')
                        ->label('Dlaždice na detailu a v reportu')
                        ->options(fn (Get $get): array => MetricCatalog::options(self::goal($get)))
                        ->columns(3),
                ]),
            Section::make('Upozornění pro skupinu')
                ->description('Prázdné = platí nastavení klienta, jinak Nastavení reklam.')
                ->collapsed()
                ->schema([
                    Grid::make(4)->schema([
                        TextInput::make('alert_overrides.cpa_over_pct')->label('Cena nad cíl o (%)')->numeric()->minValue(1)->placeholder((string) $settings->cpa_over_pct),
                        TextInput::make('alert_overrides.roas_under_pct')->label('ROAS pod cíl o (%)')->numeric()->minValue(1)->maxValue(100)->placeholder((string) $settings->roas_under_pct),
                        TextInput::make('alert_overrides.min_conversions')->label('Hodnotit od konverzí za týden')->numeric()->minValue(1)->placeholder((string) $settings->min_conversions),
                        TextInput::make('alert_overrides.min_spend')->label('Hodnotit od útraty za týden')->numeric()->minValue(0)->suffix($currency)->placeholder((string) $settings->min_spend),
                        TextInput::make('alert_overrides.frequency_max')->label('Frekvence nad')->numeric()->minValue(1)->step(0.1)->placeholder((string) $settings->frequency_max),
                        TextInput::make('alert_overrides.ctr_drop_pct')->label('Pokles CTR o (%)')->numeric()->minValue(1)->placeholder((string) $settings->ctr_drop_pct),
                        TextInput::make('alert_overrides.budget_under_pct')->label('Čerpání pod (%)')->numeric()->minValue(1)->maxValue(100)->placeholder((string) $settings->budget_under_pct),
                        TextInput::make('alert_overrides.budget_over_pct')->label('Čerpání nad (%)')->numeric()->minValue(100)->maxValue(300)->placeholder((string) $settings->budget_over_pct),
                    ]),
                    CheckboxList::make('alert_overrides.muted')
                        ->label('Ztlumená pravidla')
                        ->options(fn (): array => self::mutableRules())
                        ->columns(2),
                ]),
            Grid::make(2)->schema([
                Toggle::make('weekly_report')->label('Týdenní report za skupinu'),
                Toggle::make('monthly_report')->label('Měsíční report za skupinu'),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public static function fill(AdCampaignGroup $group): array
    {
        return $group->only(['name', 'name_rule', 'monthly_budget', 'target_cpa', 'target_roas', 'weekly_report', 'monthly_report', 'alert_overrides', 'dashboard']) + [
            'goal' => $group->goal->value,
            'campaigns' => $group->campaignIds(),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function save(Client $client, array $data, ?AdCampaignGroup $group = null): AdCampaignGroup
    {
        $goal = $data['goal'] instanceof PrimaryGoal ? $data['goal'] : PrimaryGoal::from((string) $data['goal']);
        $tiles = array_values($data['dashboard']['tiles'] ?? []);
        $overrides = collect($data['alert_overrides'] ?? [])
            ->reject(fn ($value): bool => blank($value))
            ->map(fn ($value) => is_numeric($value) ? $value + 0 : $value)
            ->all();

        $values = [
            'name' => $data['name'],
            'goal' => $goal,
            'name_rule' => $data['name_rule'] ?? null,
            'monthly_budget' => $data['monthly_budget'] ?? null,
            'target_cpa' => $goal->isPerformance() ? ($data['target_cpa'] ?? null) : null,
            'target_roas' => $goal->hasValue() ? ($data['target_roas'] ?? null) : null,
            'dashboard' => $tiles === [] ? null : ['tiles' => MetricCatalog::selectionToStore($tiles, $goal)],
            'alert_overrides' => $overrides ?: null,
            'weekly_report' => (bool) ($data['weekly_report'] ?? false),
            'monthly_report' => (bool) ($data['monthly_report'] ?? false),
        ];

        $group ??= $client->adCampaignGroups()->make(['sort' => $client->adCampaignGroups()->max('sort') + 1]);
        $group->fill($values)->save();

        CampaignGroups::sync($group, $data['campaigns'] ?? []);
        CampaignGroups::applyRules($client);

        return $group;
    }

    /** @return array<string, string> */
    public static function mutableRules(): array
    {
        return [
            'no_conversions' => 'Útrata bez konverzí',
            'no_spend' => 'Včera nulová útrata',
            'budget_pacing' => 'Čerpání rozpočtu',
            'cost_above_target' => 'Cena za konverzi nad cílem',
            'roas_below_target' => 'ROAS pod cílem',
            'creative_fatigue' => 'Únava kreativ',
            'spend_spike' => 'Skok útraty',
        ];
    }

    private static function goal(Get $get): PrimaryGoal
    {
        $goal = $get('goal');

        return $goal instanceof PrimaryGoal ? $goal : (PrimaryGoal::tryFrom((string) $goal) ?? PrimaryGoal::Purchases);
    }
}
