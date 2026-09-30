{{--
    Cookie lišta a podrobné nastavení souhlasu. Stav drží Alpine store
    `consent` z resources/js/consent.js, odtamtud se taky načítají měřicí kódy.

    Souhlas je výrazné zelené tlačítko vpravo (na mobilu blíž k palci),
    „Odmítnout" a „Nastavení" jsou menší odkazy vlevo. Odmítnutí ale musí
    zůstat v první vrstvě lišty, jedním kliknutím. Schovat ho jen do
    nastavení by byl přesně ten vzor, za který dozorové úřady pokutují.

    Nastavení se dá kdykoliv znovu otevřít odkazem „Nastavení cookies"
    v patičce.
--}}
@if ($tracking->needsConsent())
    <div x-data
         x-show="! $store.consent.decided && ! $store.consent.settingsOpen && ! $store.nav.open"
         x-cloak
         x-transition
         role="region"
         aria-label="{{ text('cookies.lista_nazev', 'Souhlas s cookies', 'Cookie lišta', 'Název lišty pro čtečky obrazovky') }}"
         class="fixed inset-x-4 bottom-4 z-[60] mx-auto max-w-[620px] rounded-card bg-ink p-6 text-cream shadow-2xl md:inset-x-auto md:right-6 md:bottom-6 md:p-7">
        <p class="m-0 text-[15px] font-bold">
            {{ text('cookies.lista_nadpis', 'Můžeme měřit, jak web používáte?', 'Cookie lišta', 'Nadpis lišty') }}
        </p>
        <p class="m-0 mt-2 text-[14px] leading-[1.55] text-cream/75">
            {{ text('cookies.lista_text', 'S vaším souhlasem zapneme Google Analytics a Microsoft Clarity, abychom viděli, co se na webu čte. Meta Pixel nám zase řekne, jestli reklama na Facebooku a Instagramu přivedla někoho, kdo nám pak napsal. Bez souhlasu se nic z toho nenačte.', 'Cookie lišta', 'Hlavní text lišty') }}
            <a href="{{ url('/cookies') }}" class="text-cream underline underline-offset-2 hover:text-brick">{{ text('cookies.lista_odkaz', 'Víc o cookies', 'Cookie lišta', 'Odkaz na stránku Cookies') }}</a>
        </p>

        <div class="mt-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-[13px] text-cream/55">
                <button type="button" @click="$store.consent.rejectAll()"
                        class="py-2 underline underline-offset-4 transition hover:text-cream">
                    {{ text('cookies.odmitnout', 'Odmítnout', 'Cookie lišta', 'Odkaz pro jen nezbytné cookies') }}
                </button>
                <span aria-hidden="true">·</span>
                <button type="button" @click="$store.consent.openSettings()"
                        class="py-2 underline underline-offset-4 transition hover:text-cream">
                    {{ text('cookies.nastaveni', 'Nastavení', 'Cookie lišta', 'Odkaz, který otevře výběr kategorií') }}
                </button>
            </div>
            <button type="button" @click="$store.consent.acceptAll()"
                    class="shrink-0 rounded-pill bg-go px-8 py-3.5 text-[15px] font-bold text-cream shadow-[0_10px_24px_-10px_rgba(30,122,56,.8)] transition hover:-translate-y-0.5 hover:bg-go-dark">
                {{ text('cookies.souhlasim', 'Souhlasím', 'Cookie lišta', 'Hlavní tlačítko souhlasu se vším') }}
            </button>
        </div>
    </div>

    <div x-data
         x-show="$store.consent.settingsOpen"
         x-cloak
         x-transition.opacity
         @keydown.escape.window="$store.consent.closeSettings()"
         @click.self="$store.consent.closeSettings()"
         class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/60 p-4 md:items-center">
        <div role="dialog"
             aria-modal="true"
             aria-labelledby="cookies-nastaveni-nadpis"
             tabindex="-1"
             x-effect="$store.consent.settingsOpen && $nextTick(() => $el.focus())"
             class="max-h-[calc(100dvh-2rem)] w-full max-w-[560px] overflow-y-auto rounded-card bg-cream p-6 text-ink shadow-2xl outline-none md:p-8">
            <div class="flex items-start justify-between gap-4">
                <h2 id="cookies-nastaveni-nadpis" class="m-0 text-[22px] font-extrabold tracking-[-.01em]">
                    {{ text('cookies.nastaveni_nadpis', 'Nastavení cookies', 'Cookie lišta', 'Nadpis okna s výběrem kategorií') }}
                </h2>
                <button type="button" @click="$store.consent.closeSettings()"
                        aria-label="{{ text('cookies.zavrit', 'Zavřít', 'Cookie lišta', 'Popisek křížku pro čtečky') }}"
                        class="-mt-1 -mr-2 rounded-pill p-2 text-ink/60 transition hover:bg-ink/5 hover:text-ink">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>

            <div class="mt-5 divide-y divide-ink/12 border-y border-ink/12">
                <div class="flex items-start justify-between gap-5 py-4">
                    <div>
                        <p class="m-0 text-[15px] font-bold">{{ text('cookies.nezbytne', 'Nezbytné', 'Cookie lišta', 'Název kategorie') }}</p>
                        <p class="m-0 mt-1 text-[13px] leading-[1.55] text-body">{{ text('cookies.nezbytne_popis', 'Drží web v chodu: odeslání formuláře, ochranu proti spamu a zapamatování téhle volby. Vypnout nejdou.', 'Cookie lišta', 'Popis kategorie') }}</p>
                    </div>
                    <span class="shrink-0 pt-0.5 text-[13px] font-bold text-muted">{{ text('cookies.vzdy_zapnute', 'Vždy zapnuté', 'Cookie lišta', 'Stav nezbytných cookies') }}</span>
                </div>

                @if ($tracking->hasAnalytics())
                    <label class="flex cursor-pointer items-start justify-between gap-5 py-4">
                        <span>
                            <span class="block text-[15px] font-bold">{{ text('cookies.analyticke', 'Analytické', 'Cookie lišta', 'Název kategorie') }}</span>
                            <span class="mt-1 block text-[13px] leading-[1.55] text-body">{{ text('cookies.analyticke_popis', 'Google Analytics a Microsoft Clarity. Ukážou, které stránky se čtou a kde lidé na webu váhají. Clarity zaznamenává pohyb po stránce a kliknutí, co vyplníte do formuláře, nevidí.', 'Cookie lišta', 'Popis kategorie') }}</span>
                        </span>
                        <input type="checkbox" role="switch" x-model="$store.consent.analytics" class="peer sr-only">
                        <span aria-hidden="true"
                              class="relative mt-0.5 h-6 w-11 shrink-0 rounded-pill bg-ink/25 transition peer-checked:bg-moss peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brick after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-cream after:shadow after:transition peer-checked:after:translate-x-5"></span>
                    </label>
                @endif

                @if ($tracking->hasMarketing())
                    <label class="flex cursor-pointer items-start justify-between gap-5 py-4">
                        <span>
                            <span class="block text-[15px] font-bold">{{ text('cookies.marketingove', 'Marketingové', 'Cookie lišta', 'Název kategorie') }}</span>
                            <span class="mt-1 block text-[13px] leading-[1.55] text-body">{{ text('cookies.marketingove_popis', 'Meta Pixel. Zjistíme díky němu, jestli reklama na Facebooku a Instagramu přivedla někoho, kdo nám pak napsal, a můžeme ji ukázat lidem, kteří už web viděli.', 'Cookie lišta', 'Popis kategorie') }}</span>
                        </span>
                        <input type="checkbox" role="switch" x-model="$store.consent.marketing" class="peer sr-only">
                        <span aria-hidden="true"
                              class="relative mt-0.5 h-6 w-11 shrink-0 rounded-pill bg-ink/25 transition peer-checked:bg-moss peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brick after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-cream after:shadow after:transition peer-checked:after:translate-x-5"></span>
                    </label>
                @endif
            </div>

            <p class="m-0 mt-4 text-[13px] leading-[1.55] text-body">
                {{ text('cookies.nastaveni_poznamka', 'Volbu si pamatujeme rok. Změnit ji můžete kdykoliv odkazem Nastavení cookies v patičce webu.', 'Cookie lišta', 'Poznámka pod kategoriemi') }}
                <a href="{{ url('/cookies') }}" class="font-bold underline underline-offset-2">{{ text('cookies.lista_odkaz', 'Víc o cookies', 'Cookie lišta', 'Odkaz na stránku Cookies') }}</a>
            </p>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <button type="button" @click="$store.consent.saveSelection()"
                        class="rounded-pill border-[1.5px] border-ink/25 px-5 py-2.5 text-[14px] font-bold text-ink transition hover:border-ink">
                    {{ text('cookies.ulozit', 'Uložit výběr', 'Cookie lišta', 'Tlačítko v okně nastavení') }}
                </button>
                <button type="button" @click="$store.consent.acceptAll()"
                        class="rounded-pill bg-go px-8 py-3.5 text-[15px] font-bold text-cream transition hover:bg-go-dark">
                    {{ text('cookies.prijmout_vse', 'Přijmout vše', 'Cookie lišta', 'Tlačítko souhlasu se vším v okně nastavení') }}
                </button>
            </div>
        </div>
    </div>
@endif
