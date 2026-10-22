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
const PORT = Number(process.env.PORT || 7681);
const HOME = process.env.HOME || '/home/agent';
const REGISTR = '/etc/taveo-agent/projekty.tsv';
const FRONTA = '/fronta';
const AUTORI = path.join(HOME, '.taveo-autori.json');
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;
const OKNO = /^@\d+$/;

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

    const format = ['#{window_id}', '#{@session}', '#{@autor}', '#{window_activity}', '#{pane_pid}', '#{@rc}'].join('\t');
    const vystup = await tmux(['list-windows', '-t', `=${slug}`, '-F', format]);

    return vystup.trim().split('\n').filter(Boolean).map((r) => {
        const [id, session, autor, aktivita, pid, rc] = r.split('\t');
        return { id, session: session || null, autor: autor || null, aktivita: Number(aktivita) || null, claude: bezi(pid, 'claude'), rc: rc === '1' };
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
            claude: okno?.claude || false,
            nova: false,
        };
    });

    // Okno, ve kterém ještě nikdo nic nenapsal, na disku konverzaci nemá.
    for (const o of bezici.filter((o) => !disk.some((k) => k.session === o.session))) {
        seznam.push({ session: o.session, nazev: 'Nová konverzace', aktualizace: o.aktivita, autor: o.autor, okno: o.id, claude: o.claude, nova: true });
    }

    return seznam.sort((a, b) => (Boolean(b.okno) - Boolean(a.okno)) || (b.aktualizace - a.aktualizace));
}

// ---------------------------------------------------------------- nová / navázaná konverzace

/**
 * Požadavek pro server (root). Kontejner sám zálohu databáze udělat nesmí,
 * nemá k ní přístup. Čeká na výsledek, nejdéle `limit` sekund.
 */
async function fronta(pozadavek, limit = 120) {
    const id = crypto.randomUUID();
    const soubor = path.join(FRONTA, 'pozadavky', `pozadavek-${id}.json`);
    const vysledek = path.join(FRONTA, 'vysledky', `vysledek-${id}.json`);

    fs.writeFileSync(`${soubor}.tmp`, JSON.stringify(pozadavek));
    fs.renameSync(`${soubor}.tmp`, soubor);

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

/** Prostředí okna. U webu na serveru míří git do snímků mimo složku webu. */
function prostredi(p) {
    return p.typ === 'server'
        ? ['-e', `GIT_DIR=/snimky/${p.slug}.git`, '-e', `GIT_WORK_TREE=/projekty/${p.slug}/kod`]
        : [];
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

    if (req.method === 'POST' && url.pathname === '/api/projekty') {
        const data = await telo(req);
        const vysledek = await fronta({ akce: 'pridat', projekt: data.projekt, typ: data.typ, zdroj: data.zdroj }, 180);
        return odpoved(res, vysledek.ok ? 200 : 422, vysledek);
    }

    const p = casti[0] === 'api' && casti[1] === 'projekty' ? projekt(casti[2]) : null;
    if (!p) {
        return odpoved(res, 404, { chyba: 'Projekt neexistuje.' });
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

const prihlasen = () => fs.existsSync(path.join(HOME, '.claude', '.credentials.json'));

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
        const prikaz = `bash -lc 'claude remote-control --name "TAVEO · ${p.slug}" --spawn same-dir; exec bash'`;
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

setTimeout(() => hlidatAplikaci().catch((e) => console.error(e.message)), 5000);
setInterval(() => hlidatAplikaci().catch((e) => console.error(e.message)), 3 * 60 * 1000);

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
