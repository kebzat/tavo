<?php

namespace App\Support\Ads;

use App\Enums\Ads\PrimaryGoal;
use App\Settings\AdsSettings;
use Closure;

/**
 * Všechna čísla, která jde v reklamách ukázat: dlaždice na detailu klienta
 * a v reportu, šest čísel na kartě v přehledu klientů, volby v „Co ukazovat“.
 *
 * U každého čísla: český název (u konverzí podle cíle klienta), výpočet
 * z Metrics, formát, jestli je růst dobře (barva změny) a pro které cíle
 * dává smysl. Pořadí klíčů je pořadí dlaždic.
 */
final class MetricCatalog
{
    /** Kolik čísel má karta klienta v přehledu. */
    public const OVERVIEW_SLOTS = 6;

    public const OVERVIEW_DEFAULT = ['spend', 'conversions', 'cost_per_conversion', 'roas', 'ctr', 'cpm'];

    /** Karta v přehledu podle cíle, když šablona v nastavení chybí. */
    public const CARD_DEFAULTS = [
        'purchases' => ['spend', 'conversions', 'cost_per_conversion', 'roas', 'ctr', 'cpm'],
        'leads' => ['spend', 'conversions', 'cost_per_conversion', 'form_leads', 'web_leads', 'ctr'],
        'traffic' => ['spend', 'conversions', 'cost_per_conversion', 'landing_page_views', 'ctr', 'cpm'],
        'awareness' => ['spend', 'reach', 'frequency', 'cpm', 'conversions', 'ctr_all'],
        'recruitment' => ['spend', 'conversions', 'cost_per_conversion', 'reach', 'ctr', 'cpm'],
    ];

    /** Staré klíče dlaždic, uložené u klientů a ve zmrazených reportech. */
    public const ALIASES = [
        'cost' => 'cost_per_conversion',
        'value' => 'purchase_value',
        'clicks' => 'link_clicks',
    ];

    /** Čísla, která potřebují přesný dosah za období (PeriodReach). */
    public const NEEDS_PERIOD_REACH = ['reach', 'frequency', 'unique_ctr'];

    /**
     * Výchozí dlaždice podle cíle. Platí, dokud správce v „Co ukazovat“
     * nevybere jinak. Nová čísla se zapínají ručně.
     */
    private const DEFAULTS = [
        'purchases' => ['spend', 'conversions', 'cost_per_conversion', 'roas', 'purchase_value', 'link_clicks', 'ctr', 'cpm'],
        'leads' => ['spend', 'conversions', 'cost_per_conversion', 'conversion_rate', 'link_clicks', 'cpc', 'ctr', 'cpm'],
        'traffic' => ['spend', 'conversions', 'cost_per_conversion', 'impressions', 'ctr', 'cpm'],
        'awareness' => ['spend', 'reach', 'frequency', 'cpm', 'conversions', 'cost_per_conversion', 'impressions', 'ctr_all'],
        'recruitment' => ['spend', 'conversions', 'cost_per_conversion', 'reach', 'link_clicks', 'cpc', 'ctr', 'cpm'],
    ];

    private const SHOP = ['purchases'];

    private const CONVERSIONS = ['purchases', 'leads', 'recruitment'];

    private const LEADS = ['leads', 'recruitment'];

    /** @var array<string, array{label: string, goal_label: ?Closure, format: string, direction: int, goals: ?list<string>, value: Closure}>|null */
    private static ?array $definitions = null;

    /**
     * Co přesně číslo znamená a z čeho se počítá. Pavel chce u CTR a konverzí
     * vědět, o jaký typ jde; ukazuje se v nápovědě u dlaždice a v „Co ukazovat“.
     */
    private const DESCRIPTIONS = [
        'spend' => 'Útrata v reklamním systému za období.',
        'conversions' => 'Konverze podle cíle, jak si je připsal reklamní systém (atribuce nastavená v účtu).',
        'cost_per_conversion' => 'Útrata děleno konverzemi, ze součtů za celé období.',
        'roas' => 'Hodnota nákupů připsaných reklamám děleno útratou. Ze součtů, ne průměr dní.',
        'purchase_value' => 'Hodnota nákupů připsaných reklamám (Meta: omni_purchase, sloupec Nákupy ve Správci reklam).',
        'average_order_value' => 'Hodnota nákupů děleno počtem nákupů.',
        'conversion_rate' => 'Konverze děleno prokliky na web (odkaz v reklamě).',
        'add_to_cart' => 'Události „přidání do košíku“ z pixelu. Počítá události, ne lidi.',
        'cost_per_add_to_cart' => 'Útrata děleno přidáními do košíku.',
        'checkouts' => 'Události „zahájení objednávky“ z pixelu. Počítá události, ne lidi.',
        'cost_per_checkout' => 'Útrata děleno zahájenými objednávkami.',
        'link_clicks' => 'Kliknutí na odkaz v reklamě, který vede na web (inline link clicks). Bez kliknutí na profil, „více“ a obrázek.',
        'cpc' => 'Útrata děleno prokliky na web.',
        'landing_page_views' => 'Proklik, po kterém se cílová stránka opravdu načetla a pixel se ozval.',
        'cost_per_landing_page_view' => 'Útrata děleno zobrazeními cílové stránky.',
        'landing_page_view_rate' => 'Kolik prokliků skončilo načtenou stránkou. Nízké číslo = pomalý web nebo nefunkční pixel.',
        'landing_page_conversion_rate' => 'Konverze děleno zobrazeními cílové stránky.',
        'reach' => 'Počet lidí, kteří reklamu viděli, za celé období bez opakování. Přes dny ani kampaně se nesčítá, bereme ho z Mety zvlášť.',
        'impressions' => 'Kolikrát se reklama zobrazila (jeden člověk víckrát).',
        'frequency' => 'Zobrazení děleno dosahem za celé období: kolikrát reklamu viděl průměrný člověk.',
        'ctr' => 'CTR odkazu: prokliky na web děleno zobrazeními. Nepočítá kliknutí na profil ani reakce.',
        'ctr_all' => 'CTR všech kliknutí: i na profil, „více“, obrázek a reakce. Vychází vyšší než CTR odkazu.',
        'unique_ctr' => 'Unikátní CTR odkazu: kolik zasažených lidí kliklo na web, za celé období.',
        'cpm' => 'Cena za 1 000 zobrazení.',
        'form_leads' => 'Leady z formuláře přímo na Facebooku a Instagramu (instant form, onsite_conversion.lead_grouped).',
        'web_leads' => 'Leady, které na webu zaznamenal Meta pixel (offsite_conversion.fb_pixel_lead).',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /** Klíč z katalogu, i ze starého názvu. Null, když takové číslo neznáme. */
    public static function canonical(string $key): ?string
    {
        $key = self::ALIASES[$key] ?? $key;

        return isset(self::definitions()[$key]) ? $key : null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::definitions()[$key]);
    }

    public static function appliesTo(string $key, PrimaryGoal $goal): bool
    {
        $goals = self::definitions()[$key]['goals'] ?? null;

        return $goals === null || in_array($goal->value, $goals, true);
    }

    /** Název čísla. Bez cíle obecný („Konverze“), s cílem konkrétní („Nákupy“). */
    public static function label(string $key, ?PrimaryGoal $goal = null): string
    {
        $definition = self::definitions()[$key];

        return $goal !== null && $definition['goal_label'] !== null
            ? ($definition['goal_label'])($goal)
            : $definition['label'];
    }

    /** Hodnota z Metrics. Null, když pro cíl nedává smysl nebo chybí jmenovatel. */
    public static function value(string $key, Metrics $metrics, PrimaryGoal $goal): ?float
    {
        if (! self::appliesTo($key, $goal)) {
            return null;
        }

        return (self::definitions()[$key]['value'])($metrics, $goal);
    }

    /** Co číslo znamená. U konverzí doplní, co se u cíle počítá. */
    public static function description(string $key, ?PrimaryGoal $goal = null): string
    {
        $text = self::DESCRIPTIONS[$key] ?? '';

        if ($goal !== null && in_array($key, ['conversions', 'cost_per_conversion', 'conversion_rate', 'landing_page_conversion_rate'], true)) {
            $text .= ' '.$goal->conversionDefinition();
        }

        return trim($text);
    }

    public static function format(string $key, ?float $value, string $currency = 'CZK'): string
    {
        return match (self::definitions()[$key]['format']) {
            'money' => Format::money($value, $currency),
            'unit_price' => Format::unitPrice($value, $currency),
            'count' => Format::count($value),
            'percent' => Format::percent($value),
            'roas' => Format::roas($value),
            'decimal' => Format::number($value, 2),
            default => Format::number($value),
        };
    }

    /** 1 = víc je lépe, -1 = míň je lépe, 0 = neutrální (útrata, frekvence). */
    public static function direction(string $key): int
    {
        return self::definitions()[$key]['direction'];
    }

    public static function needsPeriodReach(string $key): bool
    {
        return in_array($key, self::NEEDS_PERIOD_REACH, true);
    }

    /**
     * Čísla, která jde u klienta zapnout, pro formulář „Co ukazovat“.
     *
     * @return array<string, string>
     */
    public static function options(PrimaryGoal $goal): array
    {
        $options = [];

        foreach (self::keys() as $key) {
            if (self::appliesTo($key, $goal)) {
                $options[$key] = self::label($key, $goal);
            }
        }

        return $options;
    }

    /**
     * Všechna čísla s obecným názvem, pro výběr v přehledu klientů.
     *
     * @return array<string, string>
     */
    public static function allOptions(): array
    {
        return collect(self::keys())->mapWithKeys(fn (string $key): array => [$key => self::label($key)])->all();
    }

    /**
     * Výchozí dlaždice podle cíle: šablona z Nastavení reklam (e-shop, leady,
     * boosting, nábor), jinak vestavěná sada.
     *
     * @return list<string>
     */
    public static function defaults(PrimaryGoal $goal): array
    {
        $stored = rescue(fn () => app(AdsSettings::class)->tile_templates[$goal->value] ?? null, null, report: false);
        $keys = is_array($stored) ? array_values(array_filter($stored, fn ($key): bool => is_string($key) && self::exists($key) && self::appliesTo($key, $goal))) : [];

        return $keys ?: self::DEFAULTS[$goal->value];
    }

    /**
     * Šest čísel karty v přehledu pro cíl (šablona z Nastavení reklam).
     *
     * @return list<string>
     */
    public static function cardSlots(PrimaryGoal $goal): array
    {
        $templates = rescue(fn () => app(AdsSettings::class)->card_templates, [], report: false);

        return self::overviewSlots($templates[$goal->value] ?? self::CARD_DEFAULTS[$goal->value]);
    }

    /**
     * Dlaždice, které se u klienta ukážou. Bez výběru (null) výchozí sada
     * podle cíle, jinak vybrané v pořadí katalogu. Staré klíče se přeloží,
     * neznámé a pro cíl nesmyslné se vynechají.
     *
     * @param  list<string>|null  $chosen
     * @return list<string>
     */
    public static function tiles(?array $chosen, PrimaryGoal $goal): array
    {
        if ($chosen === null) {
            return self::defaults($goal);
        }

        $chosen = array_filter(array_map(fn ($key): ?string => is_string($key) ? self::canonical($key) : null, $chosen));
        $keys = array_values(array_filter(self::keys(), fn (string $key): bool => in_array($key, $chosen, true) && self::appliesTo($key, $goal)));

        // Výběr, ze kterého po změně cíle nic nezbylo, by nechal stránku bez čísel.
        return $keys ?: self::defaults($goal);
    }

    /**
     * Výběr k uložení. Když se shoduje s výchozí sadou, uloží se null,
     * ať klient dál dostává výchozí sadu i po jejím rozšíření.
     *
     * @param  list<string>  $chosen
     * @return list<string>|null
     */
    public static function selectionToStore(array $chosen, PrimaryGoal $goal): ?array
    {
        $keys = self::tiles(array_values($chosen), $goal);
        $defaults = self::defaults($goal);

        return array_diff($keys, $defaults) === [] && array_diff($defaults, $keys) === [] ? null : $keys;
    }

    /**
     * Šest čísel karty v přehledu. Neznámý klíč v nastavení nahradí výchozí.
     *
     * @param  array<int, mixed>  $stored
     * @return list<string>
     */
    public static function overviewSlots(array $stored): array
    {
        $slots = [];

        for ($slot = 0; $slot < self::OVERVIEW_SLOTS; $slot++) {
            $key = $stored[$slot] ?? null;
            $slots[] = (is_string($key) ? self::canonical($key) : null) ?? self::OVERVIEW_DEFAULT[$slot];
        }

        return $slots;
    }

    /** @return array<string, array{label: string, goal_label: ?Closure, format: string, direction: int, goals: ?list<string>, value: Closure}> */
    private static function definitions(): array
    {
        return self::$definitions ??= [
            'spend' => self::metric('Útrata', 'money', 0, fn (Metrics $m): float => $m->spend()),
            'conversions' => self::metric('Konverze', 'count', 1, fn (Metrics $m, PrimaryGoal $g): float => $m->conversions($g),
                goalLabel: fn (PrimaryGoal $g): string => $g->conversionLabel()),
            'cost_per_conversion' => self::metric('Cena za konverzi', 'unit_price', -1, fn (Metrics $m, PrimaryGoal $g): ?float => $m->costPerConversion($g),
                goalLabel: fn (PrimaryGoal $g): string => $g->costLabel()),
            'roas' => self::metric('ROAS', 'roas', 1, fn (Metrics $m): ?float => $m->roas(), self::SHOP),
            'purchase_value' => self::metric('Hodnota nákupů', 'money', 1, fn (Metrics $m): float => $m->get('purchase_value'), self::SHOP),
            'average_order_value' => self::metric('Průměrná hodnota nákupu', 'money', 1, fn (Metrics $m): ?float => $m->averageOrderValue(), self::SHOP),
            'conversion_rate' => self::metric('Konverzní poměr', 'percent', 1, fn (Metrics $m, PrimaryGoal $g): ?float => $m->conversionRate($g), self::CONVERSIONS),
            'add_to_cart' => self::metric('Přidání do košíku', 'count', 1, fn (Metrics $m): float => $m->get('add_to_cart'), self::SHOP),
            'cost_per_add_to_cart' => self::metric('Cena za přidání do košíku', 'unit_price', -1, fn (Metrics $m): ?float => $m->costPerAddToCart(), self::SHOP),
            'checkouts' => self::metric('Zahájené objednávky', 'count', 1, fn (Metrics $m): float => $m->get('checkouts'), self::SHOP),
            'cost_per_checkout' => self::metric('Cena za zahájenou objednávku', 'unit_price', -1, fn (Metrics $m): ?float => $m->costPerCheckout(), self::SHOP),
            'form_leads' => self::metric('Poptávky z formuláře na Metě', 'count', 1, fn (Metrics $m): float => $m->get('form_leads'), self::LEADS),
            'web_leads' => self::metric('Poptávky z webu (pixel)', 'count', 1, fn (Metrics $m): float => $m->get('web_leads'), self::LEADS),
            'link_clicks' => self::metric('Prokliky na web', 'number', 1, fn (Metrics $m): float => $m->get('link_clicks'), self::CONVERSIONS),
            'cpc' => self::metric('Cena za proklik', 'unit_price', -1, fn (Metrics $m): ?float => $m->cpc(), self::CONVERSIONS),
            'landing_page_views' => self::metric('Zobrazení cílové stránky', 'number', 1, fn (Metrics $m): float => $m->get('landing_page_views')),
            'cost_per_landing_page_view' => self::metric('Cena za zobrazení stránky', 'unit_price', -1, fn (Metrics $m): ?float => $m->costPerLandingPageView()),
            'landing_page_view_rate' => self::metric('Zobrazení stránky z prokliků', 'percent', 1, fn (Metrics $m): ?float => $m->landingPageViewRate()),
            'landing_page_conversion_rate' => self::metric('Konverze ze zobrazení stránky', 'percent', 1, fn (Metrics $m, PrimaryGoal $g): ?float => $m->landingPageConversionRate($g), self::CONVERSIONS,
                goalLabel: fn (PrimaryGoal $g): string => $g->conversionLabel().' ze zobrazení stránky'),
            'reach' => self::metric('Dosah', 'number', 1, fn (Metrics $m): ?float => $m->reach()),
            'impressions' => self::metric('Zobrazení', 'number', 1, fn (Metrics $m): float => $m->get('impressions')),
            'frequency' => self::metric('Frekvence', 'decimal', 0, fn (Metrics $m): ?float => $m->periodFrequency()),
            'ctr' => self::metric('CTR odkazu', 'percent', 1, fn (Metrics $m): ?float => $m->ctr()),
            'ctr_all' => self::metric('CTR všech kliknutí', 'percent', 1, fn (Metrics $m): ?float => $m->ctrAll()),
            'unique_ctr' => self::metric('Unikátní CTR odkazu', 'percent', 1, fn (Metrics $m): ?float => $m->uniqueCtr()),
            'cpm' => self::metric('CPM', 'unit_price', -1, fn (Metrics $m): ?float => $m->cpm()),
        ];
    }

    /**
     * @param  list<string>|null  $goals  pro které cíle číslo dává smysl, null = pro všechny
     * @return array{label: string, goal_label: ?Closure, format: string, direction: int, goals: ?list<string>, value: Closure}
     */
    private static function metric(string $label, string $format, int $direction, Closure $value, ?array $goals = null, ?Closure $goalLabel = null): array
    {
        return [
            'label' => $label,
            'goal_label' => $goalLabel,
            'format' => $format,
            'direction' => $direction,
            'goals' => $goals,
            'value' => $value,
        ];
    }
}
