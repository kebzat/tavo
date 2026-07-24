@props(['home'])
<h1>Toto je test</h1>
<header id="top" class="section-x relative flex min-h-screen flex-col justify-center overflow-hidden pt-[150px] pb-[60px] text-center">
    <div class="container-tavo relative z-2 flex flex-col items-center">
        <x-eyebrow data-reveal :rule="true" class="mb-[30px]">{{ $home->hero_eyebrow }}</x-eyebrow>

        <h1 class="text-hero m-0 max-w-[16ch] font-extrabold tracking-[-.03em]">
            <span data-line><span>{{ $home->hero_line_1 }}</span></span>
            <span data-line><span>{{ $home->hero_line_2 }}</span></span>
            <span data-line><span>{{ $home->hero_line_3 }}
                <span class="text-brick italic">{{ $home->hero_line_3_accent }}</span>
            </span></span>
        </h1>

        <div class="mt-11 flex flex-col items-center gap-9">
            <p data-reveal class="text-perex m-0 max-w-[52ch] text-body">{{ $home->hero_perex }}</p>

            <div data-reveal class="flex flex-wrap justify-center gap-3.5">
                <x-btn :href="$home->hero_cta_primary_url" variant="primary">{{ $home->hero_cta_primary_label }}</x-btn>
                <x-btn :href="$home->hero_cta_secondary_url" variant="ghost">{{ $home->hero_cta_secondary_label }}</x-btn>
            </div>
        </div>
    </div>
</header>
