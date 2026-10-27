<?php

namespace App\Support\Automations;

use App\Models\Automation;
use App\Support\Vault\LoginBroker;
use App\Support\Vault\VaultException;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;

/**
 * Nahraje CSV do importu objednávek v Upgates a tím ho spustí. Import objednávek
 * Upgates z adresy pravidelně stahovat neumí, jde jen „Zopakovat s novým souborem“
 * u existujícího importu. Web se přihlásí přístupem z trezoru (druh Upgates)
 * a pošle totéž co administrace: POST `?do=runAgainWithNewFile` s `file`
 * a `import_id`, odpověď `{"success": true}` (ověřeno 27. 10. 2026).
 */
class UpgatesImportUpload
{
    private const PATH = '/setup/export-import/default/orders/?do=runAgainWithNewFile';

    public function __construct(private LoginBroker $broker) {}

    public function upload(Automation $automation, string $csv): void
    {
        $entry = $automation->upgatesEntry();
        $importId = (int) $automation->setting('upgates_import_id');

        if ($entry === null || $importId < 1) {
            throw new AutomationException('U automatizace chybí přihlášení do Upgates z trezoru nebo číslo importu objednávek, změny stavů se do Upgates nenahrají.');
        }

        try {
            $cookies = $this->broker->login($entry);
        } catch (VaultException $e) {
            $entry->record('automatizace', 'login_failed', $e->getMessage());

            throw new AutomationException('Přihlášení do Upgates se nepovedlo: '.$e->getMessage());
        }

        $jar = new CookieJar;
        foreach ($cookies as $cookie) {
            $jar->setCookie(new SetCookie(['Name' => $cookie['name'], 'Value' => $cookie['value'], 'Domain' => $cookie['domain'], 'Path' => $cookie['path'] ?: '/', 'Secure' => $cookie['secure']]));
        }

        $response = Http::withOptions(['cookies' => $jar])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->acceptJson()
            ->timeout(60)
            ->attach('file', $csv, 'taveo-automatizace.csv', ['Content-Type' => 'text/csv'])
            ->post($this->origin($entry->loginUrl()).self::PATH, ['import_id' => (string) $importId]);

        if (! $response->successful() || $response->json('success') !== true) {
            throw new AutomationException('Upgates soubor se změnami stavů nepřijal (import #'.$importId.', HTTP '.$response->status().'). Zkontroluj, že import v Upgates existuje.');
        }

        $entry->record('automatizace', 'upgates_import', 'Import objednávek #'.$importId);
    }

    private function origin(?string $url): string
    {
        $parts = parse_url((string) $url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }
}
