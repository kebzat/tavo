<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Support\Automations\ImportCsv;
use Illuminate\Http\Response;

/**
 * CSV čekajících změn stavů na adrese s tokenem, bez přihlášení. Upgates
 * objednávky z adresy pravidelně importovat neumí (jen jednorázově ze souboru),
 * soubor proto nahrává web sám (UpgatesImportUpload). Adresa zůstává pro kontrolu
 * a pro ruční import, kdyby nahrávání selhávalo.
 */
class AutomationCsvController extends Controller
{
    public const HEADER = ImportCsv::HEADER;

    public function __invoke(string $token): Response
    {
        $automation = Automation::query()->where('import_token', $token)->first();

        abort_if($automation === null || ! hash_equals($automation->import_token, $token), 404);

        $rows = ImportCsv::rows($automation);
        ImportCsv::markExported($rows);

        return response(ImportCsv::build($rows), 200, [
            'Content-Type' => 'text/csv; charset=windows-1250',
            'Content-Disposition' => 'inline; filename="upgates-import.csv"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
