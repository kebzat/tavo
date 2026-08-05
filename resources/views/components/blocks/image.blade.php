@props(['data'])

@if ($data['image_image'] ?? null)
    <section data-block-bg="cream" class="section-x section-y-sm bg-cream">
        <figure class="container-tavo m-0 max-w-[900px]">
            <x-media data-reveal
                     :image="$data['image_image']"
                     fit="natural"
                     sizes="(min-width: 1020px) 900px, 88vw" />

            @if (filled($data['caption'] ?? null))
                <figcaption data-reveal class="mt-4 text-[13px] leading-[1.5] text-muted">
                    {{ $data['caption'] }}
                </figcaption>
            @endif
        </figure>
    </section>
@endif
