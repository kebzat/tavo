<footer class="section-x bg-ink pt-[clamp(56px,7vw,90px)] pb-10 text-cream">
    <div class="container-tavo">
        <div class="grid grid-cols-2 gap-x-[30px] gap-y-10 border-b border-cream/15 pb-14 menu:grid-cols-[2fr_1fr_1fr_1.4fr]">
            <div>
                <img src="/images/tavo-logo-cream.svg" alt="{{ $site->brand_name }}" class="mb-[18px] block h-[34px] w-auto">
                <p class="m-0 max-w-[34ch] text-[15px] leading-[1.6] text-cream/60">{{ $site->brand_claim }}</p>
            </div>

            @foreach ($site->footer_columns as $column)
                <div>
                    <div class="mb-[18px] text-xs font-bold tracking-[.14em] text-cream/45 uppercase">{{ $column['title'] }}</div>
                    <div class="flex flex-col gap-3">
                        @foreach ($column['links'] as $link)
                            <a href="{{ $link['url'] }}"
                               @if (str_starts_with($link['url'], 'http')) target="_blank" rel="noopener" @endif
                               class="text-[15px] text-cream transition-colors hover:text-brick">{{ $link['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap justify-between gap-5 pt-[26px] text-[13px] text-cream/45">
            <span>{{ $site->copyright }}</span>
            <div class="flex flex-wrap gap-5">
                <a href="{{ url('/ochrana-osobnich-udaju') }}" class="text-cream/45 transition-colors hover:text-cream">Ochrana osobních údajů</a>
                <a href="{{ url('/cookies') }}" class="text-cream/45 transition-colors hover:text-cream">Cookies</a>
                <span>{{ $site->footer_note }}</span>
            </div>
        </div>
    </div>
</footer>
