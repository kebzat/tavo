import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import { WebLinksAddon } from '@xterm/addon-web-links';
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
        // Odkazy jdou otevřít kliknutím (přihlášení Clauda vypíše adresu).
        this.term.loadAddon(new WebLinksAddon((event, uri) => window.open(uri, '_blank', 'noopener')));
        this.term.open(element);
        this.resize();

        this.term.onData((data) => this.send({ t: 'i', d: data }));

        new ResizeObserver(() => this.resize()).observe(element);
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
