<x-layout.app
    :title="$page->seo_title ?: $page->title"
    :description="$page->seo_description ?: $page->perex">

    {{-- Bez bloků končí stránka hned pod perexem, pak si odsazení nad patičkou musí vzít hlavička. --}}
    <header class="section-x pt-[150px] {{ $blocks->isEmpty() ? 'pb-[clamp(70px,9vw,120px)]' : 'pb-10' }}">
        <div class="container-tavo {{ $page->hero_eyebrow ? '' : 'max-w-[900px]' }}">
            <a href="{{ route('home') }}" data-reveal
               class="mb-[30px] flex w-fit items-center gap-2 text-[13px] font-semibold tracking-[.12em] text-muted uppercase">
                ← Zpět na úvod
            </a>

            @if ($page->hero_eyebrow)
                <x-eyebrow data-reveal :rule="true" class="mb-6">{{ $page->hero_eyebrow }}</x-eyebrow>
            @endif

            {{-- Nadtitulek dělá z hlavičky dopadovou stránku, proto i výrazně větší nadpis. --}}
            <h1 data-reveal
                class="{{ $page->hero_eyebrow ? 'text-page-title max-w-[15ch]' : 'text-h2-lg' }} m-0 font-extrabold tracking-[-.03em]">
                {{ $headline['before'] }}@if ($headline['accent'])<span class="text-brick italic">{{ $headline['accent'] }}</span>@endif{{ $headline['after'] }}
            </h1>

            @if ($page->perex)
                <p data-reveal class="text-perex mt-[34px] mb-0 max-w-[52ch] text-body">{{ $page->perex }}</p>
            @endif

            @if ($page->hero_cta)
                <div data-reveal class="mt-9 flex flex-wrap gap-3.5">
                    <x-btn :href="$contact->emailHref()" size="lg">{{ $contact->email }}</x-btn>
                    <x-btn :href="$contact->phoneHref()" variant="ghost" size="lg">Zavolat</x-btn>
                </div>
            @endif
        </div>
    </header>

    {{-- Odsazení i podmínku zobrazení si každý blok řeší sám, viz components/blocks/. --}}
    @foreach ($blocks as $block)
        <x-dynamic-component :component="$block['component']" :data="$block['data']" />
    @endforeach
</x-layout.app>
