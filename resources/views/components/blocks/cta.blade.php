@props(['data'])

@if (filled($data['title'] ?? null))
    <x-cta-band data-block-bg="brick"
                :eyebrow="$data['eyebrow'] ?? null"
                :title="$data['title']"
                :perex="$data['perex'] ?? null"
                :secondary-label="$data['secondary_label'] ?? null"
                :secondary-url="$data['secondary_url'] ?? null" />
@endif
