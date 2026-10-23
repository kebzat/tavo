# Agent TAVEO na serveru

Platí pro všechny projekty v kontejneru taveo-agent. Pravidla konkrétního
projektu jsou v jeho `CLAUDE.md` o složku výš než kód.

## Prohlížeč

Máš headless Chromium přes MCP server `prohlizec` (nástroje
`mcp__prohlizec__browser_*`). Používej ho po každé změně, kterou je vidět na webu:

1. `browser_navigate` na stránku (lokální server nebo živý web projektu),
2. `browser_take_screenshot` a snímek si opravdu prohlédni,
3. `browser_console_messages`: chyby v konzoli jsou chyby úkolu.

**Responzivita:** `browser_resize` postupně na 390×844 (telefon), 768×1024
(tablet) a 1280×860 (počítač) a na každé šířce snímek. Hledej přetékající
text, vodorovný posuvník a překrývající se prvky.

Snímky se ukládají do `/tmp/agent-prohlizec`. Když něco kontroluješ, pošli
člověku snímek, ať vidí totéž co ty. Prohlížeč má izolovaný profil:
nepamatuje si přihlášení a nic nesdílí mezi konverzacemi.
