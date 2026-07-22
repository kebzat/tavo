{{--
    Mobilní menu drží globální Alpine store `nav`, aby se podle něj mohla schovat
    i cookie lišta (jinak by překryla spodní CTA v otevřeném menu).
--}}
<div x-data
     @keydown.escape.window="$store.nav.close()">
    <nav data-nav
         class="fixed inset-x-0 top-0 z-50 flex items-center justify-between px-[6vw] py-[18px]">
        <a href="{{ route('home') }}" class="flex items-center" aria-label="{{ $site->brand_name }} — úvodní stránka">
            <img src="/images/tavo-logo-dark.svg" alt="{{ $site->brand_name }}"
                 class="block h-[26px] w-auto" x-show="!$store.nav.open">
            <img src="/images/tavo-logo-cream.svg" alt="{{ $site->brand_name }}"
                 class="block h-[26px] w-auto" x-show="$store.nav.open" x-cloak>
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
                @click="$store.nav.toggle()"
                :aria-expanded="$store.nav.open ? 'true' : 'false'"
                aria-label="Menu"
                class="flex h-[46px] w-[46px] cursor-pointer flex-col items-center justify-center gap-1 rounded-xl bg-ink menu:hidden">
            <span class="block h-0.5 w-[18px] bg-cream transition-transform duration-300"
                  :class="$store.nav.open && 'translate-y-[3px] rotate-45'"></span>
            <span class="block h-0.5 w-[18px] bg-cream transition-transform duration-300"
                  :class="$store.nav.open && '-translate-y-[3px] -rotate-45'"></span>
        </button>
    </nav>

    <div x-show="$store.nav.open"
         x-cloak
         x-transition.opacity
         class="fixed inset-0 z-49 flex flex-col justify-between bg-ink px-[8vw] pt-24 pb-10 text-cream">
        <div class="flex flex-col gap-1.5">
            @foreach ($site->nav_links as $link)
                <a href="{{ $link['url'] }}"
                   @click="$store.nav.close()"
                   class="border-b border-cream/15 py-2.5 text-[34px] font-extrabold text-cream">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </div>

        <a href="{{ $site->nav_cta_url }}"
           @click="$store.nav.close()"
           class="rounded-2xl bg-brick p-[18px] text-center text-[17px] font-bold text-ink">
            {{ $site->nav_cta_label }} →
        </a>
    </div>
</div>
