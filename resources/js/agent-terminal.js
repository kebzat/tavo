import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import { ClipboardAddon } from '@xterm/addon-clipboard';
import '@xterm/xterm/css/xterm.css';

/**
 * Terminál vlákna agenta v nástrojích (stránka Agent → projekt).
 *
 * Připojuje se WebSocketem k službě v kontejneru taveo-agent. Adresu
 * s vstupenkou dává Livewire (`url()`), vstupenka platí minutu, proto si ji
 * každé připojení i opětovné připojení bere znovu.
 *
 * Zprávy ven: {t: 'i', d: text} vstup z klávesnice, {t: 'r', c, r} velikost.
 * Zprávy dovnitř: výstup terminálu jako text.
 */
class TavoAgentTerminal {
    constructor(element, { url, onState }) {
        this.url = url;
        this.onState = onState ?? (() => {});
        this.socket = null;
        this.attempts = 0;

        this.term = new Terminal({
            fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
            fontSize: 13,
            lineHeight: 1.15,
            cursorBlink: true,
            scrollback: 5000,
            allowProposedApi: true,
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
        // „c to copy“ u Clauda posílá text do schránky přes OSC 52 (tmux ho propouští).
        this.term.loadAddon(new ClipboardAddon());
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
        this.onState('connecting');

        const url = await this.url();
        if (! url) {
            this.term.reset();
            this.onState('none');
            return;
        }

        const socket = new WebSocket(url);
        this.socket = socket;

        socket.onopen = () => {
            this.attempts = 0;
            this.term.reset();
            this.onState('open');
            this.resize();
            this.term.focus();
        };

        socket.onmessage = (event) => this.term.write(event.data);

        socket.onclose = () => {
            if (this.socket !== socket) return; // odpojili jsme sami (jiné vlákno)
            this.socket = null;

            // Výpadek spojení (uspaný notebook, restart LiteSpeedu): zkusit znovu,
            // s čerstvou vstupenkou. Po třech nezdarech nechat na člověku.
            if (this.attempts < 3) {
                this.attempts++;
                this.onState('reconnecting');
                setTimeout(() => this.socket === null && this.connect(), 1500 * this.attempts);
            } else {
                this.onState('closed');
            }
        };
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
