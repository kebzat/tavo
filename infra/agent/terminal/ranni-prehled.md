Připrav ranní přehled pro klienty TAVEO. Řiď se CLAUDE.md v této složce.

Postup:
1. `taveo klienti`. Zpracuj jen klienty, kteří mají `reklamy: true` nebo `karta_vyplnena: true`.
2. Pro každého zvlášť, jednoho po druhém: `taveo klient <slug>`, pak `taveo prehled <slug>` s přehledem
   (Markdown přes heredoc). Nejdřív dokonči jednoho klienta, pak teprve další.
3. Když má klient v `reporty_k_doplneni` koncept reportu, připrav k němu JSON {"facts","causes","recommendations"}
   (Markdown v každém poli) a ulož `taveo report <id>`. Fakta jen z čísel, příčiny jako hypotézy.

Přehled jednoho klienta, nejvýš 200 slov, přesně tyhle nadpisy (### …):
### Co se změnilo
Jen podstatné změny proti minulému týdnu, se zdrojem a obdobím. Když se nic podstatného nezměnilo, napiš to jednou větou.
### Spolehlivost dat
Chyby stahování, upozornění na měření, nefunkční cílové stránky, chybějící data. Když je vše v pořádku, jedna věta.
### Co vyžaduje pozornost
Živá upozornění seřazená podle závažnosti, úkoly po termínu, zamítnuté reklamy, čerpání rozpočtu.
### Navržený další krok (ke schválení)
Nejvýš tři konkrétní kroky pro Pavla. Kampaně ani rozpočty neměníš, jen navrhuješ.

Pravidla: nic nepočítej, cituj čísla z `taveo klient`. Chybějící data nejsou nula. Neopakuj včerejší přehled
(`vcerejsi_prehled`), piš jen co je nové. Když je karta klienta prázdná, uveď na konci jednou větou, co v ní chybí.
Nikomu nic neposílej. Na konci vypiš jednou řádkou, u kolika klientů jsi přehled uložil.
