@php($gtmId = app(App\Settings\SeoSettings::class)->gtm_id)

<div x-data="{
        open: false,
        init() {
            const stored = localStorage.getItem('tavo-cookies');
            if (stored === null) { this.open = true; }
            else if (stored === 'all') { this.loadAnalytics(); }
        },
        accept(choice) {
            localStorage.setItem('tavo-cookies', choice);
            this.open = false;
            if (choice === 'all') this.loadAnalytics();
        },
        loadAnalytics() {
            const id = window.__tavoGtmId;
            if (!id || document.getElementById('tavo-gtm')) return;
            const s = document.createElement('script');
            s.id = 'tavo-gtm';
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtm.js?id=' + id;
            document.head.appendChild(s);
        },
     }"
     x-show="open && ! $store.nav.open"
     x-cloak
     x-transition
     role="dialog"
     aria-modal="false"
     aria-label="Souhlas s cookies"
     class="fixed inset-x-4 bottom-4 z-[60] mx-auto max-w-2xl rounded-card bg-ink p-6 text-cream shadow-2xl md:inset-x-auto md:right-6 md:bottom-6">
    <p class="m-0 text-[14px] leading-[1.55] text-cream/75">
        Používáme nezbytné cookies pro běh webu.
        @if ($gtmId)
            S vaším souhlasem k tomu přidáme anonymní měření návštěvnosti, ať víme, co zlepšit.
        @endif
        Víc v <a href="{{ url('/cookies') }}" class="text-brick underline">zásadách cookies</a>.
    </p>

    <div class="mt-4 flex flex-wrap gap-3">
        @if ($gtmId)
            <button type="button" @click="accept('all')"
                    class="rounded-pill bg-brick px-5 py-2.5 text-[14px] font-bold text-cream transition hover:bg-brick-dark">
                Souhlasím se vším
            </button>
        @endif
        <button type="button" @click="accept('necessary')"
                class="rounded-pill border border-cream/30 px-5 py-2.5 text-[14px] font-bold text-cream transition hover:bg-cream hover:text-ink">
            Jen nezbytné
        </button>
    </div>
</div>
