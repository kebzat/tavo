<?php

namespace App\Support\Vault;

use App\Models\VaultEntry;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Přihlásí se za agenta do administrace (WordPress, Shoptet, Upgates, jiný
 * web) a vrátí jen cookies přihlášení. Agent si je vloží do svého prohlížeče,
 * heslo nikdy nevidí. Formulář se hledá obecně: první formulář s polem pro
 * heslo, skrytá pole zůstanou, jméno a heslo se doplní. Když web chce kód 2FA
 * a v trezoru je uložený, doplní ho taky. Token CSRF, který web do formuláře
 * vkládá až skriptem (Shoptet), se doplní ze stránky.
 *
 * Cookies po přihlášení si trezor u přístupu pamatuje (šifrovaně). Když
 * relace ještě platí, vrátí ji bez nového přihlášení. Jinak se přihlásí
 * znovu, ale s trvalými cookies z minula, takže web pozná známé zařízení
 * (Shoptet: `fingerprint`, půl roku) a neposílá e-mail „Byli jste to Vy?“.
 *
 * Ověření „nejsem robot“ (Turnstile, reCAPTCHA, hCaptcha) projít neumí,
 * to je jeho smysl. Pak hlásí, co s tím (aplikační heslo, výjimka pro IP).
 */
class LoginBroker
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private const CAPTCHA = '~cf-turnstile|challenges\.cloudflare\.com/turnstile|g-recaptcha|grecaptcha|h-captcha|hcaptcha\.com~i';

    /** Shoptet má token jen ve skriptu (`shoptet.csrf.token = "…"`) a pole `__csrf__` přidá až při odeslání. */
    private const SHOPTET_CSRF = '~shoptet\.csrf\.token\s*=\s*["\']([^"\']+)["\']~';

    /** Prošlo poslední `login()` uloženou relací, bez nového přihlášení? */
    public bool $resumed = false;

    /**
     * `$resume = false` se přihlásí vždy znovu (Vyzkoušet má ověřit heslo).
     *
     * @return list<array{name: string, value: string, domain: string, path: string, expires: int, httpOnly: bool, secure: bool, sameSite: string}>
     */
    public function login(VaultEntry $entry, bool $resume = true): array
    {
        $this->resumed = false;
        $url = $entry->loginUrl();

        if (! $url || blank($entry->secret)) {
            throw new VaultException('U přístupu chybí adresa přihlášení nebo heslo.');
        }

        $remembered = $entry->login_cookies ?? [];

        if ($resume && $remembered !== [] && $this->sessionValid($entry, $jar = new CookieJar(false, $remembered))) {
            $this->resumed = true;

            return $this->remember($entry, $jar);
        }

        // Z minula jen trvalé cookies (známé zařízení), stará relace k novému přihlášení nepatří.
        $jar = new CookieJar(false, $remembered);
        $jar->clearSessionCookies();
        $known = count($jar) > 0;
        $page = $this->request($jar)->get($url);

        // Trvalá cookie „zapamatovat přihlášení“ může formulář přeskočit, pak načisto.
        if ($known && $this->findForm($page, 'password') === null) {
            $jar = new CookieJar;
            $page = $this->request($jar)->get($url);
        }

        if ($page->status() >= 400 && $page->status() !== 401) {
            throw new VaultException('Adresa přihlášení '.$url.' vrací chybu (HTTP '.$page->status().'). Zkontroluj v trezoru adresu webu nebo přihlášení.');
        }
        $form = $this->findForm($page, 'password');

        if ($form === null) {
            throw new VaultException('Na '.$url.' se nenašel přihlašovací formulář. Zkontroluj v trezoru adresu přihlášení.');
        }

        // Jen ve formuláři: plugin s výjimkou pro IP serveru pole vynechá, ale skript ochrany na stránku vloží dál.
        if (preg_match(self::CAPTCHA, $form['html'])) {
            throw new VaultException(
                'Přihlášení chrání ověření „nejsem robot“ (Turnstile, reCAPTCHA), přes formulář se agent nepřihlásí. '
                .'U WordPressu ulož aplikační heslo (agent pak jde přes API), případně v nastavení ochrany povol IP serveru TAVEO.'
            );
        }

        $fields = $this->withScriptToken($page, $form['fields']);
        $fields[$form['password']] = (string) $entry->secret;

        if ($form['username'] !== null) {
            $fields[$form['username']] = (string) $entry->username;
        }

        $response = $this->submit($jar, $form, $fields, $this->urlOf($page, $url));

        // Druhý krok: kód z aplikace (jedno textové pole, žádné heslo).
        $codeForm = $this->findForm($response, 'code');

        if ($codeForm !== null) {
            $code = $entry->totpCode();

            if ($code === null) {
                throw new VaultException('Web chce po přihlášení kód 2FA. Ulož v trezoru tajný klíč 2FA (při zapínání 2FA se ukáže pod QR kódem).');
            }

            $response = $this->submit($jar, $codeForm, [...$this->withScriptToken($response, $codeForm['fields']), $codeForm['code'] => $code], $this->urlOf($response, $url));
        }

        if ($response->status() >= 400 || $this->findForm($response, 'password') !== null) {
            // Neviditelné ověření (reCAPTCHA v3) ve formuláři pole nemá, poznat jde jen podle skriptu na stránce.
            throw new VaultException(match (true) {
                (bool) preg_match(self::CAPTCHA, $page->body()) => 'Přihlášení se nepovedlo. Na stránce je ochrana „nejsem robot“, nejspíš přihlášení odmítla ona, jinak zkontroluj uživatele a heslo v trezoru. '
                    .'U WordPressu ulož aplikační heslo (agent pak jde přes API), případně v nastavení ochrany povol IP serveru TAVEO.',
                $response->status() >= 400 => 'Web přihlášení odmítl (HTTP '.$response->status().'). Když jsou uživatel a heslo v trezoru správně, chce web nejspíš něco, co trezor neposílá.',
                default => 'Přihlášení se nepovedlo, web vrátil znovu přihlašovací formulář. Zkontroluj uživatele a heslo v trezoru.',
            });
        }

        return $this->remember($entry, $jar);
    }

    /** Platí relace z minula? Administrace pak neukáže formulář s heslem ani nepřesměruje na přihlášení. */
    private function sessionValid(VaultEntry $entry, CookieJar $jar): bool
    {
        $admin = $entry->adminUrl();

        if ($admin === null) {
            return false;
        }

        $page = $this->request($jar)->get($admin);

        return $page->successful()
            && ! str_contains(strtolower(parse_url($this->urlOf($page, $admin), PHP_URL_PATH) ?? ''), 'login')
            && $this->findForm($page, 'password') === null;
    }

    /** Uloží cookies k přístupu (šifrovaně, jen pro příští přihlášení) a vrátí je pro prohlížeč. */
    private function remember(VaultEntry $entry, CookieJar $jar): array
    {
        $entry->forceFill(['login_cookies' => $jar->toArray()])->saveQuietly();

        return $this->cookies($jar);
    }

    private function request(CookieJar $jar)
    {
        return Http::withOptions(['cookies' => $jar, 'allow_redirects' => ['max' => 10, 'track_redirects' => true]])
            ->withUserAgent(self::USER_AGENT)
            ->withHeaders(['Accept-Language' => 'cs,en;q=0.8'])
            ->timeout(30);
    }

    private function submit(CookieJar $jar, array $form, array $fields, string $pageUrl): Response
    {
        $action = $form['action'] === '' || str_starts_with($form['action'], '#') ? $pageUrl : $this->absolute($form['action'], $pageUrl);

        return $form['method'] === 'get'
            ? $this->request($jar)->get($action, $fields)
            : $this->request($jar)->withHeaders(['Referer' => $pageUrl])->asForm()->post($action, $fields);
    }

    /**
     * Formulář s heslem (`password`), nebo s jedním polem na kód (`code`).
     *
     * @return array{html: string, action: string, method: string, fields: array<string, string>, username: ?string, password: ?string, code: ?string}|null
     */
    private function findForm(Response $page, string $kind): ?array
    {
        $html = $page->body();

        if (trim($html) === '') {
            return null;
        }

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//form') as $form) {
            /** @var DOMElement $form */
            $fields = [];
            $username = $password = null;
            $texts = [];

            foreach ($xpath->query('.//input|.//select|.//textarea', $form) as $input) {
                /** @var DOMElement $input */
                $name = $input->getAttribute('name');
                $type = strtolower($input->getAttribute('type') ?: 'text');

                if ($name === '' || in_array($type, ['submit', 'button', 'image', 'reset', 'file'], true)) {
                    continue;
                }

                if (in_array($type, ['checkbox', 'radio'], true)) {
                    if ($input->hasAttribute('checked')) {
                        $fields[$name] = $input->getAttribute('value') ?: 'on';
                    }

                    continue;
                }

                if ($type === 'password') {
                    $password ??= $name;

                    continue;
                }

                $fields[$name] = $input->getAttribute('value');

                if (in_array($type, ['text', 'email', 'tel', 'number'], true) && ! $this->looksLikeHoneypot($input)) {
                    $texts[] = $name;
                }
            }

            if ($kind === 'password' && $password !== null) {
                $username = collect($texts)->first(fn (string $name): bool => (bool) preg_match('~log|user|mail|name|jmeno|login~i', $name)) ?? $texts[0] ?? null;
            } elseif (! ($kind === 'code' && $password === null && count($texts) === 1 && preg_match('~code|kod|otp|token|2fa|auth~i', $texts[0]))) {
                continue;
            }

            return [
                'html' => $dom->saveHTML($form),
                'action' => $form->getAttribute('action'),
                'method' => strtolower($form->getAttribute('method') ?: 'get'),
                'fields' => $fields,
                'username' => $username,
                'password' => $password,
                'code' => $kind === 'code' ? $texts[0] : null,
            ];
        }

        return null;
    }

    /** @param array<string, string> $fields */
    private function withScriptToken(Response $page, array $fields): array
    {
        if (! isset($fields['__csrf__']) && preg_match(self::SHOPTET_CSRF, $page->body(), $match)) {
            $fields['__csrf__'] = $match[1];
        }

        return $fields;
    }

    /** Pasti na roboty (honeypot) se vyplnit nesmí. */
    private function looksLikeHoneypot(DOMElement $input): bool
    {
        return (bool) preg_match('~nospam|honeypot|hp_|wpa_|website_url~i', $input->getAttribute('name').' '.$input->getAttribute('class'))
            || str_contains(strtolower($input->getAttribute('style')), 'display:none');
    }

    /** Kde stránka po přesměrováních skončila (formulář může mít relativní adresu). */
    private function urlOf(Response $page, string $fallback): string
    {
        $history = $page->header('X-Guzzle-Redirect-History');
        $history = $history !== '' ? array_map('trim', explode(',', $history)) : [];

        return end($history) ?: $fallback;
    }

    private function absolute(string $action, string $base): string
    {
        if (preg_match('~^https?://~i', $action)) {
            return $action;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($action, '/')) {
            return $origin.$action;
        }

        $dir = preg_replace('~/[^/]*$~', '/', $parts['path'] ?? '/');

        return $origin.$dir.$action;
    }

    private function cookies(CookieJar $jar): array
    {
        return collect($jar->toArray())
            ->map(fn (array $cookie): array => [
                'name' => $cookie['Name'],
                'value' => $cookie['Value'],
                'domain' => $cookie['Domain'],
                'path' => $cookie['Path'] ?: '/',
                'expires' => (int) ($cookie['Expires'] ?? -1) ?: -1,
                'httpOnly' => (bool) ($cookie['HttpOnly'] ?? false),
                'secure' => (bool) ($cookie['Secure'] ?? false),
                'sameSite' => 'Lax',
            ])
            ->values()
            ->all();
    }
}
