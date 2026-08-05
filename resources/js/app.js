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

window.Alpine = Alpine;
Alpine.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMotion);
} else {
    initMotion();
}
