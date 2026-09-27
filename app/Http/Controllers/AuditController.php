<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Checklist;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Sdílený audit klienta. Odkaz chrání jen náhodný token, stejně jako
 * u checklistu, a stránka se nesmí indexovat (noindex v layoutu a robots.txt).
 */
class AuditController extends Controller
{
    public function __invoke(Request $request, string $token): View
    {
        // Přihlášený správce otevře i audit, který ještě nesdílíme, aby ho
        // mohl před odesláním zkontrolovat přesně tak, jak ho uvidí klient.
        $audit = Audit::query()
            ->when($request->user() === null, fn ($query) => $query->public())
            ->where('public_token', $token)
            ->with('client.crmCompany')
            ->firstOrFail();

        // Přihlášený správce si audit kontroluje, to otevření klientem není.
        if ($request->user() === null) {
            $audit->recordView($request->userAgent());
        }

        $rendered = $audit->rendered();

        return view('audit.show', [
            'audit' => $audit,
            'html' => $rendered['html'],
            'toc' => $rendered['toc'],
            'locked' => $rendered['locked'],
            'tiles' => $audit->highlightTiles(),
            // Checklist je návod, jak nálezy opravit. V omezeném režimu
            // by prozradil to, co má zůstat na hovor.
            'links' => $audit->is_teaser ? [] : $this->checklistLinks($audit),
        ]);
    }

    /**
     * Sdílené checklisty téhož klienta jako tlačítka v hlavičce.
     *
     * @return list<array{label: string, url: string}>
     */
    private function checklistLinks(Audit $audit): array
    {
        if (! $audit->client) {
            return [];
        }

        return $audit->client->checklists()
            ->forClients()
            ->where('is_public', true)
            ->ordered()
            ->get()
            ->map(fn (Checklist $checklist): array => [
                'label' => 'Checklist úkolů · hotovo '.$checklist->progress()['percent'].' %',
                'url' => $checklist->publicUrl(),
            ])
            ->all();
    }
}
