@props([
    'checklist',
    'progress',
])

{{-- Tmavá hlavička s celkovým progresem. Stejná na rozcestníku i v kategorii. --}}
<section class="section-x pt-6 pb-2">
    <div class="container-tavo">
        <div class="rounded-card bg-ink px-[7vw] py-11 text-cream menu:px-14 menu:py-14" data-progres-celkem>
            <div class="flex flex-col gap-8 menu:flex-row menu:items-end menu:justify-between">
                <div class="max-w-[46ch]">
                    @if ($checklist->client)
                        <p class="mb-4 text-sm font-bold tracking-[.14em] text-brick uppercase">
                            {{ $checklist->client->name }}
                        </p>
                    @endif

                    <h1 data-line class="text-h2-sm font-extrabold tracking-[-.02em]">
                        {{ $checklist->name }}
                    </h1>

                    @if ($checklist->intro)
                        <p class="mt-5 text-perex text-cream/70">{{ $checklist->intro }}</p>
                    @endif
                </div>

                <div class="shrink-0">
                    <p class="text-metric-lg font-extrabold tracking-[-.03em] text-brick">
                        <span data-progres-procenta>{{ $progress['percent'] }}</span> %
                    </p>
                    <p class="mt-2 text-sm font-semibold text-cream/60">
                        hotovo <span data-progres-hotovo>{{ $progress['done'] }}</span> z {{ $progress['total'] }}
                    </p>
                </div>
            </div>

            <div class="mt-9 h-1.5 w-full overflow-hidden rounded-pill bg-cream/15">
                <div class="h-full rounded-pill bg-brick transition-[width] duration-500 ease-tavo"
                     data-progres-vypln
                     style="width: {{ $progress['percent'] }}%"></div>
            </div>
        </div>
    </div>
</section>
