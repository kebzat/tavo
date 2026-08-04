@props(['data'])

@if (filled($data['image_url'] ?? null))
    <section data-block-bg="cream" class="section-x section-y-sm bg-cream">
        <figure class="container-tavo m-0 max-w-[900px]">
            <x-media data-reveal
                     :url="$data['image_url']"
                     :alt="$data['image_alt'] ?? ''"
                     fit="natural" />

            @if (filled($data['caption'] ?? null))
                <figcaption data-reveal class="mt-4 text-[13px] leading-[1.5] text-muted">
                    {{ $data['caption'] }}
                </figcaption>
            @endif
        </figure>
    </section>
@endif
