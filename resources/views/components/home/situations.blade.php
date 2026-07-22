@props(['home'])

<section class="section-x section-y bg-cream">
    <div class="container-tavo">
        <h2 data-reveal class="text-h2 mt-0 mb-[46px] max-w-[16ch] font-extrabold tracking-[-.02em]">
            {{ $home->situations_title }}
        </h2>

        <div class="grid grid-cols-1 gap-6 menu:grid-cols-2">
            @foreach ($home->situations as $situation)
                @php
                    $dark = ($situation['variant'] ?? 'dark') === 'dark';
                @endphp
                <a href="{{ $situation['cta_url'] }}"
                   data-reveal
                   class="flex min-h-[340px] flex-col justify-between rounded-card p-10 transition-transform duration-500 ease-tavo hover:-translate-y-2 {{ $dark ? 'bg-ink text-cream' : 'bg-brick text-ink' }}">
                    <div>
                        <div class="mb-5 text-[13px] font-bold tracking-[.14em] uppercase {{ $dark ? 'text-brick' : 'text-ink' }}">
                            {{ $situation['eyebrow'] }}
                        </div>
                        <div class="text-card font-extrabold tracking-[-.02em]">{{ $situation['title'] }}</div>
                    </div>

                    <div>
                        <p class="mt-6 mb-5 text-[15px] leading-[1.5] {{ $dark ? 'text-cream/72' : 'text-ink/72' }}">
                            {{ $situation['text'] }}
                        </p>
                        <span class="text-[15px] font-bold">{{ $situation['cta_label'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</section>
