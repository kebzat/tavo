#!/usr/bin/env python3
"""
Zpracuje požadavky, které služba v kontejneru zapsala do /srv/agent/fronta.
Spouští ho systemd (taveo-agent-fronta.path), jakmile se v pozadavky/ něco
objeví. Výsledky jdou do vysledky/, jinak by se hlídání spouštělo dokola.

Kontejner nemá práva roota a nemá vidět databáze ani ostatní weby. Co je
potřeba udělat na serveru, proto jen popíše, a tady se to pečlivě ověří:

  zaloha   snímek souborů a záloha databáze projektu (před novým vláknem)
  pridat   nový projekt typu git nebo prazdny
  aktualizovat  služba agenta z main na GitHubu (po Zveřejnit v nástrojích)
  obnovit  živý web do stavu před časem (ručně, agent obnovit)
  nasadit  vydání ze služby agenta rovnou na web (tlačítko Zveřejnit, jen taveo)
  nasadit-zpet  web na verzi před posledním nasazením (tlačítko Vrátit)
  kopie-db  databáze kopie živého webu znovu z živé
  nasadit-kopii  kopie živého webu na živý web (tlačítko Zveřejnit)

Web na serveru (`server`) se z administrace přidat nedá. Dává agentovi
přístup k živému webu klienta, takže zůstává jen pro roota přes SSH.

Claude tady nikdy neběží (sezení spouští jen služba v kontejneru). Každý
příkaz `agent` má limit FRONTA_LIMIT_MIN (z /srv/agent/terminal.env, výchozí
10 min) a běží ve vlastní skupině procesů: po limitu skončí i mysqldump, rsync
a ostatní, co spustil, ne jen samotný skript.
"""

import json
import os
import re
import signal
import subprocess
import time
from pathlib import Path

POZADAVKY = Path('/srv/agent/fronta/pozadavky')
VYSLEDKY = Path('/srv/agent/fronta/vysledky')
SEZNAM = Path('/srv/agent/projekty.tsv')
NAZEV = re.compile(r'^[a-z0-9][a-z0-9-]{1,40}$')
LIMIT_S = float(os.environ.get('FRONTA_LIMIT_MIN') or 10) * 60
GITHUB = re.compile(r'^(git@github\.com:|https://github\.com/)[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+?(\.git)?$')


def projekty():
    if not SEZNAM.exists():
        return set()
    return {radek.split('\t')[0] for radek in SEZNAM.read_text().splitlines() if radek}


def ukoncit_skupinu(beh):
    """SIGTERM celé skupině procesů příkazu, po 10 s SIGKILL tomu, co zbylo."""
    for sig, cekat in ((signal.SIGTERM, 10), (signal.SIGKILL, 0)):
        try:
            os.killpg(beh.pid, sig)
        except ProcessLookupError:
            return
        for _ in range(cekat * 4):
            beh.poll()  # skončený příkaz jinak visí jako zombie a skupina „žije“
            try:
                os.killpg(beh.pid, 0)
            except ProcessLookupError:
                return
            time.sleep(0.25)


def agent(*args):
    beh = subprocess.Popen(['/usr/local/bin/agent', *args], stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                           text=True, start_new_session=True)
    try:
        vystup, _ = beh.communicate(timeout=LIMIT_S)
    except subprocess.TimeoutExpired:
        # Skript mohl skončit a výstup drží jen to, co spustil na pozadí.
        dobehl = beh.poll() is not None
        ukoncit_skupinu(beh)
        vystup, _ = beh.communicate()
        if dobehl:
            return beh.returncode == 0, (vystup or '').strip()
        return False, f'{(vystup or "").strip()}\nNestihlo se do {LIMIT_S / 60:g} min, ukončeno i se vším, co spustilo.'.strip()

    # I po řádném konci: co zůstalo na pozadí ve skupině, skončí.
    ukoncit_skupinu(beh)
    return beh.returncode == 0, (vystup or '').strip()


def zpracovat(pozadavek):
    akce = pozadavek.get('akce')
    nazev = str(pozadavek.get('projekt') or '')

    if not NAZEV.match(nazev):
        return False, 'Název projektu může mít jen malá písmena, číslice a pomlčky.'

    if akce == 'zaloha':
        if nazev not in projekty():
            return False, f'Projekt {nazev} neexistuje.'
        return agent('zaloha', nazev)

    if akce == 'aktualizovat':
        # Bez parametrů: agent.sh vezme server.js jen z main na GitHubu.
        return agent('aktualizovat')

    if akce == 'nasadit':
        strom = str(pozadavek.get('strom') or '')
        if not re.match(r'^[0-9a-f]{40}$', strom):
            return False, 'Neplatné vydání.'
        return agent('nasadit', nazev, strom)

    if akce == 'nasadit-zpet':
        return agent('nasadit-zpet', nazev)

    if akce in ('kopie-db', 'nasadit-kopii'):
        if nazev not in projekty():
            return False, f'Projekt {nazev} neexistuje.'
        return agent(akce, nazev)

    if akce == 'obnovit':
        cas = pozadavek.get('cas')
        if not isinstance(cas, int) or cas <= 0:
            return False, 'Neplatný čas, do kterého se vrací.'
        if nazev not in projekty():
            return False, f'Projekt {nazev} neexistuje.'
        args = ['obnovit', nazev, str(cas)]
        if pozadavek.get('databaze') is True:
            args.append('databaze')
        return agent(*args)

    if akce == 'pridat':
        typ = pozadavek.get('typ')
        zdroj = str(pozadavek.get('zdroj') or '').strip()
        if typ == 'prazdny':
            return agent('pridat', nazev, 'prazdny')
        if typ == 'git':
            if not GITHUB.match(zdroj):
                return False, 'Adresa repozitáře musí být z GitHubu, např. git@github.com:ucet/repozitar.git'
            return agent('pridat', nazev, 'git', zdroj)
        return False, 'Z administrace jde přidat jen repozitář nebo projekt bez kódu. Web na serveru připojí root přes SSH.'

    return False, 'Neznámý požadavek.'


def main():
    for soubor in sorted(POZADAVKY.glob('pozadavek-*.json')):
        id_ = soubor.stem.removeprefix('pozadavek-')
        try:
            pozadavek = json.loads(soubor.read_text())
            ok, vystup = zpracovat(pozadavek)
        except Exception as chyba:  # noqa: BLE001 - výsledek musí dojít vždy
            ok, vystup = False, f'Chyba při zpracování: {chyba}'
        finally:
            soubor.unlink(missing_ok=True)

        cil = VYSLEDKY / f'vysledek-{id_}.json'
        cil.write_text(json.dumps({'ok': ok, 'vystup': vystup[-4000:]}, ensure_ascii=False))
        os.chown(cil, 2100, 2100)


if __name__ == '__main__':
    main()
