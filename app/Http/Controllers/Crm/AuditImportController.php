<?php

namespace App\Http\Controllers\Crm;

use App\Enums\Crm\CompanySegment;
use App\Enums\Crm\CompanySource;
use App\Enums\Crm\CompanyStatus;
use App\Filament\Tools\Resources\Audits\Pages\EditAudit;
use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Support\Crm\AuditFromCompany;
use App\Support\Crm\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Příjem hotového auditu z Claude Code (skill /audit-eshopu). Audit se
 * napíše lokálně na předplatné a sem se jen nahraje. Vznikne stejně jako
 * z tlačítka v CRM: neveřejný, v omezeném režimu, s checklistem úkolů.
 */
class AuditImportController extends Controller
{
    public function __invoke(Request $request, AuditFromCompany $builder): JsonResponse
    {
        $data = $request->validate([
            'website' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:1000'],
            'body' => ['required', 'string', 'min:200'],
            'highlights' => ['nullable', 'array', 'max:4'],
            'highlights.*.value' => ['required', 'string', 'max:40'],
            'highlights.*.label' => ['nullable', 'string', 'max:120'],
            'tasks' => ['nullable', 'array', 'max:60'],
            'tasks.*.area' => ['required', 'string', 'max:80'],
            'tasks.*.task' => ['required', 'string', 'max:250'],
            'tasks.*.fix' => ['nullable', 'string', 'max:2000'],
            'tasks.*.priority' => ['nullable', 'in:must,should,nice'],
        ]);

        $domain = Domain::normalize($data['website']);
        abort_if($domain === null, 422, 'Neplatná adresa webu.');

        $company = Company::where('domain', $domain)->first() ?? Company::create([
            'name' => $data['name'] ?? $domain,
            'website' => $data['website'],
            'segment' => CompanySegment::Eshop,
            'source' => CompanySource::Research,
            'status' => CompanyStatus::New,
        ]);

        $audit = $builder->createFromDraft($company, [
            'title' => $data['title'] ?? 'Audit e-shopu '.$domain,
            'intro' => $data['intro'] ?? null,
            'body' => str_replace([' — ', '—'], [', ', ', '], $data['body']),
            'highlights' => $data['highlights'] ?? [],
            'tasks' => collect($data['tasks'] ?? [])->map(fn (array $t) => [
                'area' => $t['area'],
                'task' => $t['task'],
                'fix' => $t['fix'] ?? '',
                'priority' => $t['priority'] ?? 'should',
            ])->all(),
        ], 'Napsal Claude Code na předplatné '.now()->format('j. n. Y H:i').'.');

        return response()->json([
            'audit' => $audit->getKey(),
            'edit_url' => EditAudit::getUrl(['record' => $audit], panel: 'tools'),
            'company' => $company->getKey(),
            'lock_marker' => Str::contains($audit->body, '::: zámek'),
        ]);
    }
}
