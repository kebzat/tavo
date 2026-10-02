<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\ClientLogo;
use App\Models\Founder;
use App\Models\ProcessStep;
use App\Models\Service;
use App\Models\Testimonial;
use App\Settings\HomeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class HomeController extends Controller
{
    public function __invoke(HomeSettings $home): View
    {
        $founders = Founder::ordered()->get();

        return view('home', [
            'home' => $home,
            'services' => Service::published()->ordered()->get(),
            'cases' => CaseStudy::published()->where('is_featured', true)->ordered()->with('category')->get(),
            'loopItems' => $home->loop_items,
            'processSteps' => ProcessStep::ordered()->get(),
            'pricingPlans' => $this->pricingPlans($home),
            'pricingExamples' => $this->pricingExamples($home),
            'testimonials' => Testimonial::published()->ordered()->get(),
            'latest' => $this->latestCase($home),
            'trustItems' => collect($home->trust_items)
                ->filter(fn (array $item): bool => filled($item['value'] ?? null) && filled($item['label'] ?? null))
                ->values(),
            'clientLogos' => ClientLogo::published()->ordered()->with('media')->get()
                ->map(fn (ClientLogo $logo): ?array => $logo->logoImage())
                ->filter()
                ->values(),
            'founders' => $founders,
            // Štítky se jmény na společné fotce. Na fotce stojí Tom vlevo
            // a Pavel vpravo, tedy obráceně než pořadí zakladatelů, a každý
            // si nese svou barvu (první v pořadí krémovou, druhý cihlovou).
            'photoTags' => $founders
                ->values()
                ->map(fn (Founder $founder, int $index): array => [
                    'name' => $founder->name,
                    'brick' => $index > 0,
                ])
                ->reverse()
                ->values(),
            // Kroužky s Pavlem a Tomem v úvodu. Kdo fotku nemá, v úvodu chybí.
            'heroPortraits' => $founders
                ->map(fn (Founder $founder): ?array => $founder->portraitImage())
                ->filter()
                ->values(),
            // Společná fotka je jedna, ale nahrává se u kteréhokoliv zakladatele —
            // vezmeme první, která existuje.
            'foundersPhoto' => $founders
                ->map(fn (Founder $founder): ?array => $founder->photoImage(
                    'Zakladatelé '.$founders->pluck('name')->join(' a '),
                ))
                ->filter()
                ->first(),
        ]);
    }

    /**
     * Nejnovější projekt za sekcí se dvěma situacemi. Posuvník „před a po", když ho reference
     * má, jinak její první obrázek. Bez obrázku nemá sekce smysl a vynechá se.
     *
     * @return array{case: CaseStudy, comparison: ?array<string, mixed>, image: ?array<string, mixed>}|null
     */
    private function latestCase(HomeSettings $home): ?array
    {
        $case = $home->latest_case_id
            ? CaseStudy::published()->find($home->latest_case_id)
            : null;

        if (! $case) {
            return null;
        }

        $comparison = $case->beforeAfter();
        $image = $comparison ? null : ($case->galleryImages()->first() ?? $case->thumbImage());

        if (! $comparison && ! $image) {
            return null;
        }

        return ['case' => $case, 'comparison' => $comparison, 'image' => $image];
    }

    /**
     * Příklady „Kolik hodin vlastně potřebuji?". Stejně jako u karet ceníku
     * doplní chybějící klíče; příklad bez rozsahu hodin se vynechá.
     *
     * @return Collection<int, array{hours: string, price: ?string, for: ?string, items: array<int, string>, after: ?string}>
     */
    private function pricingExamples(HomeSettings $home): Collection
    {
        return collect($home->pricing_examples)
            ->map(fn (array $example): array => [
                'hours' => trim((string) ($example['hours'] ?? '')),
                'price' => filled($example['price'] ?? null) ? $example['price'] : null,
                'for' => filled($example['for'] ?? null) ? $example['for'] : null,
                'items' => collect($example['items'] ?? [])->filter(fn ($item) => is_string($item) && filled($item))->values()->all(),
                'after' => filled($example['after'] ?? null) ? $example['after'] : null,
            ])
            ->filter(fn (array $example): bool => $example['hours'] !== '')
            ->values();
    }

    /**
     * Karty ceníku s doplněnými klíči. Repeater ve Filamentu nevyplněná pole
     * někdy vůbec neuloží a prázdný řetězec má na webu znamenat „nic".
     * Karta bez názvu nebo ceny se vynechá celá.
     *
     * @return Collection<int, array{name: string, when: ?string, text: ?string, price: string, price_unit: ?string, price_note: ?string, highlight: bool}>
     */
    private function pricingPlans(HomeSettings $home): Collection
    {
        return collect($home->pricing_plans)
            ->map(fn (array $plan): array => [
                'name' => trim((string) ($plan['name'] ?? '')),
                'when' => filled($plan['when'] ?? null) ? $plan['when'] : null,
                'text' => filled($plan['text'] ?? null) ? $plan['text'] : null,
                'price' => trim((string) ($plan['price'] ?? '')),
                'price_unit' => filled($plan['price_unit'] ?? null) ? $plan['price_unit'] : null,
                'price_note' => filled($plan['price_note'] ?? null) ? $plan['price_note'] : null,
                'highlight' => (bool) ($plan['highlight'] ?? false),
            ])
            ->filter(fn (array $plan): bool => $plan['name'] !== '' && $plan['price'] !== '')
            ->values();
    }
}
