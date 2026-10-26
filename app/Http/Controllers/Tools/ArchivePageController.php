<?php

namespace App\Http\Controllers\Tools;

use App\Models\KnowledgeSource;
use App\Support\Agent\SandboxedPage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stálá stránka z archivu klienta: HTML dokument (rozbor, plán od agenta)
 * na čitelné adrese s náhodnou částí, jako přehled klienta. Otevře ji každý,
 * kdo zná odkaz, i bez přihlášení. Se smazáním dokumentu zmizí.
 */
class ArchivePageController
{
    public function __invoke(string $key): Response
    {
        $source = KnowledgeSource::query()->where('page_key', $key)->firstOrFail();
        $file = $source->pageFile();
        $html = $file ? Storage::disk('local')->get($file['path']) : null;

        abort_if(blank($html), 404);

        return SandboxedPage::response($html);
    }
}
