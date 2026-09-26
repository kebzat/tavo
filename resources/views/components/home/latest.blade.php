@props(['latest'])

@php($case = $latest['case'])

{{-- Nejnovější projekt: text vlevo, posuvník „před a po" (nebo obrázek) vpravo.
     Stojí za sekcí se dvěma situacemi, jejíž spodní odsazení mu dělá horní
     okraj, takže tu je jen spodní. Na mobilu jde obrázek pod text. --}}
<section id="nejnovejsi-projekt" class="section-x bg-cream pb-[clamp(80px,11vw,150px)]">
    <div class="container-tavo grid grid-cols-1 items-center gap-[clamp(28px,4vw,64px)] menu:grid-cols-[0.8fr_1.2fr]">
        <div>
            <x-eyebrow data-reveal :rule="true" class="mb-4">
                {{ text('home.nejnovejsi_nadtitulek', 'Nejnovější projekt', 'Homepage', 'Nadtitulek sekce s nejnovějším projektem') }}
            </x-eyebrow>

            <h2 data-reveal class="text-h2-sm m-0 font-extrabold tracking-[-.02em]">{{ $case->title }}</h2>

            @if ($case->eyebrow)
                <p data-reveal class="mt-3 mb-0 text-[13px] font-bold tracking-[.12em] text-muted uppercase">{{ $case->eyebrow }}</p>
            @endif

            @if ($case->excerpt)
                <p data-reveal class="mt-5 mb-0 max-w-[46ch] text-base leading-[1.6] text-body">{{ $case->excerpt }}</p>
            @endif

            <a data-reveal href="{{ route('cases.show', $case->slug) }}"
               class="mt-7 inline-block border-b-2 border-brick pb-[3px] text-[15px] font-bold text-ink">
                {{ text('home.nejnovejsi_odkaz', 'Prohlédnout projekt', 'Homepage', 'Odkaz na detail nejnovějšího projektu') }} →
            </a>
        </div>

        <div data-reveal>
            @if ($latest['comparison'])
                <x-before-after-slider
                    :before="$latest['comparison']['before_image']"
                    :after="$latest['comparison']['after_image']"
                    :before-label="($latest['comparison']['before_label'] ?? null) ?: 'Před'"
                    :after-label="($latest['comparison']['after_label'] ?? null) ?: 'Po'"
                    sizes="(min-width: 861px) 52vw, 88vw"
                    class="ring-1 ring-ink/10" />

                <p class="mt-3 mb-0 text-[13px] leading-[1.5] text-muted">
                    {{ text('home.nejnovejsi_napoveda', 'Přetáhněte čáru a porovnejte původní a nový web.', 'Homepage', 'Nápověda pod posuvníkem před a po u nejnovějšího projektu') }}
                </p>
            @else
                <a href="{{ route('cases.show', $case->slug) }}" class="block">
                    <x-media :image="$latest['image']" fit="natural" radius="rounded-card"
                             sizes="(min-width: 861px) 52vw, 88vw"
                             class="ring-1 ring-ink/10" />
                </a>
            @endif
        </div>
    </div>
</section>
