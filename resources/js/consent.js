/**
 * Souhlas s cookies a načítání měřicích kódů.
 *
 * Kategorie jsou dvě: analytické (GA4, Microsoft Clarity) a marketingové
 * (Meta Pixel). GTM je kontejner, načte se po souhlasu s kteroukoliv z nich
 * a o to, co v něm smí běžet, se postará Consent Mode.
 *
 * Žádný kód třetí strany se nenačte dřív, než návštěvník souhlasí. Výchozí
 * „zamítnuto" pro Google nastavuje už <head> (components/tracking.blade.php).
 *
 * Konfigurace (ID kódů) přichází z Nastavení → SEO a měření přes
 * `window.__tavoTracking`. Když chybí, web nic neměří a lišta se neukáže.
 */

const KEY = 'tavo-consent';

/**
 * Při změně kategorií nebo toho, co v nich běží, zvyš verzi. Všichni pak
 * dostanou lištu znovu, protože souhlasili s něčím jiným.
 */
const VERSION = 1;

/** Po roce se ptáme znovu. */
const MAX_AGE = 365 * 24 * 60 * 60 * 1000;

/** Cookies, které po sobě měřicí kódy nechají a které při odvolání souhlasu mažeme. */
const TRACKING_COOKIES = /^(_ga|_gid|_gat|_gcl|_fbp|_fbc|_clck|_clsk)/;

const config = window.__tavoTracking ?? {};
const loaded = new Set();

function readStored() {
    try {
        const data = JSON.parse(localStorage.getItem(KEY));

        if (! data || data.v !== VERSION || Date.now() - data.at > MAX_AGE) return null;

        return data;
    } catch {
        return null;
    }
}

function store(choice) {
    try {
        localStorage.setItem(KEY, JSON.stringify({ v: VERSION, at: Date.now(), ...choice }));
        // Původní lišta ukládala jen „all" nebo „necessary" a neznala marketing.
        localStorage.removeItem('tavo-cookies');
    } catch {
        // Prohlížeč úložiště nedovolí. Volba platí aspoň do konce návštěvy.
    }
}

function loadScript(id, src) {
    if (loaded.has(id)) return false;

    loaded.add(id);

    const script = document.createElement('script');
    script.async = true;
    script.src = src;
    document.head.appendChild(script);

    return true;
}

function loadGtm() {
    if (! config.gtm || loaded.has('gtm')) return;

    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
    loadScript('gtm', `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(config.gtm)}`);
}

function loadGa4() {
    if (! config.ga4 || loaded.has('ga4')) return;

    loadScript('ga4', `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(config.ga4)}`);
    window.gtag('js', new Date());
    window.gtag('config', config.ga4);
}

function loadClarity() {
    if (! config.clarity || loaded.has('clarity')) return;

    window.clarity = window.clarity || function () {
        (window.clarity.q = window.clarity.q || []).push(arguments);
    };
    loadScript('clarity', `https://www.clarity.ms/tag/${encodeURIComponent(config.clarity)}`);
}

function loadMetaPixel() {
    if (! config.metaPixel || loaded.has('metaPixel')) return;

    // Oficiální zavaděč od Mety, jen přepsaný čitelně. Frontu `fbq` založí
    // hned, takže volání před načtením skriptu se neztratí.
    const fbq = function () {
        fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments);
    };
    if (! window._fbq) window._fbq = fbq;
    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.queue = [];
    window.fbq = window.fbq || fbq;

    loadScript('metaPixel', 'https://connect.facebook.net/en_US/fbevents.js');
    window.fbq('init', config.metaPixel);
    window.fbq('track', 'PageView');
}

/** Předá volbu Googlu a Clarity a načte, co je povolené. */
function apply({ analytics, marketing }) {
    const state = (granted) => (granted ? 'granted' : 'denied');

    window.gtag?.('consent', 'update', {
        analytics_storage: state(analytics),
        ad_storage: state(marketing),
        ad_user_data: state(marketing),
        ad_personalization: state(marketing),
    });

    if (analytics) {
        loadGa4();
        loadClarity();
        // Clarity v EU bez tohohle signálu běží bez cookies a nespáruje návštěvy.
        window.clarity?.('consentv2', { analytics_Storage: 'granted', ad_Storage: state(marketing) });
    }

    if (marketing) loadMetaPixel();
    if (analytics || marketing) loadGtm();
}

/**
 * Smaže cookies měřicích kódů. Google je zakládá na hlavní doméně
 * (.taveo.cz), proto se zkouší každá úroveň domény zvlášť.
 */
function clearTrackingCookies() {
    const parts = location.hostname.split('.');
    const domains = [''];

    for (let i = 0; i < parts.length - 1; i++) {
        domains.push(`; domain=.${parts.slice(i).join('.')}`);
    }

    document.cookie.split(';').map((c) => c.split('=')[0].trim()).filter((name) => TRACKING_COOKIES.test(name)).forEach((name) => {
        domains.forEach((domain) => {
            document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/${domain}`;
        });
    });
}

export function registerConsent(Alpine) {
    const stored = readStored();

    Alpine.store('consent', {
        /** Lištu má smysl ukazovat, jen když web něco měří. */
        enabled: Object.keys(config).length > 0,
        decided: stored !== null,
        analytics: stored?.analytics ?? false,
        marketing: stored?.marketing ?? false,
        settingsOpen: false,

        /** Prvek, ze kterého se nastavení otevřelo. Po zavření se na něj vrátí fokus. */
        openedFrom: null,

        acceptAll() {
            this.save({ analytics: true, marketing: true });
        },

        rejectAll() {
            this.save({ analytics: false, marketing: false });
        },

        /** Uloží přepínače z podrobného nastavení. */
        saveSelection() {
            this.save({ analytics: this.analytics, marketing: this.marketing });
        },

        save(choice) {
            const previous = readStored();
            const revoked = previous
                && ((previous.analytics && ! choice.analytics) || (previous.marketing && ! choice.marketing));

            store(choice);
            this.analytics = choice.analytics;
            this.marketing = choice.marketing;
            this.decided = true;
            this.closeSettings();

            if (revoked) {
                // Načtený skript z paměti stránky vyhodit nejde. Smažeme jeho
                // cookies a načteme stránku znovu, tentokrát už bez něj.
                window.gtag?.('consent', 'update', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
                clearTrackingCookies();
                location.reload();

                return;
            }

            apply(choice);
        },

        openSettings() {
            const stored = readStored();
            this.analytics = stored?.analytics ?? false;
            this.marketing = stored?.marketing ?? false;
            this.openedFrom = document.activeElement;
            this.settingsOpen = true;
        },

        closeSettings() {
            if (! this.settingsOpen) return;

            this.settingsOpen = false;
            this.openedFrom?.focus?.();
            this.openedFrom = null;
        },
    });

    if (stored) apply(stored);
}

/**
 * Odeslaná poptávka. Volá se až po úspěšné odpovědi serveru, takže se
 * nezapočítá formulář, který spadl na validaci nebo na ochraně proti spamu.
 */
export function trackLead() {
    const consent = readStored();

    if (! consent) return;

    if (consent.marketing && config.metaPixel) window.fbq?.('track', 'Lead');

    if (consent.analytics && config.ga4) window.gtag?.('event', 'generate_lead');

    // Pro GTM: spouštěč typu „Vlastní událost" s názvem generate_lead.
    if ((consent.analytics || consent.marketing) && config.gtm) window.dataLayer?.push({ event: 'generate_lead' });

    // V Clarity pak jde odfiltrovat nahrávky návštěv, které skončily poptávkou.
    if (consent.analytics && config.clarity) window.clarity?.('event', 'lead');
}
