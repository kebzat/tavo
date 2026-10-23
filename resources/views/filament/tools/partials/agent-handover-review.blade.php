{{-- Kontrola hotové práce před zveřejněním: popis, snímky, co se změnilo. --}}
<div class="space-y-6">
    @if ($item['sensitive'])
        <div class="rounded-lg bg-warning-50 p-4 text-sm text-warning-800 ring-1 ring-warning-600/20 dark:bg-warning-500/10 dark:text-warning-300">
            <p class="font-semibold">Úprava mění i nastavení serveru</p>
            <p class="mt-1">Nejde jen o vzhled webu: mění se, jak se web nasazuje nebo jak běží agent. Když si nejste jistí, nechte to na Tomovi.</p>
            <p class="mt-2 font-mono text-xs">{{ implode(', ', $item['sensitive']) }}</p>
        </div>
    @endif

    @if ($body)
        <div class="ads-advice text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $body }}</div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">Agent k úpravě nenapsal popis. Požádejte ho v terminálu: „Připrav předání ke kontrole.“</p>
    @endif

    @if ($item['images'])
        <div>
            <p class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">Snímky z prohlížeče</p>
            <div class="grid items-start gap-4 sm:grid-cols-2">
                @foreach ($item['images'] as $file)
                    <figure class="overflow-hidden rounded-lg bg-gray-50 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                        <a href="{{ $component->imageUrl($item['name'], $file) }}" target="_blank" rel="noopener" title="Otevřít ve velikosti 1:1">
                            <img src="{{ $component->imageUrl($item['name'], $file) }}" alt="{{ $file }}" loading="lazy" class="max-h-96 w-full object-cover object-top">
                        </a>
                        <figcaption class="border-t border-gray-950/5 px-3 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">{{ pathinfo($file, PATHINFO_FILENAME) }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    @endif

    @if ($item['commits'])
        <div>
            <p class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Co se změnilo</p>
            <ul class="list-disc space-y-1 ps-5 text-sm text-gray-700 dark:text-gray-300">
                @foreach ($item['commits'] as $commit)
                    <li>{{ $commit }}</li>
                @endforeach
            </ul>
            @if ($item['files'])
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $component->plural($item['files'], 'změněný soubor', 'změněné soubory', 'změněných souborů') }} · větev {{ $item['branch'] }}</p>
            @endif
        </div>
    @endif
</div>
