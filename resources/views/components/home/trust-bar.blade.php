@props(['items'])

{{-- Pruh s čísly hned pod úvodem. Na mobilu dva sloupce, od `menu:` všechna
     čísla vedle sebe. Vlasové linky mezi buňkami dělá podklad kontejneru
     prosvítající mezerou `gap-px`, takže sedí pro libovolný počet položek. --}}
<section aria-label="{{ text('home.cisla_popisek', 'TAVEO v číslech', 'Homepage', 'Popisek pruhu s čísly pro hlasové čtečky') }}"
         class="section-x bg-cream pb-[clamp(56px,7vw,96px)]">
    <ul data-reveal class="container-tavo m-0 grid list-none grid-cols-2 gap-px border-y border-ink/14 bg-ink/14 p-0 menu:auto-cols-fr menu:grid-flow-col menu:grid-cols-none">
        @foreach ($items as $item)
            <li class="bg-cream px-3 py-6 text-center odd:last:col-span-2 menu:py-8 menu:odd:last:col-span-1">
                <span class="text-metric-sm block font-extrabold tracking-[-.02em] text-ink">{{ $item['value'] }}</span>
                <span class="mt-1.5 block text-[13px] leading-[1.4] text-muted">{{ $item['label'] }}</span>
            </li>
        @endforeach
    </ul>
</section>
