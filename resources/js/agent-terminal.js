import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import { ClipboardAddon } from '@xterm/addon-clipboard';
import '@xterm/xterm/css/xterm.css';

/**
 * Terminál vlákna agenta v nástrojích (stránka Projekty → projekt).
 *
 * Připojuje se WebSocketem k službě v kontejneru taveo-agent. Adresu
 * s vstupenkou dává Livewire (`url()`), vstupenka platí minutu, proto si ji
 * každé připojení i opětovné připojení bere znovu.
 *
 * Zprávy ven: {t: 'i', d: text} vstup z klávesnice, {t: 'r', c, r} velikost.
 * Zprávy dovnitř: výstup terminálu jako text.
 */
/** Jak dlouho čekat na vstupenku a na otevření spojení (ms). */
const CONNECT_TIMEOUT = 10000;

class TavoAgentTerminal {
    constructor(element, { url, onState, onCopy }) {
        this.url = url;
        this.onState = onState ?? (() => {});
        this.onCopy = onCopy ?? (() => {});
        this.socket = null;
        this.attempts = 0;
        this.runs = 0;
        this.problem = null;
        this.pendingCopy = null;

        this.term = new Terminal({
            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
            fontSize: 13,
            lineHeight: 1.15,
            cursorBlink: true,
            scrollback: 5000,
            allowProposedApi: true,
            // Výběr myší zachytává tmux (kvůli posouvání). S ⌥ jde klasický výběr pro ⌘C.
            macOptionClickForcesSelection: true,
            // Barvy webu: tmavé pozadí ink, krémový text, cihlový kurzor.
            theme: {
                background: '#131110',
                foreground: '#f4ede1',
                cursor: '#db4b24',
                cursorAccent: '#131110',
                selectionBackground: 'rgba(219, 75, 36, 0.35)',
            },
        });

        this.fit = new FitAddon();
        this.term.loadAddon(this.fit);
        // Výběr myší (tmux) i „c to copy“ u Clauda posílají text přes OSC 52.
        // Číst schránku aplikacím v terminálu nedovolíme.
        this.term.loadAddon(new ClipboardAddon(undefined, {
            readText: async () => '',
            writeText: (selection, text) => this.copy(text),
        }));
        this.term.attachCustomKeyEventHandler((event) => this.copyOnKey(event));
        // Odkazy jdou otevřít kliknutím, i když je Claude zalomil do více řádků.
        this.term.registerLinkProvider({ provideLinks: (y, callback) => callback(this.links(y)) });
        this.term.open(element);
        this.resize();

        this.term.onData((data) => this.send({ t: 'i', d: data }));

        new ResizeObserver(() => this.resize()).observe(element);
    }

    /**
     * Odkazy na řádku `y` (číslováno od 1). Claude dlouhé adresy láme natvrdo
     * na šířku terminálu (přihlašovací odkaz má tři řádky), takže se řádky,
     * které končí na plné šířce, spojí s dalšími a odkaz se hledá v celku.
     */
    links(y) {
        const buffer = this.term.buffer.active;
        const text = (row) => buffer.getLine(row - 1)?.translateToString(true) ?? '';
        const full = (row) => text(row).length >= this.term.cols - 1;

        let start = y;
        while (start > 1 && full(start - 1) && /^\S/.test(text(start))) start--;

        // Spojený text a ke každému znaku jeho řádek a sloupec.
        let joined = '';
        const position = [];
        for (let row = start; row <= buffer.length; row++) {
            const line = text(row);
            for (let x = 0; x < line.length; x++) position.push({ x: x + 1, y: row });
            joined += line;
            if (! full(row) || ! /^\S/.test(text(row + 1))) break;
        }

        const links = [];
        for (const match of joined.matchAll(/https?:\/\/[^\s"'<>`]+/g)) {
            const uri = match[0].replace(/[.,;:!?)\]]+$/, '');
            const from = position[match.index];
            const to = position[match.index + uri.length - 1];
            if (! from || ! to || y < from.y || y > to.y) continue;

            links.push({
                range: { start: from, end: to },
                text: uri,
                activate: () => window.open(uri, '_blank', 'noopener'),
            });
        }

        return links;
    }

    /**
     * Zápis do schránky. Safari ho bez kliknutí nebo klávesy odmítne (text
     * přijde ze serveru se zpožděním), pak ho dokončí nejbližší ⌘C.
     */
    async copy(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.pendingCopy = null;
            this.onCopy('copied');
        } catch {
            this.pendingCopy = text;
            this.onCopy('pending');
        }
    }

    copyOnKey(event) {
        const shortcut = event.type === 'keydown' && event.key.toLowerCase() === 'c' && (event.metaKey || (event.ctrlKey && event.shiftKey));
        if (! this.pendingCopy || ! shortcut) return true;

        this.copy(this.pendingCopy);
        return false;
    }

    send(message) {
        if (this.socket?.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(message));
        }
    }

    resize() {
        try {
            this.fit.fit();
        } catch {
            return; // prvek je skrytý
        }
        this.send({ t: 'r', c: this.term.cols, r: this.term.rows });
    }

    async connect() {
        this.disconnect();
        const run = ++this.runs;
        this.onState('connecting');

        let url;
        this.problem = null;
        try {
            // Livewire po chybě (vypršelá stránka, výpadek) slib nemusí nikdy splnit.
            url = await Promise.race([
                this.url(),
                new Promise((resolve, reject) => setTimeout(() => reject(new Error('timeout')), CONNECT_TIMEOUT)),
            ]);
        } catch (error) {
            this.problem = error.message === 'timeout' ? 'Server nevydal vstupenku do 10 s.' : `Vstupenka: ${error.message}`;
            if (run === this.runs) this.retry(run);
            return;
        }
        if (run !== this.runs) return; // mezitím jiné připojení

        if (! url) {
            this.term.reset();
            this.onState('none');
            return;
        }

        let socket;
        try {
            socket = new WebSocket(url);
        } catch (error) {
            this.problem = `WebSocket: ${error.message}`;
            this.retry(run);
            return;
        }
        this.socket = socket;

        // Spojení, které se neotevře ani neodmítne, prohlížeč drží donekonečna
        // (zachytí ho antivirus nebo doplněk). Zavřít a zkusit znovu.
        const timeout = setTimeout(() => {
            if (socket.readyState !== WebSocket.CONNECTING) return;
            this.problem = 'Spojení se do 10 s neotevřelo.';
            socket.close();
        }, CONNECT_TIMEOUT);

        socket.onopen = () => {
            clearTimeout(timeout);
            this.attempts = 0;
            this.term.reset();
            this.onState('open');
            this.resize();
            this.term.focus();
        };

        socket.onmessage = (event) => this.term.write(event.data);

        socket.onclose = (event) => {
            clearTimeout(timeout);
            if (this.socket !== socket) return; // odpojili jsme sami (jiné vlákno)
            this.socket = null;
            this.problem ??= `Spojení se zavřelo (kód ${event.code}).`;
            this.retry(run);
        };
    }

    /**
     * Výpadek spojení (uspaný notebook, restart LiteSpeedu): zkusit znovu,
     * s čerstvou vstupenkou. Po třech nezdarech nechat na člověku.
     */
    retry(run) {
        if (this.attempts < 3) {
            this.attempts++;
            this.onState('reconnecting');
            setTimeout(() => run === this.runs && this.connect(), 1500 * this.attempts);
        } else {
            this.attempts = 0;
            this.onState('closed', this.problem);
        }
    }

    disconnect() {
        const socket = this.socket;
        this.socket = null;
        socket?.close();
    }

    focus() {
        this.term.focus();
    }
}

window.TavoAgentTerminal = TavoAgentTerminal;
window.dispatchEvent(new Event('tavo-agent-terminal-ready'));
