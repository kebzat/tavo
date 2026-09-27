---
name: audit-eshopu
description: Podrobný audit e-shopu pro CRM Taveo (styl auditu Světa Cejlonu), napsaný v Claude Code na předplatném místo placeného API a nahraný rovnou do ostrého CRM. Použij, když Tomáš napíše /audit-eshopu <doména> nebo chce audit e-shopu pro oslovení.
---

# Audit e-shopu do CRM

Píšeš audit e-shopu, který Tom a Pavel z TAVEO pošlou majiteli jako důvod se
s nimi bavit. Vzor stylu i hloubky je
[database/content/audits/svet-cejlonu-2026-09-24.md](../../../database/content/audits/svet-cejlonu-2026-09-24.md)
(bez kapitoly „Cenová nabídka"). Pravidla pravdivosti, tónu a formátu jsou
v `DeepAuditPrompt::system()` v
[app/Support/Crm/Ai/DeepAuditPrompt.php](../../../app/Support/Crm/Ai/DeepAuditPrompt.php)
a platí tady stejně. Texty navíc proti skillu `tavo-copy`.

Argument je doména, třeba `melichar.cz`.

## Postup

1. **Změř web**

   ```bash
   PAGESPEED_ENABLED=false ./bin/art crm:measure <doména> > /tmp/audit-<doména>.json
   ```

   Dostaneš měření (platforma, měřicí kódy, sitemapa, robots.txt, AI roboti,
   titulky, odkazy z úvodní stránky) a nálezy. Lokálně spouštěj s
   `PAGESPEED_ENABLED=false`, PageSpeed bez klíče stejně skončí na limitu.
   Když je firma v ostrém CRM, vezmi PageSpeed odtamtud:

   ```bash
   curl -sS "https://taveo.cz/nastroje/api/companies/scout?website=<doména>" \
     -H "X-Crm-Token: $(grep '^TAVEO_PROD_CRM_TOKEN=' .env | cut -d= -f2-)"
   ```

   (`scout.measurements.pagespeed`). Když tam není, rychlost v auditu
   vynech a napiš to do „Co zvenku nevidíme".

2. **Projdi web v prohlížeči** (Playwright MCP, nebo WebFetch, když je
   prohlížeč obsazený). Úvodní stránka, 2 až 3 kategorie, 2 produkty, košík,
   doprava, kontakt, obchodní podmínky. Na mobilu (390 × 844) udělej snímek
   úvodu, kategorie a produktu a podívej se na ně. Hledej totéž co vzor:
   titulky a popisky, texty kategorií, produkty, fotky, doprava a vrácení,
   recenze, důvěryhodnost, zbytky ukázkového obsahu, překlepy, strukturovaná
   data, co brzdí nákup na mobilu. Když se hodí, projdi sitemapu a spočítej
   typy adres jako ve vzoru.

3. **Napiš audit** do `/tmp/audit-<doména>.md` přesně ve formátu z
   `DeepAuditPrompt::system()`: Shrnutí se stavem podle oblastí, Nejdůležitější
   nález, řádek `::: zámek`, Co udělat nejdřív, kapitoly s nálezy, Co zvenku
   nevidíme. 10 až 20 nálezů, u každého adresa nebo citace. Bez ceníku.
   Nic, co jsi neviděl nebo nezměřil.

4. **Zkontroluj** text: `grep -c '—'` musí vrátit 0, čísla v textu musí
   sedět s měřením, žádné sliby tržeb.

5. **Nahraj do ostrého CRM.** Token je v lokálním `.env` jako
   `TAVEO_PROD_CRM_TOKEN` (hodnota `CRM_IMPORT_TOKEN` ze serveru). Připrav
   `/tmp/audit-<doména>-payload.json`:

   ```json
   {
     "website": "<doména>",
     "name": "<název firmy>",
     "body": "<celý Markdown>",
     "highlights": [{"value": "34 / 100", "label": "rychlost na mobilu podle PageSpeed"}],
     "tasks": [{"area": "Rychlost", "task": "Zmenšit obrázky v menu", "fix": "…", "priority": "must"}]
   }
   ```

   `highlights` 3 až 4 čísla, která v auditu opravdu jsou. `tasks` jeden úkol
   na nález, `priority` must, should nebo nice. JSON sestav skriptem
   (`python3 -c 'import json…'`), ne ručně, ať sedí escapování.

   ```bash
   curl -sS -X POST https://taveo.cz/nastroje/api/audits/import \
     -H "Content-Type: application/json" \
     -H "X-Crm-Token: $(grep '^TAVEO_PROD_CRM_TOKEN=' .env | cut -d= -f2-)" \
     --data @/tmp/audit-<doména>-payload.json
   ```

   Odpověď obsahuje `edit_url`. Audit vznikne neveřejný a v omezeném režimu,
   firma se v CRM založí, když tam ještě není.

   Když token v `.env` chybí, nic nenahrávej. Řekni Tomášovi, ať ho doplní,
   a nech mu Markdown v `/tmp/audit-<doména>.md`.

6. **Předej odkaz** `edit_url` a tři nejsilnější nálezy v pár větách.
   Sdílení se zapíná ručně po kontrole.
