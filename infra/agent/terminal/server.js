'use strict';

/**
 * Služba v kontejneru taveo-agent. Nástroje TAVEO přes ni:
 *
 *   - čtou projekty a jejich konverzace s Claudem (běžící i uložené),
 *   - zakládají novou konverzaci nebo navazují na uloženou,
 *   - připojují terminál v prohlížeči (WebSocket přes LiteSpeed).
 *
 * Každý projekt má navíc okno s `claude remote-control` (serverový režim):
 * v aplikaci Claude (desktop, claude.ai/code, mobil) se pak dají zakládat
 * konverzace, které běží tady. Konverzace z nástrojů se do aplikace připojují
 * samy (remoteControlAtStartup v managed-settings.json).
 *
 * Běžící konverzace = okno tmuxu. Každé má ID konverzace Clauda (@session),
 * takže jde poznat, která uložená konverzace právě běží, a navázat na ni.
 *
 * API volá jen Laravel na stejném serveru (127.0.0.1) se sdíleným tajemstvím.
 * Prohlížeč dostane jen krátkou podepsanou vstupenku na jedno okno.
 *
 * Co potřebuje roota (záloha databáze, přidání projektu), řeší server: služba
 * zapíše požadavek do /fronta a počká na výsledek (viz agent-fronta.py).
 */

const http = require('node:http');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { execFile } = require('node:child_process');
const { WebSocketServer } = require('ws');
const pty = require('node-pty');

const SECRET = process.env.AGENT_SECRET || '';
// Tajemství služby nemá skončit v prostředí konverzací (tmux dědí prostředí
// služby): Claude v okně by jím mohl ovládat okna. Pro `taveo` stačí token API.
delete process.env.AGENT_SECRET;
const PORT = Number(process.env.PORT || 7681);
const HOME = process.env.HOME || '/home/agent';
const REGISTR = '/etc/taveo-agent/projekty.tsv';
const FRONTA = '/fronta';
const AUTORI = path.join(HOME, '.taveo-autori.json');
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
const OKNO = /^@\d+$/;
// Nepřipnutá konverzace bez pohybu se po téhle době uspí (proces skončí,
// konverzace zůstane uložená a jde probudit).
const USPAT_PO = Number(process.env.USPAT_PO_HODINACH || 12) * 3600;

if (SECRET.length < 32) {
    console.error('AGENT_SECRET chybí nebo je kratší než 32 znaků.');
    process.exit(1);
}

// ---------------------------------------------------------------- pomocníci

const tmux = (args) => new Promise((resolve, reject) => {
    execFile('tmux', args, { timeout: 10000 }, (error, stdout, stderr) => {
        error ? reject(new Error(stderr.trim() || error.message)) : resolve(stdout);
    });
});

const stejne = (a, b) => {
    const x = Buffer.from(String(a));
    const y = Buffer.from(String(b));
    return x.length === y.length && crypto.timingSafeEqual(x, y);
};

const podpis = (data) => crypto.createHmac('sha256', SECRET).update(data).digest('base64url');

/** Text na jeden řádek, bez řídicích znaků, zkrácený. */
const radek = (text, delka = 90) => {
    const cisty = String(text || '').replace(/[\x00-\x1f\x7f]/g, ' ').replace(/\s+/g, ' ').trim();
    return cisty.length > delka ? `${cisty.slice(0, delka - 1)}…` : cisty;
};

const cteniJson = (soubor, vychozi) => {
    try {
        return JSON.parse(fs.readFileSync(soubor, 'utf8'));
    } catch {
        return vychozi;
    }
};

/** Zápis přes dočasný soubor, ať Claude ani další požadavek nenačte půlku. */
const zapisJson = (soubor, data) => {
    const docasny = `${soubor}.${process.pid}.tmp`;
    fs.writeFileSync(docasny, JSON.stringify(data, null, 2));
    fs.renameSync(docasny, soubor);
};

function projekty() {
    let obsah = '';
    try {
        obsah = fs.readFileSync(REGISTR, 'utf8');
    } catch {
        return [];
    }

    return obsah.split('\n').filter(Boolean).map((r) => {
        const [slug, typ, zdroj] = r.split('\t');
        return { slug, typ, zdroj };
    });
}

const projekt = (slug) => projekty().find((p) => p.slug === slug) || null;
const slozka = (p) => `/projekty/${p.slug}/kod`;

/**
 * Běží v okně Claude, nebo už jen shell po jeho ukončení? tmux hlásí vždy
 * `bash`: Claude je podproces neinteraktivního shellu ve stejné skupině
 * procesů. Proto přímo podprocesy shellu okna z /proc.
 */
function bezi(pid, jmeno) {
    try {
        return fs.readFileSync(`/proc/${pid}/task/${pid}/children`, 'utf8').trim().split(/\s+/).filter(Boolean)
            .some((dite) => fs.readFileSync(`/proc/${dite}/comm`, 'utf8').trim() === jmeno);
    } catch {
        return false;
    }
}

/** Okna projektu v tmuxu. */
async function okna(slug) {
    try {
        await tmux(['has-session', '-t', `=${slug}`]);
    } catch {
        return [];
    }

    const format = ['#{window_id}', '#{@session}', '#{@autor}', '#{window_activity}', '#{pane_pid}', '#{@rc}', '#{@trvala}'].join('\t');
    const vystup = await tmux(['list-windows', '-t', `=${slug}`, '-F', format]);

    return vystup.trim().split('\n').filter(Boolean).map((r) => {
        const [id, session, autor, aktivita, pid, rc, trvala] = r.split('\t');
        return {
            id,
            session: session || null,
            autor: autor || null,
            aktivita: Number(aktivita) || null,
            claude: bezi(pid, 'claude'),
            rc: rc === '1',
            trvala: trvala === '1',
        };
    });
}

/** Okna s konverzacemi, bez okna serverového režimu pro aplikaci Claude. */
const konverzacniOkna = async (slug) => (await okna(slug)).filter((o) => !o.rc);

// ---------------------------------------------------------------- uložené konverzace

/**
 * Název a složka konverzace z jejího souboru (JSONL od Claude Code).
 * Přednost má vlastní název, pak shrnutí, jinak první zpráva člověka.
 * Čte se jen začátek souboru, dlouhé konverzace mají megabajty.
 */
function hlavicka(soubor) {
    let obsah = '';
    try {
        const fd = fs.openSync(soubor, 'r');
        const buffer = Buffer.alloc(256 * 1024);
        const precteno = fs.readSync(fd, buffer, 0, buffer.length, 0);
        fs.closeSync(fd);
        obsah = buffer.subarray(0, precteno).toString('utf8');
    } catch {
        return {};
    }

    const info = {};
    for (const r of obsah.split('\n')) {
        let zaznam;
        try { zaznam = JSON.parse(r); } catch { continue; }

        info.cwd ??= zaznam.cwd;
        if (zaznam.type === 'custom-title' && zaznam.customTitle) info.vlastni = zaznam.customTitle;
        if (zaznam.type === 'summary' && zaznam.summary) info.shrnuti ??= zaznam.summary;

        if (!info.prvni && zaznam.type === 'user' && !zaznam.isMeta) {
            const obsahZpravy = zaznam.message?.content;
            const text = typeof obsahZpravy === 'string'
                ? obsahZpravy
                : Array.isArray(obsahZpravy) ? obsahZpravy.filter((b) => b.type === 'text').map((b) => b.text).join(' ') : '';
            // Příkazy (/login) a systémové vložky začínají značkou, nejsou to zprávy.
            if (text && !text.trimStart().startsWith('<')) info.prvni = text;
        }
    }

    return { cwd: info.cwd, nazev: radek(info.vlastni || info.shrnuti || info.prvni || '') };
}

/** Konverzace projektu uložené na disku (~/.claude/projects/…/<id>.jsonl). */
function ulozene(p) {
    const koren = path.join(HOME, '.claude', 'projects');
    let slozky = [];
    try {
        slozky = fs.readdirSync(koren);
    } catch {
        return [];
    }

    const vysledek = [];
    // Složky pojmenovává Claude podle cesty. Na pojmenování nespoléháme,
    // projekt se ověří podle `cwd` uvnitř souboru.
    for (const s of slozky.filter((s) => s.includes(p.slug))) {
        let soubory = [];
        try { soubory = fs.readdirSync(path.join(koren, s)); } catch { continue; }

        for (const f of soubory) {
            const session = f.replace(/\.jsonl$/, '');
            if (!f.endsWith('.jsonl') || !UUID.test(session)) continue;

            const soubor = path.join(koren, s, f);
            const info = hlavicka(soubor);
            if (info.cwd !== slozka(p)) continue;

            vysledek.push({ session, nazev: info.nazev, aktualizace: Math.floor(fs.statSync(soubor).mtimeMs / 1000) });
        }
    }

    return vysledek;
}

/** Běžící i uložené konverzace dohromady: běžící nahoře, pak od nejnovější. */
async function konverzace(p) {
    const bezici = await konverzacniOkna(p.slug);
    const disk = ulozene(p);
    const autori = cteniJson(AUTORI, {});

    const seznam = disk.map((k) => {
        const okno = bezici.find((o) => o.session === k.session);
        return {
            ...k,
            nazev: k.nazev || 'Konverzace bez textu',
            autor: autori[k.session] || okno?.autor || null,
            okno: okno?.id || null,
            trvala: okno?.trvala || false,
            claude: okno?.claude || false,
            nova: false,
        };
    });

    // Okno, ve kterém ještě nikdo nic nenapsal, na disku konverzaci nemá.
    for (const o of bezici.filter((o) => !disk.some((k) => k.session === o.session))) {
        seznam.push({ session: o.session, nazev: 'Nová konverzace', aktualizace: o.aktivita, autor: o.autor, okno: o.id, trvala: o.trvala, claude: o.claude, nova: true });
    }

    return seznam.sort((a, b) => (Boolean(b.okno) - Boolean(a.okno)) || (b.aktualizace - a.aktualizace));
}

// ---------------------------------------------------------------- nová / navázaná konverzace

/**
 * Požadavek pro server (root). Kontejner sám zálohu databáze udělat nesmí,
 * nemá k ní přístup. Čeká na výsledek, nejdéle `limit` sekund.
 */
/** Zapíše požadavek do fronty a vrátí jeho ID. Na výsledek nečeká. */
function doFronty(pozadavek) {
    const id = crypto.randomUUID();
    const soubor = path.join(FRONTA, 'pozadavky', `pozadavek-${id}.json`);

    fs.writeFileSync(`${soubor}.tmp`, JSON.stringify(pozadavek));
    fs.renameSync(`${soubor}.tmp`, soubor);

    return id;
}

async function fronta(pozadavek, limit = 120) {
    const id = doFronty(pozadavek);
    const soubor = path.join(FRONTA, 'pozadavky', `pozadavek-${id}.json`);
    const vysledek = path.join(FRONTA, 'vysledky', `vysledek-${id}.json`);

    for (let i = 0; i < limit * 2; i++) {
        await new Promise((r) => setTimeout(r, 500));
        if (fs.existsSync(vysledek)) {
            const data = JSON.parse(fs.readFileSync(vysledek, 'utf8'));
            fs.rmSync(vysledek, { force: true });
            return data;
        }
    }

    fs.rmSync(soubor, { force: true });
    return { ok: false, vystup: 'Server na požadavek neodpověděl. Běží služba taveo-agent-fronta?' };
}

/**
 * Claude se při prvním spuštění ve složce ptá, jestli jí věří, a napoprvé
 * i na vzhled. Projektové složky jsou naše, odpověď známe předem.
 */
function pripravitClaude(cwd) {
    const soubor = path.join(HOME, '.claude.json');
    const nastaveni = cteniJson(soubor, {});

    if (nastaveni.projects?.[cwd]?.hasTrustDialogAccepted && nastaveni.theme) {
        return;
    }

    nastaveni.theme ??= 'dark';
    nastaveni.projects ??= {};
    nastaveni.projects[cwd] = { ...nastaveni.projects[cwd], hasTrustDialogAccepted: true };
    zapisJson(soubor, nastaveni);
}

/**
 * Prostředí okna. U webu na serveru míří git do snímků mimo složku webu.
 * Konverzace v aplikaci Claude bez vlastního názvu začínají názvem projektu
 * (grillnor-…), ne názvem serveru, ať se na mobilu projekty rozliší.
 */
function prostredi(p) {
    const nazevVAplikaci = ['-e', `CLAUDE_REMOTE_CONTROL_SESSION_NAME_PREFIX=${p.slug}`];

    return p.typ === 'server'
        ? [...nazevVAplikaci, '-e', `GIT_DIR=/snimky/${p.slug}.git`, '-e', `GIT_WORK_TREE=/projekty/${p.slug}/kod`]
        : nazevVAplikaci;
}

async function zalozit(p, { autor, session, nahradit }) {
    const bezici = await konverzacniOkna(p.slug);

    if (session) {
        if (!UUID.test(String(session)) || !ulozene(p).some((k) => k.session === session)) {
            throw Object.assign(new Error('Konverzace neexistuje.'), { status: 404 });
        }

        const otevrene = bezici.find((o) => o.session === session);
        if (otevrene) return otevrene.id;
    }

    // Před každou konverzací u živého webu snímek souborů a záloha databáze.
    if (p.typ === 'server') {
        const zaloha = await fronta({ akce: 'zaloha', projekt: p.slug });
        if (!zaloha.ok) {
            throw new Error(`Záloha webu se nepovedla, konverzaci nezakládám. ${zaloha.vystup || ''}`.trim());
        }
    }

    pripravitClaude(slozka(p));

    const id = session || crypto.randomUUID();
    const prikaz = `bash -lc 'claude ${session ? '--resume' : '--session-id'} ${id}; exec bash'`;
    const existuje = (await okna(p.slug)).length > 0;

    const args = existuje
        ? ['new-window', '-d', '-P', '-F', '#{window_id}', '-t', `=${p.slug}:`, '-c', slozka(p), ...prostredi(p), '-n', 'konverzace', prikaz]
        : ['new-session', '-d', '-P', '-F', '#{window_id}', '-s', p.slug, '-c', slozka(p), ...prostredi(p), '-n', 'konverzace', prikaz];

    const okno = (await tmux(args)).trim();
    await tmux(['set-option', '-w', '-t', okno, '@session', id]);
    await tmux(['set-option', '-w', '-t', okno, '@autor', radek(autor, 60)]);
    await tmux(['set-option', '-w', '-t', okno, 'automatic-rename', 'off']);

    if (!session && autor) {
        const autori = cteniJson(AUTORI, {});
        autori[id] = radek(autor, 60);
        zapisJson(AUTORI, autori);
    }

    // Navázání z prázdné nové konverzace: tu prázdnou zavřít, ať nezůstává viset.
    if (nahradit && OKNO.test(nahradit)) {
        const stare = bezici.find((o) => o.id === nahradit);
        if (stare && !ulozene(p).some((k) => k.session === stare.session)) {
            await tmux(['kill-window', '-t', stare.id]).catch(() => {});
        }
    }

    return okno;
}

// ---------------------------------------------------------------- hotová práce

/**
 * Hotová práce, kterou nástroje ukážou ke kontrole. Podklady leží v
 * /projekty/<projekt>/predani/<jméno>/:
 *
 *   popis.md      co se změnilo, lidsky (první řádek # Nadpis)
 *   *.png …       snímky z prohlížeče
 *   zacatek       kdy agent začal měnit živý web (ISO čas), k tlačítku Vrátit
 *   *.html …      hotové kódy a texty k vložení (Shoptet, Upgates)
 *
 * U git projektu je hotová práce větev agent/<jméno> ve schránce (odeslat.git)
 * a nástroje ji zveřejní přes GitHub. Agent sám do main pořád nemůže.
 * U živého webu a projektu bez kódu je hotová práce každá složka v predani/.
 */
const VETEV = /^[a-z0-9][a-z0-9._-]{0,60}$/;
const SNIMEK = /^[A-Za-z0-9][A-Za-z0-9._-]{0,80}\.(png|jpe?g|webp)$/i;
const KOD = /^[A-Za-z0-9][A-Za-z0-9._-]{0,80}\.(html?|txt|css|js|json|liquid|xml|csv|md)$/i;
const schranka = (p) => `/projekty/${p.slug}/odeslat.git`;
const predaniSlozka = (p, jmeno) => path.join('/projekty', p.slug, 'predani', jmeno);

const git = (args) => new Promise((resolve, reject) => {
    execFile('git', args, { timeout: 10000 }, (error, stdout, stderr) => {
        error ? reject(new Error(stderr.trim() || error.message)) : resolve(stdout);
    });
});

/** Popis, snímky, kódy a začátek práce ze složky předání (každá část volitelná). */
function podklady(slozka) {
    const cist = (soubor, limit) => { try { return fs.readFileSync(path.join(slozka, soubor), 'utf8').slice(0, limit); } catch { return null; } };
    let soubory = [];
    try { soubory = fs.readdirSync(slozka).sort(); } catch { /* bez podkladů */ }

    const zacatek = Date.parse((cist('zacatek', 100) || '').trim());

    return {
        popis: cist('popis.md', 20000),
        snimky: soubory.filter((s) => SNIMEK.test(s)),
        kody: soubory.filter((s) => KOD.test(s) && s !== 'popis.md').slice(0, 20).map((s) => ({ soubor: s, obsah: cist(s, 200000) })),
        zacatek: Number.isNaN(zacatek) ? null : Math.floor(zacatek / 1000),
    };
}

async function predani(p) {
    if (p.typ !== 'git') {
        return predaniSlozky(p);
    }

    if (!fs.existsSync(schranka(p))) {
        return [];
    }

    const vypis = await git(['--git-dir', schranka(p), 'for-each-ref', '--sort=-committerdate',
        '--format=%(refname:lstrip=3)%00%(objectname)%00%(committerdate:unix)%00%(subject)', 'refs/heads/agent/']);

    return vypis.split('\n').filter(Boolean).map((radek) => {
        const [jmeno, sha, cas, nadpis] = radek.split('\0');
        if (!VETEV.test(jmeno)) {
            return null;
        }

        const { popis, snimky } = podklady(predaniSlozka(p, jmeno));

        return { vetev: `agent/${jmeno}`, jmeno, sha, cas: Number(cas), nadpis, popis, snimky };
    }).filter(Boolean);
}

/** Živý web a projekt bez kódu: hotová práce = složky v predani/, nejnovější první. */
function predaniSlozky(p) {
    const koren = path.join('/projekty', p.slug, 'predani');
    let jmena = [];
    try { jmena = fs.readdirSync(koren, { withFileTypes: true }).filter((d) => d.isDirectory() && VETEV.test(d.name)).map((d) => d.name); } catch { return []; }

    return jmena.map((jmeno) => {
        const slozka = path.join(koren, jmeno);
        const stat = fs.statSync(slozka);
        const pod = podklady(slozka);
        const nadpis = (pod.popis || '').match(/^#\s+(.+)$/m)?.[1]?.trim() || jmeno;

        // Bez souboru zacatek: kdy agent složku založil (má ji založit dřív, než začne).
        return { jmeno, cas: Math.floor(stat.mtimeMs / 1000), nadpis, ...pod, zacatek: pod.zacatek ?? Math.floor((stat.birthtimeMs || stat.ctimeMs) / 1000) };
    }).sort((a, b) => b.cas - a.cas);
}

// ---------------------------------------------------------------- HTTP API

const odpoved = (res, status, data) => {
    res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' });
    res.end(JSON.stringify(data));
};

const telo = (req) => new Promise((resolve) => {
    let data = '';
    req.on('data', (c) => { data += c; if (data.length > 65536) req.destroy(); });
    req.on('end', () => { try { resolve(JSON.parse(data || '{}')); } catch { resolve({}); } });
});

async function api(req, res) {
    const auth = req.headers.authorization || '';
    if (!stejne(auth, `Bearer ${SECRET}`)) {
        return odpoved(res, 401, { chyba: 'Neplatné tajemství.' });
    }

    const url = new URL(req.url, 'http://agent');

    // Laravel kóduje ID okna (@3 → %403), bez dekódování by se nenašlo.
    let casti;
    try {
        casti = url.pathname.split('/').filter(Boolean).map(decodeURIComponent); // api, projekty, slug, …
    } catch {
        return odpoved(res, 400, { chyba: 'Neplatná adresa.' });
    }

    if (req.method === 'GET' && url.pathname === '/api/projekty') {
        const seznam = await Promise.all(projekty().map(async (p) => ({ ...p, vlakna: await konverzacniOkna(p.slug), aplikace: await adresaAplikace(p) })));
        return odpoved(res, 200, seznam);
    }

    // Ranní přehled ručně (ověření po nasazení). Jen jednou denně, jako plánovaný.
    if (req.method === 'POST' && url.pathname === '/api/ranni-prehled') {
        await ranniPrehled({ vynutit: true });
        return odpoved(res, 202, { spusteno: ranniBezi });
    }

    if (req.method === 'GET' && url.pathname === '/api/predplatne') {
        return odpoved(res, 200, await predplatne());
    }

    // Po zveřejnění: server si z GitHubu (main) stáhne novou verzi služby.
    // Nečeká se na výsledek, aktualizace tuhle službu sama restartuje.
    // Výsledek požadavku, na který se nečekalo (vrácení webu). 404 = ještě běží.
    if (req.method === 'GET' && casti[1] === 'fronta' && casti.length === 3 && UUID.test(casti[2] || '')) {
        const soubor = path.join(FRONTA, 'vysledky', `vysledek-${casti[2]}.json`);
        if (!fs.existsSync(soubor)) {
            return odpoved(res, 404, { hotovo: false });
        }
        const data = JSON.parse(fs.readFileSync(soubor, 'utf8'));
        fs.rmSync(soubor, { force: true });
        return odpoved(res, 200, { hotovo: true, ...data });
    }

    if (req.method === 'POST' && url.pathname === '/api/aktualizovat') {
        doFronty({ akce: 'aktualizovat', projekt: 'taveo' });
        return odpoved(res, 202, { ok: true });
    }

    if (req.method === 'POST' && url.pathname === '/api/projekty') {
        const data = await telo(req);
        const vysledek = await fronta({ akce: 'pridat', projekt: data.projekt, typ: data.typ, zdroj: data.zdroj }, 180);
        return odpoved(res, vysledek.ok ? 200 : 422, vysledek);
    }

    const p = casti[0] === 'api' && casti[1] === 'projekty' ? projekt(casti[2]) : null;
    if (!p) {
        return odpoved(res, 404, { chyba: 'Projekt neexistuje.' });
    }

    // Hotová práce ke zveřejnění: větve agent/… ve schránce se snímky a popisem.
    if (casti[3] === 'predani') {
        if (req.method === 'GET' && casti.length === 4) {
            return odpoved(res, 200, await predani(p));
        }

        const jmeno = casti[4] || '';
        if (!VETEV.test(jmeno)) {
            return odpoved(res, 400, { chyba: 'Neplatná větev.' });
        }

        if (req.method === 'GET' && casti.length === 6) {
            const soubor = casti[5];
            const cesta = path.join(predaniSlozka(p, jmeno), soubor);
            if (!SNIMEK.test(soubor) || !fs.existsSync(cesta)) {
                return odpoved(res, 404, { chyba: 'Snímek neexistuje.' });
            }
            const pripona = path.extname(soubor).slice(1).toLowerCase();
            res.writeHead(200, { 'Content-Type': `image/${pripona === 'jpg' ? 'jpeg' : pripona}` });
            return fs.createReadStream(cesta).pipe(res);
        }

        // Po zveřejnění nebo zahození: větev ze schránky pryč, i se snímky.
        // Jen když je pořád na stejném commitu, novější práci agenta to nesmaže.
        if (req.method === 'DELETE' && casti.length === 5 && p.typ !== 'git') {
            fs.rmSync(predaniSlozka(p, jmeno), { recursive: true, force: true });
            return odpoved(res, 200, { ok: true });
        }

        // Vrátit živý web do stavu před touhle prací (soubory, volitelně databázi).
        // Dělá to root (agent.sh obnovit), výsledek si nástroje vyzvednou.
        if (req.method === 'POST' && casti.length === 6 && casti[5] === 'vratit' && p.typ === 'server') {
            const { cas, databaze } = await telo(req);
            if (!Number.isInteger(cas) || cas <= 0) {
                return odpoved(res, 400, { chyba: 'Chybí čas, do kterého se vrací.' });
            }
            const id = doFronty({ akce: 'obnovit', projekt: p.slug, cas, databaze: databaze === true });
            return odpoved(res, 202, { id });
        }

        if (req.method === 'DELETE' && casti.length === 5) {
            const { sha } = await telo(req);
            if (!/^[0-9a-f]{40}$/.test(sha || '')) {
                return odpoved(res, 400, { chyba: 'Chybí commit.' });
            }
            try {
                await git(['--git-dir', schranka(p), 'update-ref', '-d', `refs/heads/agent/${jmeno}`, sha]);
            } catch {
                return odpoved(res, 409, { chyba: 'Agent mezitím na větvi pokračoval.' });
            }
            fs.rmSync(predaniSlozka(p, jmeno), { recursive: true, force: true });
            return odpoved(res, 200, { ok: true });
        }
    }

    if (casti[3] === 'konverzace' && casti.length === 4) {
        if (req.method === 'GET') {
            return odpoved(res, 200, await konverzace(p));
        }

        if (req.method === 'POST') {
            const okno = await zalozit(p, await telo(req));
            return odpoved(res, 201, { okno });
        }
    }

    // Úloha na pozadí (z ClickUpu). Hned potvrdí, výsledek pošle zpět na web.
    if (req.method === 'POST' && casti[3] === 'ulohy' && casti.length === 4) {
        const data = await telo(req);
        if (!Number.isInteger(data.id) || typeof data.zadani !== 'string' || !data.zadani.trim()) {
            return odpoved(res, 422, { chyba: 'Úloha potřebuje id a zadání.' });
        }
        if (data.session && !UUID.test(String(data.session))) {
            return odpoved(res, 422, { chyba: 'Neplatná konverzace.' });
        }

        zaraditUlohu(p, { id: data.id, zadani: data.zadani, session: data.session || null, autor: radek(data.autor || 'ClickUp', 60) });
        return odpoved(res, 202, { zarazeno: true, ve_fronte: (fronty.get(p.slug) || []).length });
    }

    // Připnout / odepnout: trvalá konverzace se sama neuspí.
    if (req.method === 'POST' && casti[3] === 'okna' && casti[5] === 'trvala' && casti.length === 6) {
        const okno = (await konverzacniOkna(p.slug)).find((o) => o.id === casti[4]);
        if (!okno) {
            return odpoved(res, 404, { chyba: 'Konverzace už neběží.' });
        }

        const { trvala } = await telo(req);
        await tmux(trvala ? ['set-option', '-w', '-t', okno.id, '@trvala', '1'] : ['set-option', '-wu', '-t', okno.id, '@trvala']);
        return odpoved(res, 200, { ok: true });
    }

    if (req.method === 'DELETE' && casti[3] === 'okna' && casti.length === 5) {
        // Okno musí patřit tomuhle projektu, ID oken jsou společná pro všechny.
        const okno = (await konverzacniOkna(p.slug)).find((o) => o.id === casti[4]);
        if (!okno) {
            return odpoved(res, 404, { chyba: 'Konverzace už neběží.' });
        }

        await tmux(['kill-window', '-t', okno.id]);
        return odpoved(res, 200, { ok: true });
    }

    return odpoved(res, 404, { chyba: 'Neznámá akce.' });
}

// ---------------------------------------------------------------- aplikace Claude (Remote Control)

const PRIHLASENI = path.join(HOME, '.claude', '.credentials.json');
const prihlasen = () => fs.existsSync(PRIHLASENI);

// ---------------------------------------------------------------- využití předplatného

/**
 * Kolik z limitů předplatného je vyčerpáno (5h relace, týden), stejné číslo
 * jako /usage v Claude Code. Čte token, kterým je Claude v kontejneru
 * přihlášený, a ptá se Anthropicu. Token neobnovuje: to dělá Claude Code sám
 * a obnova by mu přihlášení vyměnila pod rukama. Ven jdou jen čísla.
 * Odpověď se drží minutu, endpoint má vlastní limit dotazů.
 */
let vyuziti = { cas: 0, data: null };

async function predplatne() {
    if (Date.now() - vyuziti.cas < 60_000 && vyuziti.data) {
        return vyuziti.data;
    }

    let oauth;
    try {
        oauth = JSON.parse(fs.readFileSync(PRIHLASENI, 'utf8')).claudeAiOauth;
    } catch {
        throw Object.assign(new Error('Claude v kontejneru není přihlášený.'), { status: 503 });
    }

    const r = await fetch('https://api.anthropic.com/api/oauth/usage', {
        headers: { Authorization: `Bearer ${oauth.accessToken}`, 'anthropic-beta': 'oauth-2025-04-20' },
        signal: AbortSignal.timeout(8000),
    }).catch(() => null);

    if (!r || !r.ok) {
        const chyba = r?.status === 401 ? 'Přihlášení Clauda vypršelo, obnoví se v příští konverzaci.' : `Anthropic neodpověděl (${r?.status ?? 'spojení'}).`;
        throw Object.assign(new Error(chyba), { status: 503 });
    }

    const u = await r.json();
    vyuziti = {
        cas: Date.now(),
        data: { plan: oauth.subscriptionType ?? null, limity: u.limits ?? null, five_hour: u.five_hour ?? null, seven_day: u.seven_day ?? null },
    };

    return vyuziti.data;
}

/** Adresa projektu v aplikaci Claude, kterou vypsal serverový režim. */
async function adresaAplikace(p) {
    const rc = (await okna(p.slug)).find((o) => o.rc);
    if (!rc) return null;

    try {
        const obsah = await tmux(['capture-pane', '-p', '-J', '-t', rc.id, '-S', '-200']);
        return obsah.match(/https:\/\/claude\.ai\/code\?environment=[\w-]+/)?.[0] ?? null;
    } catch {
        return null;
    }
}

/**
 * Web na serveru má v cache konfigurace absolutní cesty serveru
 * (/home/taveo.cz/grillnor/storage/…). Stejná cesta proto v kontejneru
 * vede odkazem na připojenou složku, jinak `php artisan` spadne.
 */
function zrcadlitCesty() {
    for (const p of projekty().filter((p) => p.typ === 'server' && /^\/home\/[^/]+\/[^/]+/.test(p.zdroj))) {
        let cil = null;
        try { cil = fs.readlinkSync(p.zdroj); } catch { /* odkaz ještě není */ }
        if (cil === slozka(p)) continue;

        try {
            fs.mkdirSync(path.dirname(p.zdroj), { recursive: true });
            try { fs.unlinkSync(p.zdroj); } catch { /* nebylo co mazat */ }
            fs.symlinkSync(slozka(p), p.zdroj);
        } catch (e) {
            console.error(`cesta ${p.zdroj}: ${e.message}`);
        }
    }
}

/**
 * Každý projekt má okno se serverovým režimem. Když neběží (restart
 * kontejneru, Claude to po výpadku sítě vzdal), založí se znovu.
 * Bez přihlášení nemá smysl, Remote Control potřebuje předplatné.
 */
async function hlidatAplikaci() {
    zrcadlitCesty();
    if (!prihlasen()) return;

    for (const p of projekty()) {
        const rc = (await okna(p.slug)).find((o) => o.rc);
        if (rc?.claude) continue;
        if (rc) await tmux(['kill-window', '-t', rc.id]).catch(() => {});

        pripravitClaude(slozka(p));
        const prikaz = `bash -lc 'claude remote-control --name "TAVEO · ${p.slug}" --remote-control-session-name-prefix ${p.slug} --spawn same-dir; exec bash'`;
        const existuje = (await okna(p.slug)).length > 0;
        const args = existuje
            ? ['new-window', '-d', '-P', '-F', '#{window_id}', '-t', `=${p.slug}:`, '-c', slozka(p), ...prostredi(p), '-n', 'aplikace-claude', prikaz]
            : ['new-session', '-d', '-P', '-F', '#{window_id}', '-s', p.slug, '-c', slozka(p), ...prostredi(p), '-n', 'aplikace-claude', prikaz];

        try {
            const okno = (await tmux(args)).trim();
            await tmux(['set-option', '-w', '-t', okno, '@rc', '1']);
            await tmux(['set-option', '-w', '-t', okno, 'automatic-rename', 'off']);
            console.log(`${new Date().toISOString()} aplikace Claude: spuštěno pro ${p.slug}`);
        } catch (e) {
            console.error(`aplikace Claude pro ${p.slug}: ${e.message}`);
        }
    }
}

// ---------------------------------------------------------------- ranní přehled

const RANNI_CAS = process.env.RANNI_PREHLED_CAS || '06:50';
const RANNI_SLOZKA = path.join(HOME, '.taveo-ranni');
const MARKETING = 'marketing';
let ranniBezi = false;

/** Dnešní datum a čas v Praze (kontejner má TZ=Europe/Prague). */
const ted = () => {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return { den: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`, cas: `${pad(d.getHours())}:${pad(d.getMinutes())}`, tyden: d.getDay() };
};

/**
 * Ranní přehled od agenta: jeden headless běh Clauda denně ve všední dny,
 * v projektu „marketing“. Smí jen `taveo` (čtení dat klientů, zápis konceptů)
 * a čtení souborů, nic jiného. Pojistky kvůli předplatnému: jeden běh denně
 * (značka v ~/.taveo-ranni), žádné opakování po chybě nebo limitu, časový
 * limit a strop kroků. Výstup jde do logu vedle značky.
 */
async function ranniPrehled({ vynutit = false } = {}) {
    const { den, cas, tyden } = ted();
    const znacka = path.join(RANNI_SLOZKA, `${den}.hotovo`);

    if (ranniBezi || fs.existsSync(znacka)) return;
    if (!vynutit && (cas < RANNI_CAS || tyden === 0 || tyden === 6)) return;
    if (!prihlasen() || !projekt(MARKETING)) return;

    ranniBezi = true;
    fs.mkdirSync(RANNI_SLOZKA, { recursive: true });
    // Značka hned na začátku: ani pád uprostřed nevede k druhému běhu týž den.
    fs.writeFileSync(znacka, new Date().toISOString());

    const cwd = `/projekty/${MARKETING}/kod`;
    pripravitClaude(cwd);
    const zadani = fs.readFileSync('/opt/terminal/ranni-prehled.md', 'utf8');

    console.log(`${new Date().toISOString()} ranní přehled: start`);

    const proces = execFile('claude', [
        '-p', zadani,
        '--permission-mode', 'dontAsk',
        '--allowedTools', 'Bash(taveo *)', 'Read', 'Glob', 'Grep',
        '--max-turns', '150',
    ], { cwd, timeout: 40 * 60 * 1000, maxBuffer: 10 * 1024 * 1024 }, (chyba, stdout, stderr) => {
        ranniBezi = false;
        const log = path.join(RANNI_SLOZKA, `${den}.log`);
        fs.writeFileSync(log, [stdout, stderr, chyba ? `CHYBA: ${chyba.message}` : ''].join('\n'));

        const limit = /usage limit|limit reached|rate limit/i.test(`${stdout}${stderr}`);
        console.log(`${new Date().toISOString()} ranní přehled: ${chyba ? 'skončil chybou' : 'hotovo'}${limit ? ' (limit předplatného, dnes se nic neopakuje)' : ''}`);
    });
    // Zadání jde v argumentu, vstup nic nenese. Bez zavření by Claude čekal na data.
    proces.stdin?.end();
}

setInterval(() => ranniPrehled().catch((e) => console.error(e.message)), 60 * 1000);

setTimeout(() => hlidatAplikaci().catch((e) => console.error(e.message)), 5000);
setInterval(() => hlidatAplikaci().catch((e) => console.error(e.message)), 3 * 60 * 1000);

// ---------------------------------------------------------------- úlohy na pozadí (ClickUp)

/*
 * Komentář „@agent …“ v ClickUpu přijde přes web jako úloha. Agent ji odpracuje
 * headless (`claude -p`) ve složce projektu, jako uložená konverzace, na kterou
 * jde v nástrojích navázat. Výsledek jde na web, ten odpoví do ClickUpu.
 *
 * Pojistky: v projektu běží nejvýš jedna úloha (ostatní čekají ve frontě),
 * celkem nejvýš dvě, časový limit a strop kroků. U webu na serveru před každou
 * úlohou snímek a záloha databáze, stejně jako před konverzací. Oprávnění
 * hlídá režim auto podle managed-settings: co by chtělo potvrzení, neudělá.
 */
const ULOHY_SOUBEZNE = Number(process.env.ULOHY_SOUBEZNE || 2);
const ULOHA_LIMIT_MIN = Number(process.env.ULOHA_LIMIT_MIN || 30);
const ULOHY_LOG = path.join(HOME, '.taveo-ulohy');
const API_WEBU = process.env.TAVEO_API_URL || 'https://taveo.cz/nastroje/api/agent';
const fronty = new Map();
const bezici = new Set();

function zaraditUlohu(p, uloha) {
    fronty.set(p.slug, [...(fronty.get(p.slug) || []), uloha]);
    console.log(`${new Date().toISOString()} úloha ${uloha.id}: zařazena v ${p.slug}`);
    spustitDalsi();
}

function spustitDalsi() {
    for (const [slug, cekajici] of fronty) {
        if (bezici.size >= ULOHY_SOUBEZNE) return;
        if (bezici.has(slug) || !cekajici.length) continue;

        const uloha = cekajici.shift();
        const p = projekt(slug);
        if (!p) {
            vysledekUlohy(uloha, { ok: false, chyba: `Projekt ${slug} už neexistuje.` });
            continue;
        }

        bezici.add(slug);
        odpracovat(p, uloha)
            .catch((e) => vysledekUlohy(uloha, { ok: false, chyba: e.message }))
            .finally(() => { bezici.delete(slug); spustitDalsi(); });
    }
}

async function odpracovat(p, uloha) {
    if (!prihlasen()) throw new Error('Claude na serveru není přihlášený.');

    if (p.typ === 'server') {
        const zaloha = await fronta({ akce: 'zaloha', projekt: p.slug });
        if (!zaloha.ok) throw new Error(`Záloha webu se nepovedla, úlohu nespouštím. ${zaloha.vystup || ''}`.trim());
    }

    const cwd = slozka(p);
    pripravitClaude(cwd);

    const navazat = uloha.session && ulozene(p).some((k) => k.session === uloha.session);
    const session = navazat ? uloha.session : crypto.randomUUID();
    const autori = cteniJson(AUTORI, {});
    autori[session] ??= uloha.autor;
    zapisJson(AUTORI, autori);

    // Proměnné z `prostredi` (tmux -e KLIC=hodnota) pro execFile jako objekt.
    const env = { ...process.env };
    const dvojice = prostredi(p);
    for (let i = 1; i < dvojice.length; i += 2) {
        const [klic, ...hodnota] = dvojice[i].split('=');
        env[klic] = hodnota.join('=');
    }

    console.log(`${new Date().toISOString()} úloha ${uloha.id}: start v ${p.slug} (${navazat ? 'navazuje' : 'nová'} ${session})`);

    const { stdout, stderr, chyba } = await new Promise((resolve) => {
        const proces = execFile('claude', [
            '-p', uloha.zadani,
            ...(navazat ? ['--resume', session] : ['--session-id', session]),
            '--output-format', 'json',
            '--permission-mode', 'auto',
            '--max-turns', '200',
        ], { cwd, env, timeout: ULOHA_LIMIT_MIN * 60 * 1000, maxBuffer: 20 * 1024 * 1024 }, (chyba, stdout, stderr) => resolve({ stdout, stderr, chyba }));
        proces.stdin?.end();
    });

    fs.mkdirSync(ULOHY_LOG, { recursive: true });
    fs.writeFileSync(path.join(ULOHY_LOG, `${uloha.id}.log`), [stdout, stderr, chyba ? `CHYBA: ${chyba.message}` : ''].join('\n'));

    let vystup = null;
    try { vystup = JSON.parse(stdout); } catch { /* spadl dřív, než něco vypsal */ }

    const limit = /usage limit|limit reached|rate limit/i.test(`${stdout}${stderr}`);
    const casVyprsel = chyba?.killed || chyba?.signal === 'SIGTERM';
    const ok = Boolean(vystup && !vystup.is_error && !chyba);

    await vysledekUlohy(uloha, {
        ok,
        session: vystup?.session_id || session,
        vysledek: typeof vystup?.result === 'string' ? vystup.result : null,
        chyba: ok ? null : limit
            ? 'Narazil jsem na limit předplatného Claude.'
            : casVyprsel ? `Nestihl jsem to do ${ULOHA_LIMIT_MIN} minut.` : (vystup?.subtype || chyba?.message || 'Claude skončil bez výsledku.').slice(0, 500),
    });

    console.log(`${new Date().toISOString()} úloha ${uloha.id}: ${ok ? 'hotovo' : 'chyba'}`);
}

/** Výsledek na web (ten odpoví do ClickUpu). Při výpadku sítě ještě dvakrát. */
async function vysledekUlohy(uloha, data) {
    let token = process.env.AGENT_API_TOKEN || '';
    try { token ||= fs.readFileSync(path.join(HOME, '.config', 'taveo', 'token'), 'utf8').trim(); } catch { /* bez tokenu web odmítne */ }

    for (let pokus = 1; pokus <= 3; pokus++) {
        try {
            const odpovedWebu = await fetch(`${API_WEBU}/ulohy/${uloha.id}/vysledek`, {
                method: 'POST',
                headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(data),
                signal: AbortSignal.timeout(30000),
            });
            if (odpovedWebu.ok || odpovedWebu.status < 500) {
                if (!odpovedWebu.ok) console.error(`úloha ${uloha.id}: web odmítl výsledek (${odpovedWebu.status})`);
                return;
            }
        } catch (e) {
            console.error(`úloha ${uloha.id}: výsledek se neodeslal (${e.message}), pokus ${pokus}`);
        }
        await new Promise((r) => setTimeout(r, pokus * 5000));
    }
}

/** Uspí nepřipnuté konverzace, ve kterých se dlouho nic nedělo. */
async function uspatNecinne() {
    const ted = Math.floor(Date.now() / 1000);

    for (const p of projekty()) {
        for (const o of await konverzacniOkna(p.slug)) {
            if (o.trvala || !o.aktivita || ted - o.aktivita < USPAT_PO) continue;

            await tmux(['kill-window', '-t', o.id]).catch(() => {});
            console.log(`${new Date().toISOString()} uspáno ${p.slug}:${o.id} (${o.session}), bez pohybu ${Math.round((ted - o.aktivita) / 3600)} h`);
        }
    }
}

setInterval(() => uspatNecinne().catch((e) => console.error(e.message)), 10 * 60 * 1000);

const server = http.createServer((req, res) => {
    api(req, res).catch((e) => odpoved(res, e.status || 500, { chyba: e.message }));
});

// ---------------------------------------------------------------- terminál

/**
 * Vstupenka: base64url(JSON {p: projekt, v: ID okna (@3), u: kdo, exp}).podpis
 * Vydává ji Laravel po přihlášení do nástrojů, platí minutu.
 */
function vstupenka(text) {
    const [data, sig] = String(text || '').split('.');
    if (!data || !sig || !stejne(sig, podpis(data))) return null;

    try {
        const obsah = JSON.parse(Buffer.from(data, 'base64url').toString('utf8'));
        return obsah.exp > Date.now() / 1000 ? obsah : null;
    } catch {
        return null;
    }
}

const wss = new WebSocketServer({ noServer: true, maxPayload: 1024 * 1024 });

server.on('upgrade', (req, socket, head) => {
    const url = new URL(req.url, 'http://agent');
    const v = vstupenka(url.searchParams.get('t'));
    const p = v ? projekt(v.p) : null;

    if (!v || !p || !OKNO.test(String(v.v))) {
        socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
        return socket.destroy();
    }

    wss.handleUpgrade(req, socket, head, (ws) => pripojit(ws, p, v));
});

function pripojit(ws, p, v) {
    // Každé připojení má vlastní sezení ve skupině projektu. Sdílí okna, ale
    // přepnutí konverzace u Toma nepřepne konverzaci Pavlovi. Po odpojení zmizí.
    const id = `${p.slug}-w${crypto.randomBytes(3).toString('hex')}`;
    const term = pty.spawn('tmux', [
        'new-session', '-t', `=${p.slug}`, '-s', id, ';',
        'set-option', '-t', id, 'destroy-unattached', 'on', ';',
        'set-option', '-t', id, 'status', 'off', ';',
        'select-window', '-t', `${id}:${v.v}`,
    ], {
        name: 'xterm-256color',
        cols: 120,
        rows: 32,
        cwd: slozka(p),
        env: { ...process.env, TERM: 'xterm-256color', LANG: 'C.UTF-8' },
    });

    console.log(`${new Date().toISOString()} ${v.u || '?'} otevřel ${p.slug}:${v.v}`);

    term.onData((data) => ws.readyState === ws.OPEN && ws.send(data));
    term.onExit(() => ws.close(1000, 'konec'));

    ws.on('message', (zprava) => {
        let data;
        try { data = JSON.parse(zprava.toString()); } catch { return; }

        if (data.t === 'i' && typeof data.d === 'string') {
            term.write(data.d);
        } else if (data.t === 'r') {
            const cols = Math.max(20, Math.min(400, Number(data.c) || 80));
            const rows = Math.max(5, Math.min(200, Number(data.r) || 24));
            term.resize(cols, rows);
        }
    });

    // LiteSpeed ukončí tiché spojení, ping ho drží naživu.
    const ping = setInterval(() => ws.readyState === ws.OPEN && ws.ping(), 25000);

    ws.on('close', () => {
        clearInterval(ping);
        try { term.kill(); } catch { /* už skončil */ }
    });
}

server.listen(PORT, '0.0.0.0', () => console.log(`Služba agenta poslouchá na ${PORT}`));
