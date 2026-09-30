<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Proposal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Potenciální spolupráce. Adresa je čitelný slug firmy, stránka se nesmí
 * indexovat (noindex v layoutu a robots.txt).
 */
class ProposalController extends Controller
{
    public function __invoke(Request $request, string $slug): View
    {
        // Přihlášený správce otevře i stránku, kterou ještě nesdílíme, aby ji
        // mohl před odesláním zkontrolovat přesně tak, jak ji uvidí klient.
        $proposal = Proposal::query()
            ->when($request->user() === null, fn ($query) => $query->public())
            ->where('slug', $slug)
            ->with('client.crmCompany')
            ->firstOrFail();

        if ($request->user() === null) {
            $proposal->recordView($request->userAgent());
        }

        $sections = [
            'proposal' => $proposal,
            'tiles' => $proposal->highlightTiles(),
            'timeline' => $proposal->timelineItems(),
            'findings' => $proposal->findingItems(),
            'recommendations' => $proposal->recommendationItems(),
            'steps' => $proposal->stepGroups(),
            'examples' => $proposal->examplesByPlacement(),
            'experiences' => $proposal->experienceItems(),
            'principles' => $proposal->principleItems(),
            'links' => $this->auditLinks($proposal),
        ];

        return view('proposal.show', $sections + ['nav' => $this->nav($sections)]);
    }

    /**
     * Rozcestník: tlačítka v úvodu a lepivé menu. Jen sekce, které na stránce
     * opravdu jsou. „Co jsme objevili“ tu chybí schválně, začíná hned pod úvodem.
     *
     * @param  array<string, mixed>  $sections
     * @return list<array{id: string, label: string}>
     */
    private function nav(array $sections): array
    {
        $items = [
            'redesign' => [
                'label' => text('spoluprace.nav_redesign', 'Redesign webu', 'Potenciální spolupráce', 'Tlačítko v úvodu a položka menu'),
                'shown' => $sections['examples']['after_findings'] || $sections['timeline'],
            ],
            'doporuceni' => [
                'label' => text('spoluprace.nav_recommendations', 'Naše doporučení', 'Potenciální spolupráce', 'Tlačítko v úvodu a položka menu'),
                'shown' => (bool) $sections['recommendations'],
            ],
            'kroky' => [
                'label' => text('spoluprace.nav_steps', 'Akční plán', 'Potenciální spolupráce', 'Tlačítko v úvodu a položka menu'),
                'shown' => $sections['steps']['now'] || $sections['steps']['later'],
            ],
            'pristup' => [
                'label' => text('spoluprace.nav_principles', 'Obecné doporučení', 'Potenciální spolupráce', 'Tlačítko v úvodu a položka menu'),
                'shown' => (bool) $sections['principles'],
            ],
        ];

        return collect($items)
            ->filter(fn (array $item): bool => (bool) $item['shown'])
            ->map(fn (array $item, string $id): array => ['id' => $id, 'label' => $item['label']])
            ->values()
            ->all();
    }

    /**
     * Sdílené audity téhož klienta jako tlačítka v hlavičce.
     *
     * @return list<array{label: string, url: string}>
     */
    private function auditLinks(Proposal $proposal): array
    {
        if (! $proposal->client) {
            return [];
        }

        return $proposal->client->audits()
            ->public()
            ->orderByDesc('audited_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Audit $audit): array => [
                'label' => 'Přečíst celý audit',
                'url' => $audit->publicUrl(),
            ])
            ->all();
    }
}
