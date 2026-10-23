# co-agent-dela

Panel vedle chatu, ve kterém je vidět, co agent TAVEO právě dělá: každé volání
nástroje jako srozumitelný krok (čte soubor, spouští příkaz, prohlížeč otevírá
stránku), jeho stav a čas. Pod tím adresa, kterou agent naposledy otevřel
v prohlížeči (MCP server `prohlizec`), a poslední snímek obrazovky. Obrázek se
vykreslí v terminálech, které to umí (kitty, Ghostty, iTerm2), jinde je tam cesta.

Když agent sáhne po prohlížeči, nad vstupním polem se na dvě minuty ukáže pruh
s tlačítkem **Kouknout**. Panel jde otevřít kdykoli příkazem `/co-agent-dela`.

Zdroj je v repu taveo v `infra/agent/mody/co-agent-dela`.
