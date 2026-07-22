<x-layout.app
    :title="$page->seo_title ?: $page->title"
    :description="$page->seo_description ?: $page->perex">

    <header class="section-x pt-[150px] pb-10">
        <div class="container-tavo max-w-[900px]">
            <a href="{{ route('home') }}" data-reveal
               class="mb-[30px] inline-flex items-center gap-2 text-[13px] font-semibold tracking-[.12em] text-muted uppercase">
                ← Zpět na úvod
            </a>
            <h1 data-reveal class="text-h2-lg m-0 font-extrabold tracking-[-.03em]">{{ $page->title }}</h1>
            @if ($page->perex)
                <p data-reveal class="text-perex mt-6 mb-0 max-w-[52ch] text-body">{{ $page->perex }}</p>
            @endif
        </div>
    </header>

    <section class="section-x pb-[clamp(70px,9vw,120px)]">
        <div data-reveal class="container-tavo prose-tavo max-w-[900px]">
            {!! $page->content !!}
        </div>
    </section>
</x-layout.app>
