# Zabezpečení

Co chrání administraci (`/admin`), interní nástroje (`/nastroje`) a strojové
rozhraní pro agenty (`/nastroje/api`), a co je potřeba nastavit ručně mimo kód.

## Přihlášení: heslo + kód z aplikace

Oba panely vyžadují dvoufázové ověření (Filament, `AppAuthentication`).
Po hesle se zadává šestimístný kód z aplikace v telefonu (Google Authenticator,
Microsoft Authenticator, 1Password…).

- **První přihlášení po nasazení:** Filament pustí jen na stránku nastavení.
  Naskenuj QR kód, opiš kód a **ulož si záložní kódy** (třeba do 1Passwordu).
- **Ztracený telefon:** přihlas se záložním kódem (odkaz pod polem pro kód).
  Každý kód platí jednou. Pak v Profilu nastav novou aplikaci a vygeneruj
  nové záložní kódy.
- **Bez telefonu i záložních kódů** pomůže jen správce serveru:
  `php artisan tinker --execute 'App\Models\User::where("email","…")->first()->saveAppAuthenticationSecret(null);'`
  Účet si pak 2FA nastaví znovu při dalším přihlášení.
- Tajný klíč je v databázi šifrovaný `APP_KEY`, záložní kódy navíc hashované.
- Lokálně (`APP_ENV=local`) se 2FA nevyžaduje, ať vývoj nezdržuje telefon.
  V testech a na produkci ano.

Do `/nastroje` smí jen role Správce. Filament sám omezuje pokusy o přihlášení
i o zadání kódu.

## Tokeny agentů (`/nastroje/api`)

Každý agent (skill `/audit-eshopu`, automatizace, skript) má vlastní token:
**Nástroje → CRM → Přístupy agentů → Nový token.**

| Oprávnění | Endpoint |
|---|---|
| Zjistit, jestli firma už je v CRM | `GET companies/scout` |
| Zakládat firmy k proklepnutí | `POST companies/import` |
| Nahrávat audity | `POST audits/import` |
| Stahovat export pipeline | `GET export/pipeline` |

- Dávej jen to, co agent opravdu potřebuje. Agent, který čte cizí weby
  (audity), může dostat podvržené pokyny schované v obsahu stránky. S tokenem
  jen na audity pak nemůže nic přečíst ani nic dalšího změnit.
- Token se ukáže **jednou** při založení (začíná `tavo_`). V databázi je jen
  jeho SHA-256 otisk. Ztracený token se nezjišťuje, založí se nový.
- Token jde jen hlavičkou `X-Crm-Token` nebo `Authorization: Bearer`. V adrese
  (`?token=`) se nepřijímá, skončil by v logu serveru.
- **Zrušit** = okamžitě přestane platit. **Historie** ukazuje posledních sto
  volání (kdy, co, odkud, výsledek). Historie se maže po 90 dnech
  (`model:prune` v `routes/console.php`).
- Po deseti neplatných tokenech za minutu z jedné adresy rozhraní vrací 429.

**Starý společný token** `CRM_IMPORT_TOKEN` v `.env` zatím platí na všechno,
aby se agenti nerozbili při nasazení. Volání s ním se v historii nezobrazí
u žádného agenta. Nastavení CRM ukazuje červeně, že je pořád zapnutý.
Jakmile mají agenti vlastní tokeny, **smaž ho z `.env` na serveru**.

### Rozhraní agenta v kontejneru (`/nastroje/api/agent`)

Samostatné rozhraní pro kontejner taveo-agent (`VerifyAgentToken`). Má jeden
vlastní token `AGENT_API_TOKEN` (min. 32 znaků, jen `Authorization: Bearer`).
Bez něj vrací 404. Čte data klientů a zapisuje jen koncepty. Do Přístupů agentů
zatím nepatří. Při úniku se token mění v `.env` na serveru i v kontejneru.

## Hlavičky

`App\Http\Middleware\SecurityHeaders`, globálně pro web, panely i API:
HSTS (jen přes HTTPS, rok, bez subdomén), `X-Frame-Options: SAMEORIGIN`
+ `frame-ancestors 'self'`, `nosniff`, `Referrer-Policy:
strict-origin-when-cross-origin`, `Permissions-Policy` bez kamery, mikrofonu
a polohy. Plná CSP není: Filament a Livewire potřebují inline skripty.

## Ručně na serveru a na GitHubu (kód to nenastaví)

Po nasazení téhle verze:

1. **Přihlas se do `/nastroje` a nastav 2FA**, totéž Pavel. Ulož záložní kódy.
2. **Založ tokeny agentům** v Přístupech agentů a vyměň je v jejich `.env`
   (u `/audit-eshopu` je to `TAVEO_PROD_CRM_TOKEN` v lokálním `.env`).
3. **Smaž `CRM_IMPORT_TOKEN` z `.env` na serveru** a spusť `php artisan config:cache`
   (nebo Nastavení → Údržba → Obnovit cache).
4. **V `.env` na serveru** nastav `SESSION_ENCRYPT=true` a ověř
   `APP_ENV=production`, `APP_DEBUG=false`.
5. **Práva souborů:** `chmod 600 .env` a `storage/` jen pro uživatele webu.
   Server je sdílený s jinými weby, viz oprava záloh v `DbBackup`.
6. **GitHub → Settings → Branches → ochrana `main`:** jen přes pull request,
   zakázat force-push a mazání. Agenti pushují do `agent/*`, do `main` slučuje
   člověk.
7. **Deploy klíč** (SSH, kterým CI nasazuje) má být jen pro nasazení:
   v `authorized_keys` s `restrict` a bez přístupu jinam.
