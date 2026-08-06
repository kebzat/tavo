import Alpine from 'alpinejs';
import initMotion from './motion';

/**
 * Stav mobilního menu je globální — potřebuje ho navigace i cookie lišta,
 * která se při otevřeném menu schová, aby nepřekrývala spodní CTA.
 */
Alpine.store('nav', {
    open: false,
    toggle() {
        this.open = !this.open;
        document.body.style.overflow = this.open ? 'hidden' : '';
    },
    close() {
        this.open = false;
        document.body.style.overflow = '';
    },
});

/**
 * Galerie na detailu reference — slider s tečkami vedle textu, kliknutím se
 * obrázek zvětší přes celou stránku. Ovládání klávesnicí (Esc, šipky) i pořadí
 * teček řeší komponenta v gallery.blade.php.
 */
Alpine.data('tavoGallery', (images) => ({
    images,
    index: 0,
    lightbox: false,

    /** Prvek, ze kterého se lightbox otevřel — po zavření se na něj vrátí fokus. */
    openedFrom: null,

    get current() {
        return this.images[this.index] ?? { src: '', srcset: null, alt: '' };
    },

    go(i) {
        this.index = (i + this.images.length) % this.images.length;
    },

    prev() {
        this.go(this.index - 1);
    },

    next() {
        this.go(this.index + 1);
    },

    openLightbox(i) {
        if (i != null) this.index = i;
        this.openedFrom = document.activeElement;
        this.lightbox = true;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.close?.focus());
    },

    closeLightbox() {
        if (! this.lightbox) return;
        this.lightbox = false;
        document.body.style.overflow = '';
        this.openedFrom?.focus();
        this.openedFrom = null;
    },

    /**
     * Fokus musí zůstat v otevřeném dialogu — jinak by tabulátor odešel na
     * stránku pod ním, kterou uživatel nevidí.
     */
    trapFocus(event) {
        const focusable = Array.from(
            this.$el.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])'),
        ).filter((el) => el.offsetParent !== null);

        if (! focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const current = document.activeElement;

        if (event.shiftKey) {
            (current === first || ! focusable.includes(current) ? last : focusable[focusable.indexOf(current) - 1]).focus();
        } else {
            (current === last || ! focusable.includes(current) ? first : focusable[focusable.indexOf(current) + 1]).focus();
        }
    },
}));

/**
 * Porovnání „před a po" v bloku statické stránky. Dělicí čára sleduje ukazatel,
 * takže stačí přejet myší; na dotyku se táhne prstem.
 *
 * Ovládání klávesnicí zajišťuje skrytý `input[type=range]` v šabloně, který píše
 * do stejné `position`. Kdo nemá myš, posune čáru šipkami.
 */
Alpine.data('tavoBeforeAfter', () => ({
    position: 50,

    track(event) {
        const frame = this.$refs.frame?.getBoundingClientRect();

        if (! frame?.width) return;

        const ratio = ((event.clientX - frame.left) / frame.width) * 100;

        this.position = Math.min(100, Math.max(0, ratio));
    },
}));

/**
 * Odškrtávání položek na sdíleném checklistu.
 *
 * Políčko je uvnitř normálního formuláře, takže bez JS se odešle klasicky
 * a stránka se překreslí. Tady jen odchytneme odeslání, pošleme ho na pozadí
 * a rovnou přepíšeme čísla progresu, aby se u sto položek nemuselo pokaždé
 * čekat na načtení stránky.
 */
Alpine.data('tavoChecklistItem', (hotovoNaZacatku) => ({
    hotovo: hotovoNaZacatku,
    rozbaleno: false,
    odesila: false,

    async prepnout() {
        if (this.odesila) return;

        this.odesila = true;
        const puvodni = this.hotovo;
        this.hotovo = ! this.hotovo;

        try {
            const odpoved = await fetch(this.$el.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            if (! odpoved.ok) throw new Error(odpoved.status);

            const data = await odpoved.json();

            this.hotovo = data.done;
            prepisProgres('[data-progres-kategorie]', data.kategorie);
            prepisProgres('[data-progres-celkem]', data.celkem);
        } catch {
            // Nepovedlo se uložit, vracíme políčko do původního stavu,
            // ať uživatel nevidí odškrtnuto něco, co v databázi není.
            this.hotovo = puvodni;
        } finally {
            this.odesila = false;
        }
    },
}));

/** Přepíše čísla a šířku proužku v jednom bloku progresu. */
function prepisProgres(selektor, progres) {
    document.querySelectorAll(selektor).forEach((blok) => {
        blok.querySelectorAll('[data-progres-procenta]').forEach((el) => (el.textContent = progres.percent));
        blok.querySelectorAll('[data-progres-hotovo]').forEach((el) => (el.textContent = progres.done));
        blok.querySelectorAll('[data-progres-vypln]').forEach((el) => (el.style.width = `${progres.percent}%`));
    });
}

window.Alpine = Alpine;
Alpine.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMotion);
} else {
    initMotion();
}
