{{--
    Mobilní menu drží globální Alpine store `nav`, aby se podle něj mohla schovat
    i cookie lišta (jinak by překryla spodní CTA v otevřeném menu).

    Logo se mezi tmavou a krémovou variantou překlápí přes opacity (ne x-show),
    aby při načítání neproblikla obě loga naráz a nepřeskakoval layout.
    Animace hamburgeru a panelu jsou v resources/css/app.css.
--}}
<div x-data
     @keydown.escape.window="$store.nav.close()">
    <nav data-nav
         :data-open="$store.nav.open"
         class="fixed inset-x-0 top-0 z-50 flex items-center justify-between px-[6vw] py-[18px]">
        <a href="{{ route('home') }}" class="relative flex items-center" aria-label="{{ $site->brand_name }} — úvodní stránka">
            <img src="/images/taveo-logo-dark.svg" alt="{{ $site->brand_name }}"
                 class="block h-[26px] w-auto transition-opacity duration-300 ease-tavo"
                 :class="$store.nav.open && 'opacity-0'">
            <img src="/images/taveo-logo-cream.svg" alt="" aria-hidden="true"
                 class="absolute top-0 left-0 block h-[26px] w-auto opacity-0 transition-opacity duration-300 ease-tavo"
                 :class="$store.nav.open && 'opacity-100'">
        </a>

        <div class="hidden items-center gap-[34px] menu:flex">
            @foreach ($site->nav_links as $link)
                <a href="{{ $link['url'] }}" class="text-sm font-semibold text-ink transition-colors hover:text-brick">
                    {{ $link['label'] }}
                </a>
            @endforeach

            <a href="{{ $site->nav_cta_url }}"
               class="rounded-pill bg-ink px-5 py-[11px] text-sm font-bold text-cream transition duration-300 ease-tavo hover:-translate-y-0.5 hover:bg-brick">
                {{ $site->nav_cta_label }}
            </a>
        </div>

        <button type="button"
                data-burger
                @click="$store.nav.toggle()"
                :aria-expanded="$store.nav.open ? 'true' : 'false'"
                aria-label="Menu"
                class="relative flex h-12 w-12 cursor-pointer items-center justify-center rounded-2xl bg-ink transition-[background-color,border-radius,transform] duration-300 ease-tavo active:scale-90 menu:hidden">
            <span data-burger-bar></span>
            <span data-burger-bar></span>
        </button>
    </nav>

    <div x-show="$store.nav.open"
         x-cloak
         data-nav-panel
         x-transition:enter="nav-panel-motion"
         x-transition:enter-start="nav-panel-hidden"
         x-transition:enter-end="nav-panel-shown"
         x-transition:leave="nav-panel-motion"
         x-transition:leave-start="nav-panel-shown"
         x-transition:leave-end="nav-panel-hidden"
         class="fixed inset-0 z-49 flex flex-col justify-between overflow-y-auto overscroll-contain bg-ink px-[8vw] pt-28 pb-10 text-cream">
        <div class="flex flex-col gap-1.5">
            @foreach ($site->nav_links as $link)
                <a href="{{ $link['url'] }}"
                   data-nav-item
                   style="animation-delay: {{ 120 + $loop->index * 70 }}ms"
                   @click="$store.nav.close()"
                   class="border-b border-cream/15 py-2.5 text-[34px] font-extrabold text-cream">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <a href="{{ $site->nav_cta_url }}"
           data-nav-item
           style="animation-delay: {{ 120 + count($site->nav_links) * 70 }}ms"
           @click="$store.nav.close()"
           class="rounded-2xl bg-brick p-[18px] text-center text-[17px] font-bold text-ink">
            {{ $site->nav_cta_label }} →
        </a>
    </div>
</div>
