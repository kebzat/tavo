@props(['data'])

@if (filled($data['text'] ?? null))
    <section data-block-bg="cream" class="section-x section-y-sm bg-cream">
        <figure class="container-tavo m-0 max-w-[900px]">
            <blockquote data-reveal class="text-quote m-0 border-l-2 border-brick pl-[clamp(20px,3vw,40px)] font-extrabold tracking-[-.02em]">
                „{{ $data['text'] }}“
            </blockquote>

            @if (filled($data['author'] ?? null))
                <figcaption data-reveal class="mt-6 pl-[clamp(20px,3vw,40px)] text-sm font-semibold tracking-[.06em] text-muted uppercase">
                    {{ $data['author'] }}
                </figcaption>
            @endif
        </figure>
    </section>
@endif
