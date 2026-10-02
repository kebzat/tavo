@props(['items'])

{{-- Pruh s čísly hned pod úvodem. Na mobilu dva sloupce, od `menu:` všechna
     čísla vedle sebe. Vlasové linky mezi buňkami dělá podklad kontejneru
     prosvítající mezerou `gap-px`, takže sedí pro libovolný počet položek. --}}
<section aria-label="{{ text('home.cisla_popisek', 'TAVEO v číslech', 'Homepage', 'Popisek pruhu s čísly pro hlasové čtečky') }}"
         class="section-x bg-cream pb-[clamp(56px,7vw,96px)]">
    <ul data-reveal class="container-tavo m-0 grid list-none grid-cols-2 gap-px border-y border-ink/14 bg-ink/14 p-0 menu:auto-cols-fr menu:grid-flow-col menu:grid-cols-none">
        @foreach ($items as $item)
            <li class="bg-cream text-center odd:last:col-span-2 menu:odd:last:col-span-1">
                {{-- Číslo s odkazem (např. hodnocení na Googlu → recenze) je
                     celé klikací, popisek dostane cihlovou linku a šipku. --}}
                @if (filled($item['url'] ?? null))
                    <a href="{{ $item['url'] }}" class="group block px-3 py-6 no-underline menu:py-8">
                        <span class="text-metric-sm block font-extrabold tracking-[-.02em] text-ink">{{ $item['value'] }}</span>
                        <span class="mt-1.5 inline-block border-b border-brick/60 text-[13px] leading-[1.4] text-muted transition-colors duration-300 group-hover:border-brick group-hover:text-ink">
                            {{ $item['label'] }} <span aria-hidden="true" class="text-brick">→</span>
                        </span>
                    </a>
                @else
                    <div class="px-3 py-6 menu:py-8">
                        <span class="text-metric-sm block font-extrabold tracking-[-.02em] text-ink">{{ $item['value'] }}</span>
                        <span class="mt-1.5 block text-[13px] leading-[1.4] text-muted">{{ $item['label'] }}</span>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
</section>
