<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\Checklist;
use Illuminate\Contracts\View\View;

/**
 * Sdílený audit klienta. Odkaz chrání jen náhodný token, stejně jako
 * u checklistu, a stránka se nesmí indexovat (noindex v layoutu a robots.txt).
 */
class AuditController extends Controller
{
    public function __invoke(string $token): View
    {
        $audit = Audit::query()
            ->public()
            ->where('public_token', $token)
            ->with('client')
            ->firstOrFail();

        $rendered = $audit->rendered();

        return view('audit.show', [
            'audit' => $audit,
            'html' => $rendered['html'],
            'toc' => $rendered['toc'],
            'tiles' => $audit->highlightTiles(),
            'links' => $this->checklistLinks($audit),
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
