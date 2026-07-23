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
 * Galerie na detailu reference — kliknutím se obrázek zvětší přes celou stránku.
 * Ovládání klávesnicí (Esc, šipky) řeší komponenta v gallery.blade.php.
 */
Alpine.data('tavoLightbox', (images) => ({
    images,
    index: 0,
    isOpen: false,

    get current() {
        return this.images[this.index] ?? { url: '', alt: '' };
    },

    open(index) {
        this.index = index;
        this.isOpen = true;
        document.body.style.overflow = 'hidden';
        this.$nextTick(() => this.$refs.closeButton?.focus());
    },

    close() {
        if (! this.isOpen) return;
        this.isOpen = false;
        document.body.style.overflow = '';
    },

    prev() {
        if (! this.isOpen) return;
        this.index = (this.index - 1 + this.images.length) % this.images.length;
    },

    next() {
        if (! this.isOpen) return;
        this.index = (this.index + 1) % this.images.length;
    },
}));

window.Alpine = Alpine;
Alpine.start();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMotion);
} else {
    initMotion();
}
