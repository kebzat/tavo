@props([
    'eyebrow' => null,
    'title' => 'Řekněte nám, co potřebujete.',
    'perex' => null,
    'form' => false,
    'secondaryLabel' => null,
    'secondaryUrl' => null,
    'id' => null,
])

<section @if ($id) id="{{ $id }}" @endif class="section-x section-y bg-brick text-ink">
    <div class="{{ $form ? 'container-tavo max-w-[1300px]' : 'container-tavo max-w-[1200px]' }} text-center">
        @if ($eyebrow)
            <x-eyebrow data-reveal tone="ink" class="mb-[26px]">{{ $eyebrow }}</x-eyebrow>
        @endif

        <h2 data-reveal class="{{ $form ? 'text-cta' : 'text-cta-sm' }} mx-auto max-w-[16ch] font-extrabold tracking-[-.03em]">
            {{ $title }}
        </h2>

        @if ($perex)
            <p data-reveal class="text-lead mx-auto mt-7 mb-10 max-w-[52ch] text-ink/80">{{ $perex }}</p>
        @endif

        <div data-reveal class="mt-9 flex flex-wrap justify-center gap-3.5">
            <x-btn :href="$contact->emailHref()" variant="dark" size="lg">{{ $contact->email }}</x-btn>

            @if ($secondaryLabel && $secondaryUrl)
                <x-btn :href="$secondaryUrl" variant="ghost-dark" size="lg">{{ $secondaryLabel }}</x-btn>
            @else
                <x-btn :href="$contact->phoneHref()" variant="ghost-dark" size="lg">Zavolat</x-btn>
            @endif
        </div>

        @if ($form)
            <x-lead-form />
        @endif
    </div>
</section>
