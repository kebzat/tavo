@props(['latest'])

@php($case = $latest['case'])

{{-- Nejnovější projekt hned pod úvodem: velký obrázek, ať homepage nezačíná
     čtyřmi textovými sekcemi za sebou. Textu je tu schválně málo, celý příběh
     projektu je na jeho detailu. --}}
<section id="nejnovejsi-projekt" class="section-x bg-cream pb-[clamp(80px,11vw,150px)]">
    <div class="container-tavo">
        <div class="mb-[clamp(24px,3vw,36px)] flex flex-wrap items-end justify-between gap-x-10 gap-y-5">
            <div>
                <x-eyebrow data-reveal :rule="true" class="mb-4">
                    {{ text('home.nejnovejsi_nadtitulek', 'Nejnovější projekt', 'Homepage', 'Nadtitulek sekce s nejnovějším projektem pod úvodem') }}
                </x-eyebrow>
                <h2 data-reveal class="text-h2 m-0 font-extrabold tracking-[-.02em]">{{ $case->title }}</h2>
                @if ($case->eyebrow)
                    <p data-reveal class="mt-3 mb-0 text-[13px] font-bold tracking-[.12em] text-muted uppercase">{{ $case->eyebrow }}</p>
                @endif
            </div>

            <a data-reveal href="{{ route('cases.show', $case->slug) }}"
               class="border-b-2 border-brick pb-[3px] text-[15px] font-bold whitespace-nowrap text-ink">
                {{ text('home.nejnovejsi_odkaz', 'Prohlédnout projekt', 'Homepage', 'Odkaz na detail nejnovějšího projektu') }} →
            </a>
        </div>

        @if ($latest['comparison'])
            <x-before-after-slider data-reveal
                :before="$latest['comparison']['before_image']"
                :after="$latest['comparison']['after_image']"
                :before-label="($latest['comparison']['before_label'] ?? null) ?: 'Před'"
                :after-label="($latest['comparison']['after_label'] ?? null) ?: 'Po'"
                sizes="(min-width: 1660px) 1500px, 88vw"
                class="ring-1 ring-ink/10" />

            <p data-reveal class="mt-4 mb-0 text-[13px] leading-[1.5] text-muted">
                {{ text('home.nejnovejsi_napoveda', 'Přetáhněte čáru a porovnejte původní a nový web.', 'Homepage', 'Nápověda pod posuvníkem před a po u nejnovějšího projektu') }}
            </p>
        @else
            <a data-reveal href="{{ route('cases.show', $case->slug) }}" class="block">
                <x-media :image="$latest['image']" fit="natural" radius="rounded-card"
                         sizes="(min-width: 1660px) 1500px, 88vw"
                         class="ring-1 ring-ink/10" />
            </a>
        @endif
    </div>
</section>
