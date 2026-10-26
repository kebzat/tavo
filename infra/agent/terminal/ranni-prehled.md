Připrav ranní přehled pro klienty TAVEO. Řiď se CLAUDE.md v této složce.

Postup:
1. `taveo klienti`. Zpracuj jen klienty, kteří mají `reklamy: true` nebo `karta_vyplnena: true`.
2. Pro každého zvlášť, jednoho po druhém: `taveo klient <slug>`, pak přehled uložit takhle (heredoc přímo za
   příkazem, ne přes rouru ani soubor, jinak ho oprávnění nepustí):
   taveo prehled <slug> <<'EOF'
   …Markdown…
   EOF
   Nejdřív dokonči jednoho klienta, pak teprve další.
3. Když má klient v `reporty_k_doplneni` koncept reportu, napiš k němu tři části z čísel za období reportu
   (pole `cisla` a `kampane` u reportu) a ulož každou zvlášť stejným způsobem:
   taveo report <id> fakta <<'EOF' … EOF, taveo report <id> priciny <<'EOF' … EOF, taveo report <id> doporuceni <<'EOF' … EOF
   Fakta jen z čísel, příčiny jako hypotézy (opři je o `zasahy_v_obdobi`, a když příčinu nevíš, napiš to),
   doporučení jako návrhy ke schválení. Komentář Pavla neopakuj. Report za skupinu kampaní hodnoť proti jejímu cíli (`cil`).

Přehled jednoho klienta, nejvýš 200 slov, přesně tyhle nadpisy (### …):
### Co se změnilo
Jen podstatné změny proti minulému týdnu, se zdrojem a obdobím. Když se nic podstatného nezměnilo, napiš to jednou větou.
### Spolehlivost dat
Začni `reklamy.stav_dat` (do kdy jsou data kompletní). Chyby stahování, upozornění na měření, nefunkční cílové stránky,
chybějící data. Když data nejsou kompletní, nekomentuj propady ani čerpání. Když je vše v pořádku, jedna věta.
### Co vyžaduje pozornost
Živá upozornění seřazená podle závažnosti (u skupin kampaní uveď skupinu), termíny z `terminy` (končící licence,
akce, čekání na podklady), úkoly po termínu, zamítnuté reklamy, čerpání rozpočtu, kampaně čekající na zařazení.
### Navržený další krok (ke schválení)
Nejvýš tři konkrétní kroky pro Pavla. Kampaně ani rozpočty neměníš, jen navrhuješ.

Pravidla: nic nepočítej, cituj čísla z `taveo klient`. Chybějící data nejsou nula. Neopakuj včerejší přehled
(`vcerejsi_prehled`), piš jen co je nové. Když je karta klienta prázdná, uveď na konci jednou větou, co v ní chybí.
Nikomu nic neposílej. Na konci vypiš jednou řádkou, u kolika klientů jsi přehled uložil.
