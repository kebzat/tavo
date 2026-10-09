@props([
    'phone',
    'light' => false,
])

{{-- Dlouhý screenshot z mobilu v rámečku telefonu, ve kterém se dá scrollovat.
     Používá ho x-proposal.example v řadě telefonů i vedle okna prohlížeče. --}}
<div {{ $attributes->class('flex flex-col') }}>
    <div class="rounded-[46px] bg-[#0d0c0b] p-2.5 shadow-[0_30px_80px_-30px_rgba(0,0,0,.8)] ring-1 ring-cream/15">
        <div class="overflow-hidden rounded-[37px] bg-white">
            <div aria-hidden="true" class="flex h-8 items-center justify-center">
                <span class="h-5 w-20 rounded-full bg-[#0d0c0b]"></span>
            </div>
            <div tabindex="0" aria-label="{{ $phone['image']['alt'] ?: $phone['title'] }}"
                 class="h-[540px] overflow-y-auto overscroll-contain [scrollbar-width:none] focus-visible:outline-2 focus-visible:outline-brick menu:h-[620px]">
                <x-media :image="$phone['image']" fit="natural" radius="rounded-none" sizes="320px" />
            </div>
        </div>
    </div>
    @if ($phone['title'])
        <h3 class="mt-5 text-step font-extrabold tracking-[-.01em]">{{ $phone['title'] }}</h3>
    @endif
    @if ($phone['body'])
        <p @class([
            'mt-2 whitespace-pre-line text-perex',
            'text-cream/60' => ! $light,
            'text-body' => $light,
        ])>{{ $phone['body'] }}</p>
    @endif
</div>
