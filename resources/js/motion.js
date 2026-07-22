/*
|--------------------------------------------------------------------------
| Motion
|--------------------------------------------------------------------------
| Port animací z původního designu (design-source/TAVO Homepage.dc.html,
| metoda setupMotion()). Bez GSAP a bez Three.js — původní init3D() se
| v designu nikdy nevolal a canvas #tavo3d v markupu nebyl.
|
| Princip: obsah je v CSS viditelný by default; třídu .pre přidáme až tady,
| takže při vypnutém JS zůstane web plně čitelný.
*/

const q = (sel) => Array.from(document.querySelectorAll(sel));

const getScroll = () =>
    Math.max(
        document.documentElement.scrollTop || 0,
        document.body.scrollTop || 0,
        window.scrollY || 0,
    );

function initNav() {
    const nav = document.querySelector('[data-nav]');
    if (!nav) return () => {};

    return () => {
        nav.classList.toggle('is-scrolled', getScroll() > 40);
    };
}

function initReveal() {
    const reveal = q('[data-reveal]');
    const lines = q('[data-line]');

    reveal.forEach((el) => el.classList.add('pre'));
    lines.forEach((el) => el.classList.add('pre'));

    // Nadpis v heru — odkrytí po řádcích se stagger efektem.
    lines.forEach((el, i) => setTimeout(() => el.classList.remove('pre'), 150 + i * 110));

    return () => {
        const vh = window.innerHeight;
        q('[data-reveal].pre').forEach((el) => {
            const r = el.getBoundingClientRect();
            if (r.top < vh * 0.92 && r.bottom > 0) el.classList.remove('pre');
        });
    };
}

function initParallax() {
    const items = q('[data-parallax]');
    if (!items.length) return () => {};

    return () => {
        const vh = window.innerHeight;
        items.forEach((el) => {
            const wrap = el.closest('[data-parallax-wrap]');
            if (!wrap) return;
            const r = wrap.getBoundingClientRect();
            const p = (r.top + r.height / 2 - vh / 2) / vh;
            el.style.transform = `translateY(${(-p * 14).toFixed(2)}%)`;
        });
    };
}

export default function initMotion() {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const onNav = initNav();

    if (reduced) {
        onNav();
        window.addEventListener('scroll', onNav, { passive: true });
        return;
    }

    const onReveal = initReveal();
    const onParallax = initParallax();

    const onScroll = () => {
        onNav();
        onReveal();
        onParallax();
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    onScroll();

    // Doběhy pro případ, že se fonty/layout doloží později.
    setTimeout(onScroll, 250);
    setTimeout(onScroll, 900);
}
