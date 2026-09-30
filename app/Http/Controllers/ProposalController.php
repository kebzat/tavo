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

        return view('proposal.show', [
            'proposal' => $proposal,
            'tiles' => $proposal->highlightTiles(),
            'findings' => $proposal->findingItems(),
            'recommendations' => $proposal->recommendationItems(),
            'steps' => $proposal->stepGroups(),
            'examples' => $proposal->examplesByPlacement(),
            'principles' => $proposal->principleItems(),
            'links' => $this->auditLinks($proposal),
        ]);
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
