'use strict';

/**
 * Služba v kontejneru taveo-agent. Nástroje TAVEO přes ni:
 *
 *   - čtou projekty a jejich vlákna (okna tmuxu),
 *   - zakládají, přejmenovávají a ukončují vlákna,
 *   - připojují terminál v prohlížeči (WebSocket přes LiteSpeed).
 *
 * API volá jen Laravel na stejném serveru (127.0.0.1) se sdíleným tajemstvím.
 * Prohlížeč dostane jen krátkou podepsanou vstupenku na jedno vlákno.
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
const REGISTR = '/etc/taveo-agent/projekty.tsv';
const FRONTA = '/fronta';

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

/** Název vlákna: bez řídicích znaků, krátký, aby se vešel do seznamu. */
const nazev = (text, vychozi) => {
    const cisty = String(text || '').replace(/[\x00-\x1f\x7f]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 60);
    return cisty || vychozi;
};

function projekty() {
    let obsah = '';
    try {
        obsah = fs.readFileSync(REGISTR, 'utf8');
    } catch {
        return [];
    }

    return obsah.split('\n').filter(Boolean).map((radek) => {
        const [slug, typ, zdroj] = radek.split('\t');
        return { slug, typ, zdroj };
    });
}

const projekt = (slug) => projekty().find((p) => p.slug === slug) || null;

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

async function vlakna(slug) {
    try {
        await tmux(['has-session', '-t', `=${slug}`]);
    } catch {
        return [];
    }

    const format = ['#{window_id}', '#{window_index}', '#{window_name}', '#{@autor}', '#{window_activity}', '#{pane_pid}'].join('\t');
    const vystup = await tmux(['list-windows', '-t', `=${slug}`, '-F', format]);

    return vystup.trim().split('\n').filter(Boolean).map((radek) => {
        const [id, index, jmeno, autor, aktivita, pid] = radek.split('\t');
        return {
            // ID okna (@3) se nemění, pořadí po zavření jiného vlákna ano.
            id,
            poradi: Number(index),
            nazev: jmeno,
            autor: autor || null,
            aktivita: Number(aktivita) || null,
            claude: bezi(pid, 'claude'),
        };
    });
}

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

/** Prostředí vlákna. U webu na serveru míří git do snímků mimo složku webu. */
function prostredi(p) {
    return p.typ === 'server'
        ? ['-e', `GIT_DIR=/snimky/${p.slug}.git`, '-e', `GIT_WORK_TREE=/projekty/${p.slug}/kod`]
        : [];
}

async function zalozitVlakno(p, { nazev: jmeno, autor, navazat }) {
    // Před každým novým vláknem u živého webu snímek souborů a záloha databáze.
    if (p.typ === 'server') {
        const zaloha = await fronta({ akce: 'zaloha', projekt: p.slug });
        if (!zaloha.ok) {
            throw new Error(`Záloha před vláknem se nepovedla, vlákno nezakládám. ${zaloha.vystup || ''}`.trim());
        }
    }

    const slozka = `/projekty/${p.slug}/kod`;
    const prikaz = `bash -lc '${navazat ? 'claude --continue' : 'claude'}; exec bash'`;
    const okno = nazev(jmeno, 'Vlákno');

    let existuje = true;
    try {
        await tmux(['has-session', '-t', `=${p.slug}`]);
    } catch {
        existuje = false;
    }

    const args = existuje
        ? ['new-window', '-d', '-P', '-F', '#{window_id}', '-t', `=${p.slug}:`, '-c', slozka, ...prostredi(p), '-n', okno, prikaz]
        : ['new-session', '-d', '-P', '-F', '#{window_id}', '-s', p.slug, '-c', slozka, ...prostredi(p), '-n', okno, prikaz];

    const id = (await tmux(args)).trim();
    await tmux(['set-option', '-w', '-t', id, '@autor', nazev(autor, '')]);
    // Název drží, i když Claude mění titulek terminálu.
    await tmux(['set-option', '-w', '-t', id, 'automatic-rename', 'off']);

    return id;
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
    // Laravel kóduje ID vlákna (@3 → %403), bez dekódování by se nenašlo.
    let casti;
    try {
        casti = url.pathname.split('/').filter(Boolean).map(decodeURIComponent); // api, projekty, slug, vlakna, id, akce
    } catch {
        return odpoved(res, 400, { chyba: 'Neplatná adresa.' });
    }

    if (req.method === 'GET' && url.pathname === '/api/projekty') {
        const seznam = await Promise.all(projekty().map(async (p) => ({ ...p, vlakna: await vlakna(p.slug) })));
        return odpoved(res, 200, seznam);
    }

    if (req.method === 'POST' && url.pathname === '/api/projekty') {
        const data = await telo(req);
        const vysledek = await fronta({ akce: 'pridat', projekt: data.projekt, typ: data.typ, zdroj: data.zdroj }, 180);
        return odpoved(res, vysledek.ok ? 200 : 422, vysledek);
    }

    const p = casti[0] === 'api' && casti[1] === 'projekty' ? projekt(casti[2]) : null;
    if (!p || casti[3] !== 'vlakna') {
        return odpoved(res, 404, { chyba: 'Projekt neexistuje.' });
    }

    if (req.method === 'POST' && casti.length === 4) {
        const id = await zalozitVlakno(p, await telo(req));
        return odpoved(res, 201, { id });
    }

    // Vlákno musí patřit tomuhle projektu, ID oken jsou společná pro všechny.
    const cil = (await vlakna(p.slug)).find((v) => v.id === casti[4])?.id;
    if (!cil) {
        return odpoved(res, 404, { chyba: 'Vlákno neexistuje.' });
    }

    if (req.method === 'POST' && casti[5] === 'prejmenovat') {
        const data = await telo(req);
        await tmux(['rename-window', '-t', cil, nazev(data.nazev, 'Vlákno')]);
        return odpoved(res, 200, { ok: true });
    }

    if (req.method === 'DELETE' && casti.length === 5) {
        await tmux(['kill-window', '-t', cil]);
        return odpoved(res, 200, { ok: true });
    }

    return odpoved(res, 404, { chyba: 'Neznámá akce.' });
}

const server = http.createServer((req, res) => {
    api(req, res).catch((e) => odpoved(res, 500, { chyba: e.message }));
});

// ---------------------------------------------------------------- terminál

/**
 * Vstupenka: base64url(JSON {p: projekt, v: ID vlákna (@3), u: kdo, exp}).podpis
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

    if (!v || !p || !/^@\d+$/.test(String(v.v))) {
        socket.write('HTTP/1.1 401 Unauthorized\r\n\r\n');
        return socket.destroy();
    }

    wss.handleUpgrade(req, socket, head, (ws) => pripojit(ws, p, v));
});

function pripojit(ws, p, v) {
    // Každé připojení má vlastní sezení ve skupině projektu. Sdílí okna, ale
    // přepnutí vlákna u Toma nepřepne vlákno Pavlovi. Po odpojení zmizí.
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
        cwd: `/projekty/${p.slug}/kod`,
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
