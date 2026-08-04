@props(['data'])

@if (filled($data['body'] ?? null))
    <section data-block-bg="cream" class="section-x section-y-sm">
        <div data-reveal class="container-tavo prose-tavo max-w-[900px]">
            {!! $data['body'] !!}
        </div>
    </section>
@endif
