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
 *
 * Každý proces s Claudem (okno, úloha, sezení z aplikace Claude) vede registr
 * sezení: strop souběžných sezení, uspání nečinných, úklid starých verzí
 * a hlídání paměti kontejneru (viz registr sezení níž, docs/AGENT.md).
 */

const http = require('node:http');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { execFile, spawn } = require('node:child_process');
const { WebSocketServer } = require('ws');
const pty = require('node-pty');

const SECRET = process.env.AGENT_SECRET || '';
// Tajemství služby nemá skončit v prostředí konverzací (tmux dědí prostředí
// služby): Claude v okně by jím mohl ovládat okna. Pro `taveo` stačí token API.
delete process.env.AGENT_SECRET;
const PORT = Number(process.env.PORT || 7681);
const HOME = process.env.HOME || '/home/agent';
const REGISTR = process.env.PROJEKTY_TSV || '/etc/taveo-agent/projekty.tsv';
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
        // Příkaz, který okna mění, zahodí sdílený snímek oken (viz vsechnaOkna).
        if (!TMUX_CTENI.has(args[0])) zahoditSnimekOken();
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
// Živý web (server) se v terminálu a v aplikaci Claude upravuje v kopii, na web
// ho pošle tlačítko Zveřejnit (viz kopie webu). Úlohy z ClickUpu jdou rovnou na živý.
// Projekt „bez kopie“ (vývojový web) se upravuje rovnou, jako dřív.
const kopieSlozka = (p) => `/projekty/${p.slug}/kopie`;
const bezKopieSoubor = (p) => `/projekty/${p.slug}/bez-kopie`;
const bezKopie = (p) => p.typ === 'server' && fs.existsSync(bezKopieSoubor(p));
const maKopii = (p) => p.typ === 'server' && !bezKopie(p) && fs.existsSync(path.join(kopieSlozka(p), '.git'));
const pracovni = (p) => (maKopii(p) ? kopieSlozka(p) : slozka(p));

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

/**
 * Okna všech projektů jedním `tmux list-windows -a`, sdílená pro všechny dotazy
 * a nejvýš jednou za TMUX_INTERVAL_MS. Dřív šlo na každý projekt několik
 * příkazů tmuxu a nástroje se ptají každých 5 s: desítky procesů tmuxu za
 * sekundu. Příkaz, který okna mění, snímek zahodí, takže nové okno je vidět hned.
 */
const TMUX_INTERVAL = Number(process.env.TMUX_INTERVAL_MS || 1500);
const TMUX_CTENI = new Set(['list-windows', 'capture-pane', 'display-message', 'has-session']);
let snimekOken = { cas: 0, data: null };
let nacitaniOken = null;
let verzeOken = 0;

function zahoditSnimekOken() {
    verzeOken++;
    snimekOken = { cas: 0, data: null };
    nacitaniOken = null;
}

/** Map: jméno sezení tmuxu → okna. */
async function vsechnaOkna() {
    if (snimekOken.data && Date.now() - snimekOken.cas < TMUX_INTERVAL) return snimekOken.data;

    if (nacitaniOken) return nacitaniOken;

    const nacteni = (async () => {
        const verze = verzeOken;
        const format = ['#{session_name}', '#{window_id}', '#{@session}', '#{@autor}', '#{window_activity}', '#{pane_pid}', '#{@rc}', '#{@trvala}', '#{@uloha}', '#{@rcnastaveni}'].join('\t');
        let vystup = '';
        try {
            vystup = await tmux(['list-windows', '-a', '-F', format]);
        } catch (e) {
            // Bez jediného okna tmux server neběží, to není chyba.
            if (!/no server running|error connecting|no sessions/i.test(e.message)) throw e;
        }

        const data = new Map();
        for (const r of vystup.split('\n').filter(Boolean)) {
            const [sezeni, id, session, autor, aktivita, pid, rc, trvala, uloha, rcnastaveni] = r.split('\t');
            if (!data.has(sezeni)) data.set(sezeni, []);
            data.get(sezeni).push({
                id,
                session: session || null,
                autor: autor || null,
                aktivita: Number(aktivita) || null,
                pid: Number(pid) || null,
                rc: rc === '1',
                trvala: trvala === '1',
                uloha: Boolean(uloha),
                rcnastaveni: rcnastaveni || null,
            });
        }
        if (verze === verzeOken) snimekOken = { cas: Date.now(), data };

        return data;
    })().finally(() => { if (nacitaniOken === nacteni) nacitaniOken = null; });

    nacitaniOken = nacteni;
    return nacteni;
}

/** Okna projektu v tmuxu. */
async function okna(slug) {
    return ((await vsechnaOkna()).get(slug) || []).map((o) => ({
        id: o.id,
        session: o.session,
        autor: o.autor,
        aktivita: o.aktivita,
        claude: bezi(o.pid, 'claude'),
        rc: o.rc,
        trvala: o.trvala,
        uloha: o.uloha,
    }));
}

/** Žije okno (v kterémkoli sezení tmuxu)? */
const oknoZije = async (id) => [...(await vsechnaOkna()).values()].some((seznam) => seznam.some((o) => o.id === id));

/** Okna s konverzacemi, bez okna serverového režimu pro aplikaci Claude. */
const konverzacniOkna = async (slug) => (await okna(slug)).filter((o) => !o.rc);

/**
 * Nové okno v sezení projektu, sezení se případně založí. Když ho mezitím
 * založil někdo jiný (hlídka aplikace, druhá konverzace), přidá se do něj.
 * `velikost` jen pro nové sezení. Vrací ID okna.
 */
async function noveOkno(slug, volby, prikaz, velikost = []) {
    const vRozbehnutem = async () => (await tmux(['new-window', '-d', '-P', '-F', '#{window_id}', '-t', `=${slug}:`, ...volby, prikaz])).trim();
    if ((await okna(slug)).length > 0) return vRozbehnutem();

    try {
        return (await tmux(['new-session', '-d', '-P', '-F', '#{window_id}', '-s', slug, ...velikost, ...volby, prikaz])).trim();
    } catch (e) {
        if (/duplicate session/.test(e.message)) return vRozbehnutem();
        throw e;
    }
}

// ---------------------------------------------------------------- registr sezení

/*
 * Každý proces s Claudem v kontejneru, ať ho spustil kdokoli:
 *
 *   konverzace       okno tmuxu, ve kterém běží Claude (nástroje, úloha grafiky, `agent <projekt>`)
 *   okno             okno, kde Claude skončil a zbyl jen shell
 *   aplikace         serverový režim pro aplikaci Claude (`claude remote-control`, okno na projekt)
 *   aplikace-sezeni  sezení, které si v serverovém režimu založila aplikace Claude
 *                    (`claude --print --sdk-url …`, potomek procesu remote-control)
 *   uloha, ranni     `claude -p` z ClickUpu a ranní přehled
 *
 * Strop SEZENI_MAX počítá všechno kromě oken bez Clauda a serverových režimů.
 * Nad strop se ukončí nejdéle nečinné sezení, jinak se nové odmítne (úloha
 * počká ve frontě). Sezení z aplikace předem odmítnout nejde: nad strop se
 * ukončí, když právě vzniklo. Ukončuje se vždy celý strom procesů (MCP servery,
 * Chrome), nejdřív SIGTERM, po SEZENI_SIGKILL_S sekundách SIGKILL.
 *
 * Aktivita: okno podle výstupu (tmux window_activity) a psaní z terminálu,
 * sezení z aplikace podle spotřeby procesoru (výstup ani vstup nemá).
 */
const SEZENI_MAX = Number(process.env.SEZENI_MAX || 15);
// Dřív USPAT_PO_HODINACH (12 h), nová proměnná má přednost.
const NECINNE_PO = Number(process.env.SEZENI_NECINNE_HODIN || process.env.USPAT_PO_HODINACH || 2) * 3600;
const NECINNE_PRO_STROP = Number(process.env.SEZENI_NECINNE_MIN || 15) * 60;
const STARA_VERZE_NECINNE = Number(process.env.STARA_VERZE_NECINNE_MIN || 10) * 60;
const SIGKILL_PO = Number(process.env.SEZENI_SIGKILL_S || 10);
const HLIDKA_SEZENI = Number(process.env.HLIDKA_SEZENI_S || 60);
const AKTIVITA_CPU = Number(process.env.AKTIVITA_CPU_S || 2) * 100; // v tikách (CLK_TCK = 100)
const PAMET_LIMIT = Number(process.env.PAMET_VAROVANI_PROCENT || 85);
const PAMET_INTERVAL = Number(process.env.PAMET_INTERVAL_S || 15);
const UKLID_OSIRELYCH = process.env.UKLID_OSIRELYCH !== '0';
const VERZE_SLOZKA = path.join(HOME, '.local', 'share', 'claude', 'versions');
const CLAUDE_ODKAZ = path.join(HOME, '.local', 'bin', 'claude');
const POCITANE = new Set(['konverzace', 'aplikace-sezeni', 'uloha', 'ranni']);

let registr = [];
let registrCas = 0;
const vstupy = new Map(); // okno → kdy do něj naposledy někdo psal z terminálu
const sledovane = new Map(); // pid → `claude -p` služby {druh, projekt, autor, start, znami}
const cpuMinule = new Map(); // klíč sezení → {cpu, aktivita}
const koncici = new Map(); // klíč sezení → kdy se začalo ukončovat

const BOOT = (() => {
    try { return Number(fs.readFileSync('/proc/stat', 'utf8').match(/^btime (\d+)$/m)[1]); } catch { return 0; }
})();

/** Jeden proces z /proc/<pid>/stat, nebo null. */
function stat(pid) {
    try {
        const s = fs.readFileSync(`/proc/${pid}/stat`, 'utf8');
        const konec = s.lastIndexOf(')');
        const f = s.slice(konec + 2).split(' ');
        return {
            pid: Number(pid),
            comm: s.slice(s.indexOf('(') + 1, konec),
            zombie: f[0] === 'Z',
            ppid: Number(f[1]),
            pgid: Number(f[2]),
            cpu: Number(f[11]) + Number(f[12]) + Number(f[13]) + Number(f[14]),
            start: Number(f[19]),
        };
    } catch {
        return null;
    }
}

/** Všechny procesy kontejneru (pid → stat + děti). */
function procesy() {
    const mapa = new Map();
    for (const d of fs.readdirSync('/proc')) {
        if (!/^\d+$/.test(d)) continue;
        const p = stat(d);
        if (p) mapa.set(p.pid, { ...p, deti: [] });
    }
    for (const p of mapa.values()) mapa.get(p.ppid)?.deti.push(p.pid);

    return mapa;
}

/** Proces a všichni jeho potomci (pid). */
function potomci(mapa, pid) {
    if (!mapa.has(pid)) return [];
    const vysledek = [];
    const fronta = [pid];
    while (fronta.length) {
        const dalsi = fronta.shift();
        vysledek.push(dalsi);
        fronta.push(...(mapa.get(dalsi)?.deti || []));
    }

    return vysledek;
}

const zacatek = (p) => Math.floor(BOOT + p.start / 100);
const cpuStromu = (mapa, pid) => potomci(mapa, pid).reduce((soucet, d) => soucet + (mapa.get(d)?.cpu || 0), 0);
const argv = (pid) => { try { return fs.readFileSync(`/proc/${pid}/cmdline`, 'utf8').split('\0').filter(Boolean); } catch { return []; } };

/** Verze Clauda, ze které proces běží (~/.local/share/claude/versions/<verze>), jinak null. */
function verzeProcesu(pid) {
    try {
        const exe = fs.readlinkSync(`/proc/${pid}/exe`).replace(/ \(deleted\)$/, '');
        return path.dirname(exe) === VERZE_SLOZKA ? path.basename(exe) : null;
    } catch {
        return null;
    }
}

/** Verze, na kterou teď míří `claude` (po automatické aktualizaci nová). */
function aktualniVerze() {
    try { return path.basename(fs.realpathSync(CLAUDE_ODKAZ)); } catch { return null; }
}

const starsi = (a, b) => {
    const x = a.split('.').map(Number);
    const y = b.split('.').map(Number);
    for (let i = 0; i < Math.max(x.length, y.length); i++) {
        if ((x[i] || 0) !== (y[i] || 0)) return (x[i] || 0) < (y[i] || 0);
    }
    return false;
};

/** Sezení bez výstupu má aktivitu podle procesoru: přibylo od minula aspoň AKTIVITA_CPU? */
function aktivitaPodleCpu(klic, cpu, start, ted) {
    const minule = cpuMinule.get(klic);
    const aktivita = !minule ? start : cpu - minule.cpu >= AKTIVITA_CPU ? ted : minule.aktivita;
    // Výchozí bod se posouvá jen s aktivitou, jinak by se pomalé přírůstky nesečetly.
    cpuMinule.set(klic, { cpu: !minule || aktivita === ted ? cpu : minule.cpu, aktivita });

    return aktivita;
}

const hodnota = (args, prepinac) => {
    const i = args.indexOf(prepinac);
    return i >= 0 ? args[i + 1] || null : null;
};

/** Projde okna tmuxu, serverové režimy a úlohy služby. Vrací seznam sezení. */
async function obnovitRegistr() {
    const mapa = procesy();
    const ted = Math.floor(Date.now() / 1000);
    const slugy = new Set(projekty().map((p) => p.slug));
    const seznam = [];

    for (const [slug, seznamOken] of await vsechnaOkna()) {
        // Sezení připojených terminálů (<projekt>-w…) sdílí okna projektu, počítají se jednou.
        if (!slugy.has(slug)) continue;

        for (const o of seznamOken) {
            const pane = mapa.get(o.pid);
            const claude = (pane?.deti || []).map((d) => mapa.get(d)).find((d) => d?.comm === 'claude');
            const zaklad = {
                projekt: slug,
                tmux: `${slug}:${o.id}`,
                okno: o.id,
                pane: o.pid,
                pid: claude?.pid ?? o.pid,
                pgid: pane?.pgid ?? null,
                autor: o.autor,
                session: o.session,
                trvala: o.trvala,
                uloha: o.uloha,
                start: pane ? zacatek(pane) : null,
                verze: claude ? verzeProcesu(claude.pid) : null,
            };

            if (!o.rc) {
                const aktivita = Math.max(o.aktivita || 0, vstupy.get(o.id) || 0) || zaklad.start;
                seznam.push({ ...zaklad, klic: `okno:${o.id}:${o.pid}`, druh: claude ? 'konverzace' : 'okno', aktivita });
                continue;
            }

            seznam.push({ ...zaklad, klic: `okno:${o.id}:${o.pid}`, druh: 'aplikace', aktivita: o.aktivita, nastaveni: o.rcnastaveni });
            for (const w of (claude?.deti || []).map((d) => mapa.get(d)).filter(Boolean)) {
                const args = argv(w.pid);
                if (!args.includes('--sdk-url')) continue;
                const klic = `aplikace:${w.pid}:${w.start}`;
                seznam.push({
                    klic,
                    druh: 'aplikace-sezeni',
                    projekt: slug,
                    tmux: zaklad.tmux,
                    okno: null,
                    pid: w.pid,
                    pgid: w.pgid,
                    autor: 'aplikace Claude',
                    session: hodnota(args, '--session-id'),
                    trvala: false,
                    uloha: false,
                    start: zacatek(w),
                    aktivita: aktivitaPodleCpu(klic, cpuStromu(mapa, w.pid), zacatek(w), ted),
                    verze: verzeProcesu(w.pid),
                });
            }
        }
    }

    for (const [pid, s] of sledovane) {
        const p = mapa.get(pid);
        if (!p || p.start !== s.start) continue;
        // Potomci se pamatují: po konci úlohy už k nim od ní nevede cesta (viz ukoncitStrom).
        s.znami = potomci(mapa, pid).filter((d) => d !== pid).map((d) => ({ pid: d, start: mapa.get(d).start }));
        const klic = `pid:${pid}`;
        seznam.push({
            klic,
            druh: s.druh,
            projekt: s.projekt,
            tmux: null,
            okno: null,
            pid,
            pgid: p.pgid,
            autor: s.autor,
            session: s.session,
            trvala: false,
            uloha: true,
            start: zacatek(p),
            aktivita: aktivitaPodleCpu(klic, cpuStromu(mapa, pid), zacatek(p), ted),
            verze: verzeProcesu(pid),
        });
    }

    const klice = new Set(seznam.map((s) => s.klic));
    const oknaTed = new Set(seznam.map((s) => s.okno).filter(Boolean));
    for (const k of cpuMinule.keys()) if (!klice.has(k)) cpuMinule.delete(k);
    for (const k of vstupy.keys()) if (!oknaTed.has(k)) vstupy.delete(k);
    for (const k of adresyAplikace.keys()) if (!klice.has(`okno:${k}`)) adresyAplikace.delete(k);

    registr = seznam;
    registrCas = Date.now();
    return seznam;
}

const pocita = (s) => POCITANE.has(s.druh) && !koncici.has(s.klic);
// Úlohy mají vlastní časový limit, běžící úloha v okně (grafika) taky.
const lzeUkoncit = (s) => ['konverzace', 'okno', 'aplikace-sezeni'].includes(s.druh) && !s.uloha && !koncici.has(s.klic);
const necinnost = (s, ted = Math.floor(Date.now() / 1000)) => ted - (s.aktivita || s.start || ted);
// Nepřipnuté dřív, pak nejdéle nečinné.
const poradiUkonceni = (a, b) => (a.trvala - b.trvala) || (necinnost(b) - necinnost(a));

/**
 * Ukončí proces se všemi potomky a se skupinami procesů, které v tom stromu
 * začínají (okno: celá skupina shellu, Chrome z Playwrightu má vlastní).
 * `znami` jsou dřív viděné potomci (úloha, která už skončila, k nim cestu nemá).
 * U okna nejdřív `kill-window`. SIGTERM, a co do SIGKILL_PO sekund nezmizí, SIGKILL.
 */
async function ukoncitStrom(koren, { okno = null, znami = [], skupina = null } = {}) {
    const mapa = procesy();
    const pids = new Set(potomci(mapa, koren));
    for (const z of znami) {
        if (mapa.get(z.pid)?.start === z.start) potomci(mapa, z.pid).forEach((d) => pids.add(d));
    }
    const skupiny = new Set([...pids].map((pid) => mapa.get(pid)?.pgid).filter((g) => pids.has(g)));
    if (skupina) skupiny.add(skupina);
    for (const p of mapa.values()) if (skupiny.has(p.pgid)) potomci(mapa, p.pid).forEach((d) => pids.add(d));

    // Nikdy ne tini, služba, entrypoint a server tmuxu.
    for (const p of mapa.values()) {
        if (p.pid === 1 || p.pid === process.pid || p.pid === process.ppid || p.comm.startsWith('tmux')) pids.delete(p.pid);
    }

    const starty = new Map([...pids].map((pid) => [pid, mapa.get(pid).start]));
    const posli = (seznam, signal) => { for (const pid of seznam) { try { process.kill(pid, signal); } catch { /* už skončil */ } } };
    const zbyva = () => {
        const ted = procesy();
        const ziji = new Set();
        for (const [pid, start] of starty) {
            if (ted.get(pid)?.start === start) potomci(ted, pid).forEach((d) => ziji.add(d));
        }
        // Zombie už neběží, jen ho ještě nesebral rodič.
        for (const pid of ziji) if (ted.get(pid)?.zombie) ziji.delete(pid);
        for (const p of ted.values()) {
            if (p.pid === 1 || p.pid === process.pid || p.pid === process.ppid || p.comm.startsWith('tmux')) ziji.delete(p.pid);
        }
        return ziji;
    };

    if (okno) await tmux(['kill-window', '-t', okno]).catch(() => {});
    posli(pids, 'SIGTERM');

    for (let i = 0; i < SIGKILL_PO * 4; i++) {
        await new Promise((r) => setTimeout(r, 250));
        if (zbyva().size === 0) return pids.size;
    }

    const zbytek = zbyva();
    if (zbytek.size) {
        console.log(`${new Date().toISOString()} SIGKILL ${zbytek.size} procesům (${[...zbytek].slice(0, 10).join(' ')})`);
        posli(zbytek, 'SIGKILL');
    }
    return pids.size;
}

/** Ukončí sezení z registru (okno, sezení z aplikace, úlohu) i se stromem procesů. */
async function ukoncitSezeni(s, duvod) {
    if (koncici.has(s.klic)) return;
    koncici.set(s.klic, Date.now());
    console.log(`${new Date().toISOString()} ukončuji ${s.druh} ${s.tmux || s.projekt} (pid ${s.pid}, ${s.session || 'bez konverzace'}, verze ${s.verze || '?'}, nečinné ${Math.round(necinnost(s) / 60)} min): ${duvod}`);

    try {
        const pocet = await ukoncitStrom(s.okno ? s.pane : s.pid, { okno: s.okno, znami: s.znami || [] });
        console.log(`${new Date().toISOString()} ukončeno ${s.tmux || s.projekt}: ${pocet} procesů`);
    } finally {
        setTimeout(() => koncici.delete(s.klic), 60_000);
    }
}

/** Okno podle ID (Uspat, výměna serverového režimu…) i s procesy, které v něm běží. */
async function ukoncitOkno(id) {
    const okno = [...(await vsechnaOkna()).values()].flat().find((o) => o.id === id);
    if (!okno?.pid) return tmux(['kill-window', '-t', id]).catch(() => {});

    return ukoncitStrom(okno.pid, { okno: id });
}

/**
 * Místo pro nové sezení. Pod stropem hned, nad ním ukončí nejdéle nečinné
 * (nečinné aspoň SEZENI_NECINNE_MIN, připnuté ne). Vrací {ok, pocet}.
 * `vynechat` = okno, které se vzápětí zavře (prázdná konverzace při navázání).
 */
async function uvolnitMisto({ vynechat = null } = {}) {
    const pocitane = (await obnovitRegistr()).filter((s) => pocita(s) && !(vynechat && s.okno === vynechat));
    if (pocitane.length < SEZENI_MAX) return { ok: true, pocet: pocitane.length };

    const kandidat = pocitane.filter((s) => lzeUkoncit(s) && !s.trvala && necinnost(s) >= NECINNE_PRO_STROP).sort(poradiUkonceni)[0];
    if (!kandidat) return { ok: false, pocet: pocitane.length };

    ukoncitSezeni(kandidat, `strop ${SEZENI_MAX} sezení, místo pro nové`).catch((e) => console.error(e.message));
    return { ok: true, pocet: pocitane.length };
}

const plnoChyba = (pocet) => Object.assign(new Error(
    `Na serveru teď běží ${pocet} sezení s Claudem (strop je ${SEZENI_MAX}) a žádné není nečinné. `
    + 'Uspěte konverzaci, kterou už nepotřebujete, nebo to zkuste za pár minut.',
), { status: 429 });

/**
 * Hlídka sezení (při startu a každých HLIDKA_SEZENI_S): nečinná, starší verze
 * Clauda, strop, osiřelé procesy a úlohy, které čekají na místo.
 */
let hlidkaBezi = false;

async function hlidatSezeni() {
    if (hlidkaBezi) return;
    hlidkaBezi = true;

    try {
        const reg = await obnovitRegistr();
        const verze = aktualniVerze();
        const ukoncit = (s, duvod) => ukoncitSezeni(s, duvod).catch((e) => console.error(e.message));

        // Nečinná. Připnuté konverzace ne (běží pořád kvůli aplikaci Claude).
        for (const s of reg.filter((x) => lzeUkoncit(x) && !x.trvala && necinnost(x) >= NECINNE_PO)) {
            ukoncit(s, `nečinné ${Math.round(necinnost(s) / 60)} min`);
        }

        // Starší verze Clauda (běží dál i po automatické aktualizaci). Jen nečinná,
        // rozdělanou práci to nepřeruší. Připnutá konverzace se hned probudí v nové verzi.
        for (const s of reg.filter((x) => lzeUkoncit(x) && verze && x.verze && starsi(x.verze, verze) && necinnost(x) >= STARA_VERZE_NECINNE)) {
            const znovu = s.trvala && s.session && s.druh === 'konverzace' ? projekt(s.projekt) : null;
            ukoncitSezeni(s, `starší verze ${s.verze}, aktuální ${verze}`)
                .then(() => znovu && probuditPripnutou(znovu, s))
                .catch((e) => console.error(`${s.tmux} (${s.session}): ${e.message}`));
        }

        // Serverový režim ze starší verze, se starým nastavením nebo u projektu, který ho mít nemá:
        // až jsou jeho sezení nečinná, skončí. Hlídka aplikace ho spustí znovu (nebo ne).
        for (const s of reg.filter((x) => x.druh === 'aplikace' && !koncici.has(x.klic))) {
            const duvod = (verze && s.verze && starsi(s.verze, verze) && `starší verze ${s.verze}, aktuální ${verze}`)
                || (!aplikaceProProjekt(s.projekt) && 'projekt serverový režim mít nemá (APLIKACE_PROJEKTY)')
                || (s.nastaveni !== RC_NASTAVENI && 'staré nastavení serverového režimu');
            if (!duvod) continue;
            const sezeniAplikace = reg.filter((w) => w.druh === 'aplikace-sezeni' && w.tmux === s.tmux);
            if (sezeniAplikace.some((w) => necinnost(w) < STARA_VERZE_NECINNE)) continue;
            ukoncitSezeni(s, duvod).then(() => hlidatAplikaci()).catch((e) => console.error(e.message));
        }

        await drzetStrop();
        if (UKLID_OSIRELYCH) uklidOsirelych();
        spustitDalsi();
    } finally {
        hlidkaBezi = false;
    }
}

/** Nad stropem (sezení z aplikace vznikají bez ptaní): nejdéle nečinná, jinak právě vzniklá z aplikace. */
async function drzetStrop() {
    const ted = Math.floor(Date.now() / 1000);
    const pocitane = (await obnovitRegistr()).filter(pocita);
    let navic = pocitane.length - SEZENI_MAX;
    if (navic <= 0) return;

    const ukoncit = (s, duvod) => { navic--; ukoncitSezeni(s, duvod).catch((e) => console.error(e.message)); };
    for (const s of pocitane.filter((x) => lzeUkoncit(x) && !x.trvala && necinnost(x) >= NECINNE_PRO_STROP).sort(poradiUkonceni)) {
        if (navic <= 0) return;
        ukoncit(s, `strop ${SEZENI_MAX} sezení, běží ${pocitane.length}`);
    }
    for (const s of pocitane.filter((x) => x.druh === 'aplikace-sezeni' && ted - x.start < 5 * 60).sort((a, b) => b.start - a.start)) {
        if (navic <= 0) return;
        ukoncit(s, `strop ${SEZENI_MAX} sezení, nové sezení z aplikace Claude odmítnuto`);
    }
    if (navic > 0) console.warn(`${new Date().toISOString()} strop: běží ${pocitane.length} sezení (strop ${SEZENI_MAX}), žádné nejde ukončit`);
}

/** Připnutá konverzace ukončená kvůli staré verzi: znovu otevřít (--resume) a připnout. */
async function probuditPripnutou(p, s) {
    const okno = await zalozit(p, { autor: s.autor, session: s.session });
    await tmux(['set-option', '-w', '-t', okno, '@trvala', '1']);
    console.log(`${new Date().toISOString()} připnutá konverzace ${s.session} znovu v nové verzi (${p.slug}:${okno})`);
}

/**
 * Procesy, kterým umřel rodič (přešly pod tini, PID 1): Claude, Playwright MCP
 * a Chrome. Nikdo je neukončí a nikdo je nepoužívá. Claude až po limitu úlohy
 * (`claude -p` po restartu služby ještě dobíhá), ostatní po dvou minutách.
 */
function uklidOsirelych() {
    const mapa = procesy();
    const ted = Math.floor(Date.now() / 1000);

    for (const p of mapa.values()) {
        if (p.ppid !== 1 || p.pid === process.ppid || p.comm.startsWith('tmux')) continue;

        const druh = p.comm === 'claude' || verzeProcesu(p.pid) ? 'claude'
            : /^(chrome|chromium|headless_shell)/.test(p.comm) ? 'prohlížeč'
                : p.comm === 'node' && argv(p.pid).some((a) => a.includes('playwright-mcp')) ? 'playwright-mcp' : null;
        if (!druh || ted - zacatek(p) < (druh === 'claude' ? ULOHA_LIMIT_MIN * 60 : 120)) continue;

        console.log(`${new Date().toISOString()} osiřelý proces ${druh} (pid ${p.pid}, ${Math.round((ted - zacatek(p)) / 60)} min): ukončuji`);
        ukoncitStrom(p.pid).catch((e) => console.error(e.message));
    }
}

/** Paměť cgroupy kontejneru. Procento z pracovní sady (bez page cache, tu jádro uvolní samo). */
function pametKontejneru() {
    const cist = (f) => { try { return fs.readFileSync(`/sys/fs/cgroup/${f}`, 'utf8').trim(); } catch { return null; } };
    const current = Number(cist('memory.current')) || null;
    const max = /^\d+$/.test(cist('memory.max') || '') ? Number(cist('memory.max')) : null;
    const cache = Number((cist('memory.stat') || '').match(/^inactive_file (\d+)$/m)?.[1] || 0);
    const pracovni = current === null ? null : Math.max(0, current - cache);

    return {
        current,
        max,
        pracovni_sada: pracovni,
        procent: max && pracovni !== null ? Math.round((pracovni / max) * 1000) / 10 : null,
        procent_current: max && current !== null ? Math.round((current / max) * 1000) / 10 : null,
    };
}

/** Nad PAMET_VAROVANI_PROCENT: varování a ukončit nejdéle nečinné sezení (i připnuté, až když jiné není). */
let pametUkonceno = 0;
let pametVarovano = 0;

async function hlidatPamet() {
    const m = pametKontejneru();
    if (m.procent === null || m.procent < PAMET_LIMIT) return;

    if (Date.now() - pametVarovano > 60_000) {
        console.warn(`${new Date().toISOString()} VAROVÁNÍ: paměť kontejneru ${m.procent} % (${Math.round(m.pracovni_sada / 2 ** 20)} MB z ${Math.round(m.max / 2 ** 20)} MB, s cache ${m.procent_current} %)`);
        pametVarovano = Date.now();
    }
    // Ukončené sezení paměť uvolní až za chvíli, další až po půl minutě.
    if (Date.now() - pametUkonceno < 30_000) return;

    const s = (await obnovitRegistr()).filter(lzeUkoncit).sort(poradiUkonceni)[0];
    if (!s) {
        console.warn(`${new Date().toISOString()} paměť: žádné sezení nejde ukončit`);
        return;
    }
    pametUkonceno = Date.now();
    ukoncitSezeni(s, `paměť kontejneru ${m.procent} %`).catch((e) => console.error(e.message));
}

/** Počet procesů v kontejneru a strop PIDs cgroupy. */
function pocetProcesu() {
    const cist = (f) => { try { return fs.readFileSync(`/sys/fs/cgroup/${f}`, 'utf8').trim(); } catch { return null; } };
    const max = cist('pids.max');

    return {
        procesy: fs.readdirSync('/proc').filter((d) => /^\d+$/.test(d)).length,
        pids: Number(cist('pids.current')) || null,
        pids_max: /^\d+$/.test(max || '') ? Number(max) : null,
    };
}

/** Souhrn pro GET /health (bez přihlášení, proto jen čísla, žádné projekty ani konverzace). */
async function zdravi() {
    const reg = Date.now() - registrCas < 5000 ? registr : await obnovitRegistr();
    const podleDruhu = {};
    for (const s of reg) podleDruhu[s.druh] = (podleDruhu[s.druh] || 0) + 1;

    return {
        ok: true,
        sezeni: reg.filter(pocita).length,
        strop: SEZENI_MAX,
        podle_druhu: podleDruhu,
        ...pocetProcesu(),
        pamet: pametKontejneru(),
        verze_claude: aktualniVerze(),
    };
}

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
            if (info.cwd !== slozka(p) && info.cwd !== kopieSlozka(p)) continue;

            vysledek.push({ session, nazev: info.nazev, cwd: info.cwd, aktualizace: Math.floor(fs.statSync(soubor).mtimeMs / 1000) });
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
            uloha: okno?.uloha || false,
            nova: false,
        };
    });

    // Okno, ve kterém ještě nikdo nic nenapsal, na disku konverzaci nemá.
    for (const o of bezici.filter((o) => !disk.some((k) => k.session === o.session))) {
        seznam.push({ session: o.session, nazev: 'Nová konverzace', aktualizace: o.aktivita, autor: o.autor, okno: o.id, trvala: o.trvala, claude: o.claude, uloha: o.uloha, nova: true });
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
function prostredi(p, cwd = slozka(p)) {
    const nazevVAplikaci = ['-e', `CLAUDE_REMOTE_CONTROL_SESSION_NAME_PREFIX=${p.slug}`];

    // Na živém webu git ukazuje snímky, kopie má vlastní git (co se změnilo).
    return p.typ === 'server' && cwd === slozka(p)
        ? [...nazevVAplikaci, '-e', `GIT_DIR=/snimky/${p.slug}.git`, '-e', `GIT_WORK_TREE=/projekty/${p.slug}/kod`]
        : nazevVAplikaci;
}

async function zalozit(p, { autor, session, nahradit, uloha }) {
    const bezici = await konverzacniOkna(p.slug);

    if (session) {
        if (!UUID.test(String(session)) || !ulozene(p).some((k) => k.session === session)) {
            throw Object.assign(new Error('Konverzace neexistuje.'), { status: 404 });
        }

        const otevrene = bezici.find((o) => o.session === session);
        if (otevrene) return otevrene.id;
    }

    const misto = await uvolnitMisto({ vynechat: OKNO.test(nahradit || '') ? nahradit : null });
    if (!misto.ok) throw plnoChyba(misto.pocet);

    // Živý web: konverzace běží v kopii. Když ještě není, založí se (pár vteřin).
    // Když to nejde (server ještě nemá agent kopie-db) nebo je projekt bez kopie,
    // postaru na živém webu, a před tím snímek souborů a záloha databáze.
    if (p.typ === 'server' && !maKopii(p) && !bezKopie(p)) {
        await pripravitKopii(p).catch((e) => console.error(`kopie ${p.slug}: ${e.message}`));
    }
    if (p.typ === 'server' && !maKopii(p)) {
        const zaloha = await fronta({ akce: 'zaloha', projekt: p.slug });
        if (!zaloha.ok) {
            throw new Error(`Záloha webu se nepovedla, konverzaci nezakládám. ${zaloha.vystup || ''}`.trim());
        }
    }

    // Uložená konverzace se probouzí ve své složce (úloha grafiky u živého webu
    // běžela ve složce webu, ne v kopii), jinak ji `--resume` nenajde.
    const cwd = (session && ulozene(p).find((k) => k.session === session)?.cwd) || pracovni(p);
    pripravitClaude(cwd);

    const id = session || crypto.randomUUID();
    // Probuzení konverzace úlohy (grafika): s háčkem Stop, ať další výsledek dojde na web.
    const pokracuje = session && Number.isInteger(uloha?.id) && typeof uloha.konec === 'string' ? { id: uloha.id, konec: uloha.konec } : null;
    const soubor = (pripona) => path.join(ULOHY_LOG, `${pokracuje?.id}.${pripona}`);
    if (pokracuje) {
        fs.mkdirSync(ULOHY_LOG, { recursive: true });
        fs.rmSync(soubor('stop'), { force: true });
        fs.writeFileSync(soubor('nastaveni.json'), JSON.stringify({
            hooks: { Stop: [{ hooks: [{ type: 'command', command: `cat > ${soubor('stop')}` }] }] },
        }));
    }
    const prikaz = `bash -lc 'claude ${session ? '--resume' : '--session-id'} ${id}${pokracuje ? ` --settings ${soubor('nastaveni.json')}` : ''}; exec bash'`;
    const okno = await noveOkno(p.slug, ['-c', cwd, ...prostredi(p, cwd), '-n', 'konverzace'], prikaz);
    await tmux(['set-option', '-w', '-t', okno, '@session', id]);
    await tmux(['set-option', '-w', '-t', okno, '@autor', radek(autor, 60)]);
    await tmux(['set-option', '-w', '-t', okno, 'automatic-rename', 'off']);

    if (pokracuje) {
        pokracovani(pokracuje, okno, soubor, (stop) => String(stop?.last_assistant_message || '').includes(pokracuje.konec));
    }

    if (!session && autor) {
        const autori = cteniJson(AUTORI, {});
        autori[id] = radek(autor, 60);
        zapisJson(AUTORI, autori);
    }

    // Navázání z prázdné nové konverzace: tu prázdnou zavřít, ať nezůstává viset.
    if (nahradit && OKNO.test(nahradit)) {
        const stare = bezici.find((o) => o.id === nahradit);
        if (stare && !ulozene(p).some((k) => k.session === stare.session)) {
            await ukoncitOkno(stare.id).catch(() => {});
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
 *   *.html …      hotové kódy a texty k vložení (Shoptet, Upgates)
 *
 * U git projektu je hotová práce větev agent/<jméno> ve schránce (odeslat.git)
 * a nástroje ji zveřejní přes GitHub. Agent sám do main pořád nemůže.
 * U projektu bez kódu je hotová práce každá složka v predani/. Živý web
 * kartu nemá (úprav je moc a jsou hned venku).
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

/** Popis, snímky a kódy ze složky předání (každá část volitelná). */
function podklady(slozka) {
    const cist = (soubor, limit) => { try { return fs.readFileSync(path.join(slozka, soubor), 'utf8').slice(0, limit); } catch { return null; } };
    let soubory = [];
    try { soubory = fs.readdirSync(slozka).sort(); } catch { /* bez podkladů */ }

    return {
        popis: cist('popis.md', 20000),
        snimky: soubory.filter((s) => SNIMEK.test(s)),
        kody: soubory.filter((s) => KOD.test(s) && s !== 'popis.md').slice(0, 20).map((s) => ({ soubor: s, obsah: cist(s, 200000) })),
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

/** Projekt bez kódu: hotová práce = složky v predani/, nejnovější první. */
function predaniSlozky(p) {
    const koren = path.join('/projekty', p.slug, 'predani');
    let jmena = [];
    try { jmena = fs.readdirSync(koren, { withFileTypes: true }).filter((d) => d.isDirectory() && VETEV.test(d.name)).map((d) => d.name); } catch { return []; }

    return jmena.map((jmeno) => {
        const slozka = path.join(koren, jmeno);
        const stat = fs.statSync(slozka);
        const pod = podklady(slozka);
        const nadpis = (pod.popis || '').match(/^#\s+(.+)$/m)?.[1]?.trim() || jmeno;

        return { jmeno, cas: Math.floor(stat.mtimeMs / 1000), nadpis, ...pod };
    }).sort((a, b) => b.cas - a.cas);
}

// ---------------------------------------------------------------- okamžité zveřejnění

/**
 * Tlačítko Zveřejnit u webu, který běží na tomhle serveru (taveo): bez GitHubu
 * a bez testů, na webu do pár sekund. Git je jen historie, doplní ho web potom.
 *
 *   1. vydání: commit větve agenta (ten, který člověk viděl) spojený s tím,
 *      co je teď na webu, a s main z GitHubu, sestavený (vendor bez dev
 *      balíčků, npm run build). Složka
 *      /projekty/<projekt>/vydani/<strom>, strom = git tree spojení, takže
 *      stejné spojení se sestaví jen jednou. Sestavuje se předem, jakmile se
 *      úprava objeví v nástrojích, po kliknutí už jen nahrání.
 *   2. nahrání dělá root (fronta → agent nasadit): záloha databáze, předchozí
 *      verze stranou (Vrátit), rsync, migrace, cache.
 *
 * Co je na webu, si služba pamatuje sama (refs/web/aktualni: strom webu,
 * rodič = main, ze kterého vznikl). Historie na GitHubu dobíhá až po nahrání,
 * takže samotný main nestačí: úprava zveřejněná před chvílí, nebo taková,
 * která se do historie nedostala, by z webu zmizela.
 *
 * Konflikt s main se nesestaví, nástroje řeknou, ať agent sloučí main do větve.
 */
const VYDANI_PROJEKTY = ['taveo'];
const VYDANI_DOMOV = path.join(HOME, '.taveo-vydani');
const VYDANI_ULOHY = path.join(VYDANI_DOMOV, 'ulohy');
const VYDANI_PONECHAT = 3; // jedno má ~190 MB
const CACHE_PONECHAT = 2; // vendor a node_modules podle zámku, od každého
const vydaniSlozka = (p) => path.join('/projekty', p.slug, 'vydani');
const sestavovani = new Map(); // strom → Promise, ať se stejné vydání nesestavuje dvakrát

const prikaz = (cmd, args, cwd) => new Promise((resolve, reject) => {
    execFile(cmd, args, { cwd, timeout: 300000, maxBuffer: 20 * 1024 * 1024, env: { ...process.env, COMPOSER_NO_INTERACTION: '1' } }, (error, stdout, stderr) => {
        error ? reject(new Error((stderr || stdout || error.message).trim().split('\n').slice(-5).join('\n'))) : resolve(stdout.trim());
    });
});

const hashSouboru = (soubor) => crypto.createHash('sha256').update(fs.readFileSync(soubor)).digest('hex').slice(0, 16);

const vydaniRepo = (p) => path.join(VYDANI_DOMOV, `${p.slug}.git`);
const konfliktniSoubory = (e) => e.message.split('\n').filter((r) => /^[\w./-]+$/.test(r)).join(', ') || 'konflikt';

async function stahnoutMain(p, repo) {
    const origin = await prikaz('git', ['-C', slozka(p), 'remote', 'get-url', 'origin']);
    await prikaz('git', ['--git-dir', repo, 'fetch', '-q', origin, '+refs/heads/main:refs/heads/main']);
}

/** Commit verze, která je na webu (refs/web/aktualni), nebo null. */
async function naWebu(repo, ref = 'refs/web/aktualni') {
    return prikaz('git', ['--git-dir', repo, 'rev-parse', '-q', '--verify', `${ref}^{commit}`]).catch(() => null);
}

/** Zapamatuje si strom, který je teď na webu. Rodič = main, ze kterého vznikl. */
async function zapamatovatWeb(repo, strom, main, zprava) {
    const commit = await prikaz('git', ['-c', 'user.name=Služba agenta', '-c', 'user.email=agent@taveo.cz',
        '--git-dir', repo, 'commit-tree', strom, '-p', main, '-m', zprava]);
    await prikaz('git', ['--git-dir', repo, 'update-ref', 'refs/web/aktualni', commit]);
}

/**
 * Spojení commitu `sha` z větve agenta s tím, co je na webu, a s main
 * z GitHubu. Vrací strom a main.
 */
async function spojit(p, sha) {
    const repo = vydaniRepo(p);
    if (!fs.existsSync(repo)) {
        await prikaz('git', ['init', '-q', '--bare', repo]);
    }
    await stahnoutMain(p, repo);
    await prikaz('git', ['--git-dir', repo, 'fetch', '-q', schranka(p), sha]);
    const main = await prikaz('git', ['--git-dir', repo, 'rev-parse', 'main']);

    // Web = main, ze kterého vznikl, plus úpravy zveřejněné tlačítkem. K tomu
    // všechno, co do main mezitím přibylo (i ty samé úpravy, až dorazí do historie).
    let zaklad = main;
    const web = await naWebu(repo);
    if (web) {
        let strom;
        try {
            strom = (await prikaz('git', ['--git-dir', repo, 'merge-tree', '--write-tree', '--name-only', `--merge-base=${web}^1`, web, main])).split('\n')[0];
        } catch (e) {
            throw new Error(`To, co je teď na webu, nejde spojit s historií na GitHubu (${konfliktniSoubory(e)}). Ozvěte se Tomovi.`);
        }
        zaklad = await prikaz('git', ['-c', 'user.name=Služba agenta', '-c', 'user.email=agent@taveo.cz',
            '--git-dir', repo, 'commit-tree', strom, '-p', main, '-p', web, '-m', 'Web a main']);
    }

    let strom;
    try {
        strom = (await prikaz('git', ['--git-dir', repo, 'merge-tree', '--write-tree', '--name-only', zaklad, sha])).split('\n')[0];
    } catch (e) {
        throw Object.assign(new Error(`Úprava se srazí s tím, co je mezitím na webu: ${konfliktniSoubory(e)}`), { konflikt: true });
    }

    return { repo, strom, main };
}

/** Balíčky podle zámku (composer.lock, package-lock.json), sdílené mezi vydáními. */
async function cacheBalicku(druh, zdroj) {
    const zamek = druh === 'vendor' ? 'composer.lock' : 'package-lock.json';
    const cil = path.join(VYDANI_DOMOV, 'cache', `${druh}-${hashSouboru(path.join(zdroj, zamek))}`);
    const hotovo = path.join(cil, druh === 'vendor' ? 'vendor' : 'node_modules');
    if (fs.existsSync(hotovo)) {
        return hotovo;
    }

    const docasna = `${cil}.tmp-${process.pid}-${Date.now()}`;
    fs.mkdirSync(docasna, { recursive: true });
    if (druh === 'vendor') {
        for (const f of ['composer.json', 'composer.lock']) fs.copyFileSync(path.join(zdroj, f), path.join(docasna, f));
        await prikaz('composer', ['install', '--no-dev', '--no-scripts', '--no-autoloader', '--prefer-dist', '--ignore-platform-reqs', '-q'], docasna);
    } else {
        for (const f of ['package.json', 'package-lock.json']) fs.copyFileSync(path.join(zdroj, f), path.join(docasna, f));
        await prikaz('npm', ['ci', '--no-audit', '--no-fund', '--loglevel=error'], docasna);
    }
    fs.rmSync(cil, { recursive: true, force: true });
    fs.renameSync(docasna, cil);

    // Starší zámky pryč, každý má přes 100 MB.
    const koren = path.dirname(cil);
    const stare = fs.readdirSync(koren).filter((d) => d.startsWith(`${druh}-`) && !d.includes('.tmp-'))
        .map((d) => ({ d, t: fs.statSync(path.join(koren, d)).mtimeMs })).sort((a, b) => b.t - a.t).slice(CACHE_PONECHAT);
    for (const { d } of stare) fs.rmSync(path.join(koren, d), { recursive: true, force: true });

    return hotovo;
}

/** Sestaví vydání (nebo vrátí hotové). Vrací {strom, slozka, main}. */
async function sestavitVydani(p, sha) {
    const { repo, strom, main } = await spojit(p, sha);
    const cil = path.join(vydaniSlozka(p), strom);
    if (fs.existsSync(path.join(cil, '.vydani.json'))) {
        return { strom, slozka: cil, main };
    }
    if (sestavovani.has(strom)) {
        return sestavovani.get(strom);
    }

    const prace = (async () => {
        const docasna = `${cil}.tmp-${Date.now()}`;
        fs.mkdirSync(docasna, { recursive: true });
        try {
            await prikaz('bash', ['-c', 'git --git-dir="$1" archive "$2" | tar -x -C "$3"', '_', repo, strom, docasna]);
            await prikaz('cp', ['-a', await cacheBalicku('vendor', docasna), path.join(docasna, 'vendor')]);
            await prikaz('composer', ['dump-autoload', '--no-dev', '--optimize', '-q'], docasna);
            fs.symlinkSync(await cacheBalicku('node', docasna), path.join(docasna, 'node_modules'));
            await prikaz('npx', ['vite', 'build', '--logLevel', 'error'], docasna);
            fs.unlinkSync(path.join(docasna, 'node_modules'));
            fs.writeFileSync(path.join(docasna, '.vydani.json'), JSON.stringify({ strom, sha, main, cas: Math.floor(Date.now() / 1000) }));
            fs.rmSync(cil, { recursive: true, force: true });
            fs.renameSync(docasna, cil);
        } catch (e) {
            fs.rmSync(docasna, { recursive: true, force: true });
            throw new Error(`Web se nepodařilo sestavit: ${e.message}`);
        }

        // Stará vydání pryč (nahraná verze je na webu, předchozí drží root stranou).
        const stara = fs.readdirSync(vydaniSlozka(p)).filter((d) => /^[0-9a-f]{40}$/.test(d))
            .map((d) => ({ d, t: fs.statSync(path.join(vydaniSlozka(p), d)).mtimeMs })).sort((a, b) => b.t - a.t).slice(VYDANI_PONECHAT);
        for (const { d } of stara) fs.rmSync(path.join(vydaniSlozka(p), d), { recursive: true, force: true });

        return { strom, slozka: cil, main };
    })().finally(() => sestavovani.delete(strom));

    sestavovani.set(strom, prace);
    return prace;
}

/**
 * Po nahrání: refs/web/aktualni = nová verze, refs/web/predchozi = ta, kterou
 * root odložil pro Vrátit. Vrátit vezme strom předchozí, ale rodičem je main
 * z teď: vrácená úprava, která už v main je, tak při dalším zveřejnění
 * zůstane venku.
 */
async function zapamatovatNasazeni(p, uloha, zpet) {
    const repo = vydaniRepo(p);
    if (!zpet) {
        const web = await naWebu(repo);
        if (web) await prikaz('git', ['--git-dir', repo, 'update-ref', 'refs/web/predchozi', web]);
        await zapamatovatWeb(repo, uloha.strom, uloha.main, `Zveřejněno ${uloha.sha}`);

        return;
    }

    const predchozi = await naWebu(repo, 'refs/web/predchozi');
    if (!predchozi) {
        // Předchozí verzi nahrál ještě někdo jiný: co je na webu, nevíme, základem bude main.
        await prikaz('git', ['--git-dir', repo, 'update-ref', '-d', 'refs/web/aktualni']).catch(() => {});

        return;
    }
    await stahnoutMain(p, repo).catch(() => {});
    const main = await prikaz('git', ['--git-dir', repo, 'rev-parse', 'main']);
    await zapamatovatWeb(repo, `${predchozi}^{tree}`, main, 'Vráceno na předchozí verzi');
}

function ulozitUlohuVydani(uloha) {
    fs.mkdirSync(VYDANI_ULOHY, { recursive: true });
    fs.writeFileSync(path.join(VYDANI_ULOHY, `${uloha.id}.json`), JSON.stringify(uloha));
}

/**
 * Úloha na pozadí: sestavit (a s `nasadit` i nahrát na web). Stav si nástroje
 * čtou přes GET …/vydani/<id>: sestavuje → nahrava → hotovo / chyba.
 * Bez `sha` (Vrátit) jen nahraje zpátky předchozí verzi.
 */
function spustitVydani(p, { sha = null, nasadit = false, zpet = false }) {
    const uloha = { id: crypto.randomUUID(), projekt: p.slug, sha, stav: zpet ? 'nahrava' : 'sestavuje', vystup: '', konflikt: false, cas: Date.now() };
    ulozitUlohuVydani(uloha);

    (async () => {
        if (!zpet) {
            const vydani = await sestavitVydani(p, sha);
            Object.assign(uloha, { strom: vydani.strom, main: vydani.main, stav: nasadit ? 'nahrava' : 'hotovo' });
            ulozitUlohuVydani(uloha);
            if (!nasadit) return;
        }
        const r = await fronta(zpet ? { akce: 'nasadit-zpet', projekt: p.slug } : { akce: 'nasadit', projekt: p.slug, strom: uloha.strom }, 300);
        if (r.ok) {
            await zapamatovatNasazeni(p, uloha, zpet).catch((e) => console.error(`Vydání: nepovedlo se zapamatovat, co je na webu: ${e.message}`));
        }
        Object.assign(uloha, { stav: r.ok ? 'hotovo' : 'chyba', vystup: r.vystup });
        ulozitUlohuVydani(uloha);
    })().catch((e) => {
        Object.assign(uloha, { stav: 'chyba', vystup: e.message, konflikt: e.konflikt === true });
        ulozitUlohuVydani(uloha);
    });

    return uloha;
}

// ---------------------------------------------------------------- kopie živého webu

/**
 * Živý web (projekt typu server) se v terminálu a v aplikaci Claude neupravuje
 * přímo: agent pracuje v kopii (/projekty/<projekt>/kopie, vlastní databáze
 * <databáze>_kopie) a na živý web ji pošle tlačítko Zveřejnit v nástrojích.
 *
 *   - kopie má git: značka `zivy` = stav živého webu, změny = rozdíl proti ní,
 *   - Zveřejnit: root (agent nasadit-kopii) udělá snímek a zálohu databáze,
 *     nahraje soubory, spustí migrace a cache. Změny obsahu v databázi
 *     agent píše jako migrace, živá data (objednávky, zprávy) zůstávají,
 *   - Zahodit: kopie zpátky na živý web, databáze kopie znovu z živé,
 *   - Vrátit: soubory živého webu ze snímku před zveřejněním (agent obnovit).
 *
 * Úlohy z ClickUpu jdou dál rovnou na živý web (viz odpracovat).
 */
const KOPIE_PORT = 8090;
const KOPIE_VYNECHAT = ['/.git/', '/storage/logs/', '/storage/framework/cache/', '/storage/framework/sessions/',
    '/storage/framework/views/', '/bootstrap/cache/', '/node_modules/'];
const KOPIE_GIT_VYNECHAT = ['.env', '.env.*', 'vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/', 'public/storage', '*.log'];

const gitKopie = (p, args) => prikaz('git', ['-C', kopieSlozka(p), '-c', 'user.name=Kopie webu', '-c', 'user.email=agent@taveo.cz', ...args]);

/** Změněné soubory v kopii proti živému webu (i nové a smazané). */
async function zmenyKopie(p) {
    if (!maKopii(p)) return [];
    await gitKopie(p, ['add', '-A']);
    const vypis = await gitKopie(p, ['diff', '--cached', '--name-status', 'zivy']);
    return vypis.split('\n').filter(Boolean).map((r) => {
        const [stav, ...soubor] = r.split('\t');
        return { stav: stav[0], soubor: soubor.join('\t') };
    });
}

/** Databáze kopie znovu z živé (root, fronta). Vrací jméno databáze kopie. */
async function databazeKopie(p) {
    const r = await fronta({ akce: 'kopie-db', projekt: p.slug }, 300);
    const jmeno = r.ok && (r.vystup.match(/Kopie databáze: ([A-Za-z0-9_]+)/) || [])[1];
    if (!jmeno) throw new Error(`Kopii databáze se nepodařilo připravit. ${r.vystup || ''}`.trim());
    return jmeno;
}

/** .env kopie: jako živý, jen vlastní databáze a adresa náhledu v kontejneru. */
function envKopie(p, databaze) {
    const zive = fs.readFileSync(path.join(slozka(p), '.env'), 'utf8');
    const zmenit = { DB_DATABASE: databaze, APP_URL: `http://127.0.0.1:${KOPIE_PORT}`, APP_ENV: 'local', APP_DEBUG: 'true', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false' };
    let obsah = zive.split('\n').filter((r) => !Object.keys(zmenit).some((k) => r.startsWith(`${k}=`))).join('\n').trimEnd();
    obsah += '\n\n# Kopie webu pro agenta (nástroje Taveo). Nesahat, přepíše se při obnovení kopie.\n'
        + Object.entries(zmenit).map(([k, v]) => `${k}=${v}`).join('\n') + '\n';
    fs.writeFileSync(path.join(kopieSlozka(p), '.env'), obsah, { mode: 0o600 });
}

/**
 * Kopie z živého webu: soubory (rsync), databáze, .env, git se značkou `zivy`.
 * Nezveřejněné změny nepřepíše, leda s `prepsat`.
 */
async function pripravitKopii(p, { prepsat = false } = {}) {
    if (!prepsat && (await zmenyKopie(p)).length > 0) {
        throw new Error('V kopii jsou nezveřejněné změny. Zveřejněte je, nebo zahoďte.');
    }

    const kopie = kopieSlozka(p);
    fs.mkdirSync(kopie, { recursive: true });
    await prikaz('rsync', ['-a', '--delete', ...KOPIE_VYNECHAT.map((v) => `--exclude=${v}`), `${slozka(p)}/`, `${kopie}/`]);
    for (const d of ['storage/logs', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache']) {
        fs.mkdirSync(path.join(kopie, d), { recursive: true });
    }
    envKopie(p, await databazeKopie(p));

    if (!fs.existsSync(path.join(kopie, '.git'))) {
        await gitKopie(p, ['init', '-q', '-b', 'main']);
    }
    fs.writeFileSync(path.join(kopie, '.git', 'info', 'exclude'), KOPIE_GIT_VYNECHAT.join('\n') + '\n');
    await gitKopie(p, ['add', '-A']);
    await gitKopie(p, ['commit', '-q', '--allow-empty', '-m', `Živý web ${new Date().toLocaleString('cs-CZ', { timeZone: 'Europe/Prague' })}`]);
    await gitKopie(p, ['tag', '-f', 'zivy']);

    await restartovatAplikaci(p);
}

/** Aplikace Claude (serverový režim) má běžet ve správné složce: služba ji do tří minut spustí znovu. */
async function restartovatAplikaci(p) {
    const rc = (await okna(p.slug)).find((o) => o.rc);
    if (rc) await ukoncitOkno(rc.id).catch(() => {});
}

/**
 * Bez kopie (vývojový web, kde kopie nedává smysl): konverzace běží rovnou
 * v kod/ se zálohou předem, Zveřejnit ani kopie v nástrojích nejsou. Složka
 * a databáze dřívější kopie zůstanou, jen se nepoužívají.
 */
async function nastavitBezKopie(p, bez) {
    if (bez === bezKopie(p)) return;
    // Napůl založená kopie (bez značky `zivy`) změny nemá, git by jen spadl.
    if (bez && (await zmenyKopie(p).catch(() => [])).length > 0) {
        throw new Error('V kopii jsou nezveřejněné změny. Zveřejněte je, nebo zahoďte.');
    }

    if (bez) {
        fs.writeFileSync(bezKopieSoubor(p), 'Projekt bez kopie: agent pracuje přímo v kod/ (před konverzací záloha).\nNastavuje se v nástrojích, karta Hotová práce.\n');
    } else {
        fs.rmSync(bezKopieSoubor(p), { force: true });
    }
    await restartovatAplikaci(p);
}

/** Smaže předání (popis, snímky) živého webu: po zveřejnění nebo zahození už neplatí. */
function smazatPredaniKopie(p) {
    const koren = path.join('/projekty', p.slug, 'predani');
    for (const jmeno of fs.existsSync(koren) ? fs.readdirSync(koren) : []) {
        if (VETEV.test(jmeno)) fs.rmSync(path.join(koren, jmeno), { recursive: true, force: true });
    }
}

/** Úloha kopie na pozadí (zveřejnit / zahodit / vrátit / obnovit), stav jako u vydání. */
function spustitUlohuKopie(p, akce, { cas = null } = {}) {
    const uloha = { id: crypto.randomUUID(), projekt: p.slug, akce, stav: akce === 'zverejnit' ? 'nahrava' : 'pripravuje', vystup: '', konflikt: false, cas: Date.now() };
    ulozitUlohuVydani(uloha);

    (async () => {
        if (akce === 'zverejnit') {
            if ((await zmenyKopie(p)).length === 0) throw new Error('V kopii nejsou žádné změny.');
            const r = await fronta({ akce: 'nasadit-kopii', projekt: p.slug }, 300);
            if (!r.ok) throw new Error(r.vystup);
            await gitKopie(p, ['add', '-A']);
            await gitKopie(p, ['commit', '-q', '--allow-empty', '-m', `Zveřejněno ${new Date().toLocaleString('cs-CZ', { timeZone: 'Europe/Prague' })}`]);
            await gitKopie(p, ['tag', '-f', 'zivy']);
            smazatPredaniKopie(p);
            uloha.vystup = r.vystup;
        } else if (akce === 'zahodit') {
            await pripravitKopii(p, { prepsat: true });
            smazatPredaniKopie(p);
        } else if (akce === 'vratit') {
            const r = await fronta({ akce: 'obnovit', projekt: p.slug, cas, databaze: false }, 300);
            if (!r.ok) throw new Error(r.vystup);
            if (!bezKopie(p)) await pripravitKopii(p, { prepsat: true });
            uloha.vystup = r.vystup;
        } else {
            await pripravitKopii(p);
        }
        uloha.stav = 'hotovo';
        ulozitUlohuVydani(uloha);
    })().catch((e) => {
        Object.assign(uloha, { stav: 'chyba', vystup: e.message });
        ulozitUlohuVydani(uloha);
    });

    return uloha;
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
    // Bez přihlášení (dohled, docker healthcheck): jen čísla, nic o projektech.
    if (req.method === 'GET' && req.url.split('?')[0] === '/health') {
        return odpoved(res, 200, await zdravi());
    }

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
        const seznam = await Promise.all(projekty().map(async (p) => ({ ...p, bezKopie: bezKopie(p), vlakna: await konverzacniOkna(p.slug), aplikace: await adresaAplikace(p) })));
        return odpoved(res, 200, seznam);
    }

    // Ranní přehled ručně (ověření po nasazení). Jen jednou denně, jako plánovaný.
    if (req.method === 'POST' && url.pathname === '/api/ranni-prehled') {
        await ranniPrehled({ vynutit: true });
        return odpoved(res, 202, { spusteno: ranniBezi });
    }

    // Registr sezení: co běží, od kdy, poslední aktivita, verze Clauda.
    if (req.method === 'GET' && url.pathname === '/api/sezeni') {
        const ted = Math.floor(Date.now() / 1000);
        const seznam = (await obnovitRegistr()).map(({ znami, ...s }) => ({ ...s, necinne_s: necinnost(s, ted), konci: koncici.has(s.klic) }));
        return odpoved(res, 200, { strop: SEZENI_MAX, pocet: seznam.filter(pocita).length, verze_claude: aktualniVerze(), sezeni: seznam });
    }

    if (req.method === 'GET' && url.pathname === '/api/predplatne') {
        return odpoved(res, 200, await predplatne());
    }

    // Po zveřejnění: server si z GitHubu (main) stáhne novou verzi služby.
    // Nečeká se na výsledek, aktualizace tuhle službu sama restartuje.
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

    // Kopie živého webu: stav (změny proti živému) a úlohy (viz spustitUlohuKopie).
    if (casti[3] === 'kopie' && p.typ === 'server') {
        if (req.method === 'GET' && casti.length === 4) {
            const zmeny = await zmenyKopie(p);
            return odpoved(res, 200, {
                existuje: maKopii(p),
                bezKopie: bezKopie(p),
                zmeny,
                migrace: zmeny.filter((z) => z.soubor.startsWith('database/')).length,
                nahled: `http://127.0.0.1:${KOPIE_PORT}`,
            });
        }

        const akce = casti[4] || 'obnovit';
        if (req.method === 'POST' && casti.length === 5 && ['bez', 's-kopii'].includes(akce)) {
            try {
                await nastavitBezKopie(p, akce === 'bez');
            } catch (e) {
                return odpoved(res, 409, { chyba: e.message });
            }
            return odpoved(res, 200, { bezKopie: bezKopie(p) });
        }

        // Bez kopie jde jen Vrátit (soubory webu ze snímku před dřívějším zveřejněním).
        if (req.method === 'POST' && bezKopie(p) && akce !== 'vratit') {
            return odpoved(res, 409, { chyba: 'Projekt je bez kopie, agent pracuje přímo na webu.' });
        }

        if (req.method === 'POST' && casti.length <= 5 && ['zverejnit', 'zahodit', 'vratit', 'obnovit'].includes(akce)) {
            const { cas } = await telo(req);
            if (akce === 'vratit' && !(Number.isInteger(cas) && cas > 0)) {
                return odpoved(res, 400, { chyba: 'Chybí čas, do kterého se vrací.' });
            }
            return odpoved(res, 202, spustitUlohuKopie(p, akce, { cas }));
        }
    }

    // Stav úlohy na pozadí (vydání, kopie). 404 = neexistuje.
    if (req.method === 'GET' && casti[3] === 'uloha' && casti.length === 5 && UUID.test(casti[4] || '')) {
        const soubor = path.join(VYDANI_ULOHY, `${casti[4]}.json`);
        return fs.existsSync(soubor) ? odpoved(res, 200, JSON.parse(fs.readFileSync(soubor, 'utf8'))) : odpoved(res, 404, { chyba: 'Úloha neexistuje.' });
    }

    // Okamžité zveřejnění: sestavit / nahrát vydání a stav úlohy (viz spustitVydani).
    if (casti[3] === 'vydani' && VYDANI_PROJEKTY.includes(p.slug) && p.typ === 'git') {
        if (req.method === 'POST' && casti.length === 4) {
            const { sha, nasadit, zpet } = await telo(req);
            if (!zpet && !/^[0-9a-f]{40}$/.test(sha || '')) {
                return odpoved(res, 400, { chyba: 'Chybí commit.' });
            }
            return odpoved(res, 202, spustitVydani(p, { sha: zpet ? null : sha, nasadit: nasadit === true, zpet: zpet === true }));
        }

        if (req.method === 'GET' && casti.length === 5 && UUID.test(casti[4] || '')) {
            const soubor = path.join(VYDANI_ULOHY, `${casti[4]}.json`);
            return fs.existsSync(soubor) ? odpoved(res, 200, JSON.parse(fs.readFileSync(soubor, 'utf8'))) : odpoved(res, 404, { chyba: 'Úloha neexistuje.' });
        }
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

        zaraditUlohu(p, { id: data.id, zadani: data.zadani, session: data.session || null, autor: radek(data.autor || 'ClickUp', 60), terminal: data.terminal === true, konec: typeof data.konec === 'string' ? data.konec : null });
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

        await ukoncitOkno(okno.id);
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

/**
 * Adresa projektu v aplikaci Claude, kterou vypsal serverový režim. Pamatuje
 * se k oknu (za jeho života se nemění), dokud není, zkouší se jednou za 30 s.
 */
const adresyAplikace = new Map(); // okno:pid shellu → {adresa, cas}

async function adresaAplikace(p) {
    const rc = ((await vsechnaOkna()).get(p.slug) || []).find((o) => o.rc);
    if (!rc) return null;

    const klic = `${rc.id}:${rc.pid}`;
    const znama = adresyAplikace.get(klic);
    if (znama && (znama.adresa || Date.now() - znama.cas < 30_000)) return znama.adresa;

    let adresa = null;
    try {
        const obsah = await tmux(['capture-pane', '-p', '-J', '-t', rc.id, '-S', '-200']);
        adresa = obsah.match(/https:\/\/claude\.ai\/code\?environment=[\w-]+/)?.[0] ?? null;
    } catch { /* okno mezitím skončilo */ }
    adresyAplikace.set(klic, { adresa, cas: Date.now() });

    return adresa;
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
 *
 * Bez předem založeného sezení (--no-create-session-in-dir): jinak každý
 * serverový režim hned po startu spustí jedno `claude --print --sdk-url`
 * (~250 MB i s MCP), u 41 projektů ~14 GB, i když nikdo nic nepíše. Sezení
 * vzniká, až ho někdo v aplikaci založí. Nejvýš APLIKACE_KAPACITA na projekt.
 * APLIKACE_PROJEKTY: jen tyhle projekty (čárkami), „zadne“ žádný, prázdné všechny.
 */
const APLIKACE_KAPACITA = Number(process.env.APLIKACE_KAPACITA || 4);
const APLIKACE_PROJEKTY = (process.env.APLIKACE_PROJEKTY || '').split(',').map((s) => s.trim()).filter(Boolean);
// Mění se s parametry spuštění: okno se starými parametry hlídka sezení vymění.
const RC_NASTAVENI = `bez-predem-k${APLIKACE_KAPACITA}`;
const aplikaceProProjekt = (slug) => !APLIKACE_PROJEKTY.length || APLIKACE_PROJEKTY.includes('*')
    || (!APLIKACE_PROJEKTY.includes('zadne') && APLIKACE_PROJEKTY.includes(slug));
let aplikaceBezi = false;

async function hlidatAplikaci() {
    zrcadlitCesty();
    if (!prihlasen() || aplikaceBezi) return;
    aplikaceBezi = true;

    try {
        await spustitAplikace();
    } finally {
        aplikaceBezi = false;
    }
}

async function spustitAplikace() {
    for (const p of projekty().filter((p) => aplikaceProProjekt(p.slug))) {
        const rc = (await okna(p.slug)).find((o) => o.rc);
        if (rc?.claude) continue;
        if (rc) await ukoncitOkno(rc.id).catch(() => {});

        const cwd = pracovni(p);
        pripravitClaude(cwd);
        const prikaz = `bash -lc 'claude remote-control --name "TAVEO · ${p.slug}" --remote-control-session-name-prefix ${p.slug} --spawn same-dir --no-create-session-in-dir --capacity ${APLIKACE_KAPACITA}; exec bash'`;

        try {
            const okno = await noveOkno(p.slug, ['-c', cwd, ...prostredi(p, cwd), '-n', 'aplikace-claude'], prikaz);
            await tmux(['set-option', '-w', '-t', okno, '@rc', '1']);
            await tmux(['set-option', '-w', '-t', okno, '@rcnastaveni', RC_NASTAVENI]);
            await tmux(['set-option', '-w', '-t', okno, 'automatic-rename', 'off']);
            console.log(`${new Date().toISOString()} aplikace Claude: spuštěno pro ${p.slug}`);
        } catch (e) {
            console.error(`aplikace Claude pro ${p.slug}: ${e.message}`);
        }
    }
}

// ---------------------------------------------------------------- ranní přehled

const RANNI_CAS = process.env.RANNI_PREHLED_CAS || '06:50';
const RANNI_LIMIT_MIN = Number(process.env.RANNI_LIMIT_MIN || 40);
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
    // Plno: zkusí se znovu za minutu.
    if (!(await uvolnitMisto()).ok) return;

    ranniBezi = true;
    fs.mkdirSync(RANNI_SLOZKA, { recursive: true });
    // Značka hned na začátku: ani pád uprostřed nevede k druhému běhu týž den.
    fs.writeFileSync(znacka, new Date().toISOString());

    const cwd = `/projekty/${MARKETING}/kod`;
    pripravitClaude(cwd);
    const zadani = fs.readFileSync('/opt/terminal/ranni-prehled.md', 'utf8');

    console.log(`${new Date().toISOString()} ranní přehled: start`);

    claudeNaPozadi([
        '-p', zadani,
        '--permission-mode', 'dontAsk',
        '--allowedTools', 'Bash(taveo *)', 'Read', 'Glob', 'Grep',
        '--max-turns', '150',
    ], { cwd, env: process.env, limitMin: RANNI_LIMIT_MIN, druh: 'ranni', projekt: MARKETING, autor: 'ranní přehled' }).then(({ stdout, stderr, chyba, vyprselo }) => {
        ranniBezi = false;
        const log = path.join(RANNI_SLOZKA, `${den}.log`);
        fs.writeFileSync(log, [stdout, stderr, chyba ? `CHYBA: ${vyprselo ? `nestihl to do ${RANNI_LIMIT_MIN} min` : chyba.message}` : ''].join('\n'));

        const limit = /usage limit|limit reached|rate limit/i.test(`${stdout}${stderr}`);
        console.log(`${new Date().toISOString()} ranní přehled: ${chyba ? 'skončil chybou' : 'hotovo'}${limit ? ' (limit předplatného, dnes se nic neopakuje)' : ''}`);
    });
}

/**
 * `claude -p` od služby (úloha z ClickUpu, ranní přehled): ve vlastní skupině
 * procesů, v registru sezení a s časovým limitem. Po konci i po limitu skončí
 * celý strom (MCP servery, Chrome), ne jen Claude. Vrací {stdout, stderr, chyba, vyprselo}.
 */
function claudeNaPozadi(args, { cwd, env, limitMin, druh, projekt: slug, autor, session = null }) {
    return new Promise((resolve) => {
        let vyprselo = false;
        let limit = null;
        const uklid = (pid) => {
            const s = sledovane.get(pid);
            return ukoncitStrom(pid, { znami: s?.znami || [], skupina: pid }).catch((e) => console.error(e.message));
        };

        // spawn, ne execFile: jen ten umí `detached` (vlastní skupina procesů).
        const proces = spawn('claude', args, { cwd, env, detached: true, stdio: ['pipe', 'pipe', 'pipe'] });
        const vystup = { stdout: '', stderr: '' };
        let hotovo = false;
        const konec = (chyba) => {
            if (hotovo) return;
            hotovo = true;
            clearTimeout(limit);
            if (proces.pid) {
                uklid(proces.pid);
                sledovane.delete(proces.pid);
            }
            resolve({ ...vystup, chyba, vyprselo });
        };
        for (const proud of ['stdout', 'stderr']) {
            proces[proud].setEncoding('utf8');
            proces[proud].on('data', (data) => {
                vystup[proud] += data;
                if (vystup[proud].length > 20 * 1024 * 1024 && proces.pid) uklid(proces.pid);
            });
        }
        const chybaKonce = (kod, signal) => (kod === 0 ? null : Object.assign(
            new Error(signal ? `Claude ukončen signálem ${signal}` : `Claude skončil s kódem ${kod}`),
            { code: kod, signal, killed: vyprselo },
        ));
        proces.on('error', (e) => konec(e));
        // Výstup se zavře, až skončí všechno, co zdědilo rouru (MCP, Chrome). Proto
        // úklid hned po konci Clauda a na zbytek výstupu se čeká nejvýš 15 s.
        proces.on('exit', (kod, signal) => {
            if (proces.pid) uklid(proces.pid);
            setTimeout(() => konec(chybaKonce(kod, signal)), 15_000);
        });
        proces.on('close', (kod, signal) => konec(chybaKonce(kod, signal)));
        // Zadání jde v argumentu, vstup nic nenese. Bez zavření by Claude čekal na data.
        proces.stdin.end();

        const p = proces.pid ? stat(proces.pid) : null;
        if (p) sledovane.set(proces.pid, { druh, projekt: slug, autor, session, start: p.start, znami: [] });
        limit = setTimeout(() => {
            vyprselo = true;
            console.log(`${new Date().toISOString()} ${druh} v ${slug}: limit ${limitMin} min, ukončuji celý strom`);
            if (proces.pid) uklid(proces.pid);
        }, limitMin * 60 * 1000);
    });
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
// Okno úlohy → kdy do něj naposledy někdo psal (zásah člověka z terminálu).
const zasahy = new Map();
const ZASAH_NAVIC_MIN = 15;

function zaraditUlohu(p, uloha) {
    fronty.set(p.slug, [...(fronty.get(p.slug) || []), uloha]);
    console.log(`${new Date().toISOString()} úloha ${uloha.id}: zařazena v ${p.slug}`);
    spustitDalsi();
}

/**
 * Další úlohy z front projektů. Platí i strop sezení: když je plno a nic není
 * nečinné, úloha počká ve frontě a zkusí se znovu při hlídce sezení (minuta).
 */
let spoustim = false;
let spustitZnovu = false;
let cekaNaMisto = false;

async function spustitDalsi() {
    if (spoustim) {
        spustitZnovu = true;
        return;
    }
    spoustim = true;

    try {
        do {
            spustitZnovu = false;
            await spustitZFront();
        } while (spustitZnovu);
    } catch (e) {
        console.error(`fronta úloh: ${e.message}`);
    } finally {
        spoustim = false;
    }
}

async function spustitZFront() {
    for (const [slug, cekajici] of fronty) {
        if (bezici.size >= ULOHY_SOUBEZNE) return;
        if (bezici.has(slug) || !cekajici.length) continue;

        const misto = await uvolnitMisto();
        if (!misto.ok) {
            if (!cekaNaMisto) console.log(`${new Date().toISOString()} úlohy čekají: běží ${misto.pocet} sezení (strop ${SEZENI_MAX})`);
            cekaNaMisto = true;
            return;
        }
        cekaNaMisto = false;

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

    // ID konverzace přidělí web předem, ať na ni další zpráva z vlákna naváže,
    // i když přijde dřív, než tahle doběhne (fronta projektu je pustí po sobě).
    const navazat = Boolean(uloha.session) && ulozene(p).some((k) => k.session === uloha.session);
    const session = uloha.session || crypto.randomUUID();
    const autori = cteniJson(AUTORI, {});
    autori[session] ??= uloha.autor;
    zapisJson(AUTORI, autori);

    // Proměnné z `prostredi` (tmux -e KLIC=hodnota) pro execFile jako objekt.
    // TAVEO_ULOHA: mozek klienta má úloha v zadání, háček SessionStart ho nepřidá.
    const env = { ...process.env, TAVEO_ULOHA: '1' };
    const dvojice = prostredi(p);
    for (let i = 1; i < dvojice.length; i += 2) {
        const [klic, ...hodnota] = dvojice[i].split('=');
        env[klic] = hodnota.join('=');
    }

    console.log(`${new Date().toISOString()} úloha ${uloha.id}: start v ${p.slug} (${navazat ? 'navazuje' : 'nová'} ${session})`);

    if (uloha.terminal) {
        const vysledek = await vTerminalu(p, uloha, cwd, session, navazat);
        await vysledekUlohy(uloha, vysledek);
        console.log(`${new Date().toISOString()} úloha ${uloha.id}: ${vysledek.ok ? 'hotovo' : 'chyba'} (v terminálu)`);
        return;
    }

    // Paměť projektu říká „pracuj v kopii“, úlohy z ClickUpu ale jdou rovnou na web.
    const zadani = p.typ === 'server'
        ? `Tahle úloha je z ClickUpu: pracuj přímo na živém webu v aktuální složce (${slozka(p)}), kopii webu ani předání do karty nepoužívej.\n\n${uloha.zadani}`
        : uloha.zadani;
    const { stdout, stderr, chyba, vyprselo } = await claudeNaPozadi([
        '-p', zadani,
        ...(navazat ? ['--resume', session] : ['--session-id', session]),
        '--output-format', 'json',
        '--permission-mode', 'auto',
        '--max-turns', '200',
    ], { cwd, env, limitMin: ULOHA_LIMIT_MIN, druh: 'uloha', projekt: p.slug, autor: uloha.autor, session });

    fs.mkdirSync(ULOHY_LOG, { recursive: true });
    fs.writeFileSync(path.join(ULOHY_LOG, `${uloha.id}.log`), [stdout, stderr, chyba ? `CHYBA: ${chyba.message}` : ''].join('\n'));

    let vystup = null;
    try { vystup = JSON.parse(stdout); } catch { /* spadl dřív, než něco vypsal */ }

    const limit = /usage limit|limit reached|rate limit/i.test(`${stdout}${stderr}`);
    const casVyprsel = vyprselo || chyba?.killed || chyba?.signal === 'SIGTERM';
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

/**
 * Úloha, na kterou se dá dívat (reklamní grafika): běží jako obyčejná
 * konverzace v okně tmuxu, takže ji nástroje ukážou v terminálu stejně jako
 * konverzaci s člověkem. Konec pozná háček Stop (Claude dopsal odpověď),
 * zapíše ho do souboru a odtud se vezme výsledek.
 * Když Claude jen čeká na příkaz na pozadí, není hotovo, ledaže odpověď
 * už obsahuje závěrečnou značku úlohy (`konec`, třeba „[/grafika]“).
 * Po zásahu člověka (psal do terminálu) se čeká jen na značku: odpověď
 * na jeho zprávu ještě není výsledek. Limit se pak prodlužuje od zásahu.
 *
 * Okno po úloze zůstane otevřené jako běžná konverzace (s kontextem úlohy),
 * sama se uspí jako ostatní. Když v ní Claude později znovu odpoví se
 * značkou (člověk chtěl změnu), výsledek jde na web znovu.
 */
async function vTerminalu(p, uloha, cwd, session, navazat) {
    fs.mkdirSync(ULOHY_LOG, { recursive: true });
    const soubor = (pripona) => path.join(ULOHY_LOG, `${uloha.id}.${pripona}`);
    fs.rmSync(soubor('stop'), { force: true });
    fs.writeFileSync(soubor('zadani'), uloha.zadani);
    fs.writeFileSync(soubor('nastaveni.json'), JSON.stringify({
        hooks: { Stop: [{ hooks: [{ type: 'command', command: `cat > ${soubor('stop')}` }] }] },
    }));

    // Stejná konverzace otevřená v nástrojích by se s úlohou prala o soubor.
    for (const o of await konverzacniOkna(p.slug)) {
        if (o.session === session) await ukoncitOkno(o.id).catch(() => {});
    }

    // Celé zadání jen v souboru, ne v příkazové řádce: `pkill -f` / `pgrep -f`
    // s textem ze zadání (třeba port serveru náhledu) by jinak trefil i Clauda.
    const uvod = `${radek(uloha.zadani.split('\n')[0], 120)}\n\nCelé zadání je v souboru ${soubor('zadani')}. Přečti si ho celé a postupuj podle něj.`;
    fs.writeFileSync(soubor('uvod'), uvod);
    const prikaz = `bash -lc 'claude ${navazat ? '--resume' : '--session-id'} ${session} --permission-mode auto --settings ${soubor('nastaveni.json')} "$(cat ${soubor('uvod')})"'`;
    const prostrediUlohy = [...prostredi(p, cwd), '-e', 'TAVEO_ULOHA=1'];
    const okno = await noveOkno(p.slug, ['-c', cwd, ...prostrediUlohy, '-n', 'uloha'], prikaz, ['-x', '160', '-y', '45']);
    await tmux(['set-option', '-w', '-t', okno, '@session', session]);
    await tmux(['set-option', '-w', '-t', okno, '@autor', uloha.autor]);
    await tmux(['set-option', '-w', '-t', okno, '@uloha', String(uloha.id)]);
    await tmux(['set-option', '-w', '-t', okno, 'automatic-rename', 'off']);

    const konec = Date.now() + ULOHA_LIMIT_MIN * 60 * 1000;
    const hotovo = (stop) => uloha.konec && String(stop?.last_assistant_message || '').includes(uloha.konec);
    let stop = null;
    let chyba = null;
    let chybi = 0;

    while (!stop && !chyba) {
        await new Promise((r) => setTimeout(r, 2000));

        // Háček píše soubor průběžně, nedopsaný JSON se přečte příště.
        try { stop = JSON.parse(fs.readFileSync(soubor('stop'), 'utf8')); } catch { /* ještě nedoběhl */ }
        const ceka = stop?.background_tasks?.some((t) => t.status === 'running') || zasahy.has(okno);
        if (stop && ceka && !hotovo(stop)) {
            fs.rmSync(soubor('stop'), { force: true });
            stop = null;
        }
        if (stop) break;

        // Okno musí chybět dvakrát po sobě: pomalý tmux občas vrátí prázdný seznam.
        chybi = (await okna(p.slug)).some((o) => o.id === okno) ? 0 : chybi + 1;
        if (chybi >= 2) {
            chyba = 'Claude skončil bez výsledku.';
        } else if (Date.now() > Math.max(konec, (zasahy.get(okno) || 0) + ZASAH_NAVIC_MIN * 60 * 1000)) {
            chyba = `Nestihl jsem to do ${ULOHA_LIMIT_MIN} minut. Konverzace zůstala otevřená, agent v ní může pokračovat.`;
        }
    }

    zasahy.delete(okno);
    fs.rmSync(soubor('stop'), { force: true });
    const obrazovka = await tmux(['capture-pane', '-p', '-J', '-t', okno, '-S', '-3000']).catch(() => '');
    fs.writeFileSync(path.join(ULOHY_LOG, `${uloha.id}.log`), [obrazovka, stop ? JSON.stringify(stop) : '', chyba ? `CHYBA: ${chyba}` : ''].join('\n'));
    // Úloha skončila, okno je dál obyčejná konverzace (Claude si zadání už přečetl).
    await tmux(['set-option', '-wu', '-t', okno, '@uloha']).catch(() => {});
    pokracovani(uloha, okno, soubor, hotovo);

    const vysledek = typeof stop?.last_assistant_message === 'string' ? stop.last_assistant_message : null;
    if (/usage limit|limit reached|rate limit/i.test(`${chyba ? obrazovka.slice(-2000) : ''}${vysledek && !hotovo(stop) ? vysledek : ''}`)) {
        chyba = 'Narazil jsem na limit předplatného Claude.';
    }

    return { ok: !chyba, session: stop?.session_id || session, vysledek, chyba };
}

/**
 * Dokud okno po úloze žije, hlídá další odpovědi Clauda (člověk v terminálu
 * chtěl změnu). Odpověď se závěrečnou značkou jde na web jako nový výsledek.
 * Když okno zmizí (uspání, nové kolo úlohy), hlídání i soubory úlohy končí.
 */
function pokracovani(uloha, okno, soubor, hotovo) {
    const hlidka = setInterval(async () => {
        const zije = await oknoZije(okno).catch(() => true);
        if (!zije) {
            clearInterval(hlidka);
            for (const s of ['stop', 'zadani', 'uvod', 'nastaveni.json']) fs.rmSync(soubor(s), { force: true });
            return;
        }

        let stop = null;
        try { stop = JSON.parse(fs.readFileSync(soubor('stop'), 'utf8')); } catch { return; }
        fs.rmSync(soubor('stop'), { force: true });

        if (hotovo(stop)) {
            console.log(`${new Date().toISOString()} úloha ${uloha.id}: nový výsledek z pokračování konverzace`);
            await vysledekUlohy(uloha, { ok: true, session: stop.session_id, vysledek: stop.last_assistant_message, chyba: null });
        }
    }, 5000);
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

// Hlídka sezení hned po startu (staré verze, osiřelé procesy po pádu služby), pak pravidelně.
setTimeout(() => hlidatSezeni().catch((e) => console.error(e.message)), 3000);
setInterval(() => hlidatSezeni().catch((e) => console.error(e.message)), HLIDKA_SEZENI * 1000);
setInterval(() => hlidatPamet().catch((e) => console.error(e.message)), PAMET_INTERVAL * 1000);

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

    // Okno musí v projektu běžet: jinak by tmux ukázal jiné okno projektu
    // (třeba po konci úlohy) a okno úlohy se připojuje samo (link-window).
    okna(p.slug).then((seznam) => {
        const okno = seznam.find((o) => o.id === v.v);
        if (!okno) {
            socket.write('HTTP/1.1 404 Not Found\r\n\r\n');
            return socket.destroy();
        }
        wss.handleUpgrade(req, socket, head, (ws) => pripojit(ws, p, v, Boolean(okno?.uloha)));
    });
});

function pripojit(ws, p, v, uloha = false) {
    // Každé připojení má vlastní sezení ve skupině projektu. Sdílí okna, ale
    // přepnutí konverzace u Toma nepřepne konverzaci Pavlovi. Po odpojení zmizí.
    const id = `${p.slug}-w${crypto.randomBytes(3).toString('hex')}`;
    // Úloha: vlastní sezení jen s jejím oknem, bez ostatních oken projektu.
    // Když úloha skončí, sezení zmizí s ní a spojení se zavře.
    const sezeni = uloha
        ? ['new-session', '-s', id, '-n', 'prazdne', 'tail -f /dev/null', ';', 'link-window', '-s', v.v, '-t', `${id}:9`, ';', 'kill-window', '-t', `${id}:0`]
        : ['new-session', '-t', `=${p.slug}`, '-s', id, ';', 'select-window', '-t', `${id}:${v.v}`];
    const term = pty.spawn('tmux', [
        ...sezeni, ';',
        'set-option', '-t', id, 'destroy-unattached', 'on', ';',
        'set-option', '-t', id, 'status', 'off',
    ], {
        name: 'xterm-256color',
        cols: 120,
        rows: 32,
        cwd: slozka(p),
        env: { ...process.env, TERM: 'xterm-256color', LANG: 'C.UTF-8' },
    });

    console.log(`${new Date().toISOString()} ${v.u || '?'} otevřel ${p.slug}:${v.v}`);

    // Po skončení pty (tmux klient skončil, okno zavřené) se do něj už nezapisuje:
    // zápis do zavřeného deskriptoru byl „Unhandled pty write error EBADF“.
    let konec = false;
    // LiteSpeed ukončí tiché spojení, ping ho drží naživu.
    const ping = setInterval(() => ws.readyState === ws.OPEN && ws.ping(), 25000);
    const posluchaci = [];
    const uklid = () => {
        clearInterval(ping);
        while (posluchaci.length) posluchaci.pop().dispose();
    };
    const doTerminalu = (prace) => {
        if (konec) return;
        try {
            prace();
        } catch (e) {
            console.error(`terminál ${p.slug}:${v.v}: ${e.message}`);
            konec = true;
            uklid();
            ws.close(1011, 'chyba terminálu');
        }
    };

    posluchaci.push(term.onData((data) => ws.readyState === ws.OPEN && ws.send(data)));
    posluchaci.push(term.onExit(() => {
        konec = true;
        uklid();
        ws.close(1000, 'konec');
    }));

    ws.on('message', (zprava) => {
        if (konec) return;
        let data;
        try { data = JSON.parse(zprava.toString()); } catch { return; }

        // Jen ke sledování projde kolečko myši, psaní ne. Psaní do úlohy = zásah.
        const mys = typeof data.d === 'string' && /^(\x1b\[<\d+;\d+;\d+[Mm])+$/.test(data.d);
        if (data.t === 'i' && typeof data.d === 'string' && (!v.r || mys)) {
            if (!mys) vstupy.set(v.v, Math.floor(Date.now() / 1000));
            if (uloha && !mys) zasahy.set(v.v, Date.now());
            doTerminalu(() => term.write(data.d));
        } else if (data.t === 'r') {
            const cols = Math.max(20, Math.min(400, Number(data.c) || 80));
            const rows = Math.max(5, Math.min(200, Number(data.r) || 24));
            doTerminalu(() => term.resize(cols, rows));
        }
    });

    // Bez posluchače by chyba spojení (reset od klienta) shodila celou službu.
    ws.on('error', (e) => console.error(`terminál ${p.slug}:${v.v}: ${e.message}`));

    ws.on('close', () => {
        uklid();
        if (konec) return;
        konec = true;
        try { term.kill(); } catch { /* už skončil */ }
    });
}

server.listen(PORT, '0.0.0.0', () => console.log(`Služba agenta poslouchá na ${PORT}`));
