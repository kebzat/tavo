@props(['logos'])

{{-- Pás log pod referencemi. Loga zůstávají v barvách značek; ve stupních
     šedi by se některá (světle žlutá, tenké písmo) ztratila. Popisek pod
     nadpisem vede na Tomův osobní web, kde je víc projektů. --}}
<section id="klienti" class="section-x bg-cream pb-[clamp(80px,11vw,150px)]">
    <div class="container-tavo border-t border-ink/14 pt-[clamp(40px,5vw,64px)]">
        <div class="mb-[clamp(28px,3.4vw,44px)] flex flex-wrap items-end justify-between gap-x-10 gap-y-4">
            <h2 data-reveal class="text-h3-sm m-0 max-w-[22ch] font-extrabold tracking-[-.01em]">
                {{ text('home.loga_nadpis', 'Pro koho jsme stavěli weby a e-shopy', 'Homepage', 'Nadpis nad pásem log klientů') }}
            </h2>
            <p data-reveal class="m-0 max-w-[46ch] text-[15px] leading-[1.55] text-muted">
                {{ text('home.loga_popis', 'Weby, které Tom postavil přímo pro klienty. Další projekty najdete na jeho webu', 'Homepage', 'Text vedle nadpisu nad logy klientů; za ním následuje odkaz') }}
                <a href="{{ text('home.loga_odkaz_url', 'https://tomaskebza.cz/reference', 'Homepage', 'Adresa odkazu vedle log klientů') }}"
                   target="_blank" rel="noopener"
                   class="font-bold whitespace-nowrap text-ink underline decoration-brick decoration-2 underline-offset-4">{{ text('home.loga_odkaz', 'tomaskebza.cz', 'Homepage', 'Text odkazu vedle log klientů') }}&nbsp;↗</a>
            </p>
        </div>

        <ul data-reveal class="m-0 grid list-none grid-cols-2 items-center gap-x-8 gap-y-7 p-0 min-[560px]:grid-cols-3 menu:grid-cols-5 menu:gap-x-12 menu:gap-y-10">
            @foreach ($logos as $logo)
                <li class="flex h-14 items-center justify-center">
                    <img src="{{ $logo['src'] }}"
                         alt="{{ $logo['alt'] }}"
                         @if ($logo['width']) width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" @endif
                         loading="lazy" decoding="async"
                         class="h-auto max-h-11 w-auto max-w-[150px] object-contain">
                </li>
            @endforeach
        </ul>
    </div>
</section>
