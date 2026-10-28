{{-- Karty dárkových balíčků: obrázek poskládané krabice a obsah, ať balírna nemusí do detailu objednávky. Viz App\Support\Automations\GiftBoxes. --}}
<div class="grid gap-4">
    @foreach ($rows as $row)
        @foreach ($row['boxes'] as $index => $box)
            <article class="flex flex-col gap-4 rounded-xl border border-gray-200 p-4 sm:flex-row dark:border-white/10">
                @if ($box['preview'])
                    <a href="{{ $box['preview'] }}" target="_blank" rel="noopener" class="block shrink-0 sm:w-64 lg:w-72" title="Otevřít větší obrázek">
                        {{-- Obrázek kreslí server náhledů krabic; když ho ještě neumí, zůstane jen odkaz pod obsahem. --}}
                        <img src="{{ $box['preview'] }}.jpg" alt="Jak poskládat krabici z objednávky {{ $row['order'] }}" loading="lazy"
                            class="aspect-square w-full rounded-lg bg-gray-100 object-cover dark:bg-white/5"
                            onerror="this.parentElement.remove()">
                    </a>
                @endif

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="text-lg font-semibold tabular-nums text-gray-950 dark:text-white">{{ $row['order'] }}</span>
                        @if ($row['created_at'])
                            <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($row['created_at'])->timezone('Europe/Prague')->format('j. n. G:i') }}</span>
                        @endif
                        <x-filament::badge :color="$row['to_pack'] ? 'warning' : 'success'">{{ $row['status'] }}</x-filament::badge>
                    </div>
                    <p class="mt-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ $box['title'] }}@if (count($row['boxes']) > 1) · krabice {{ $index + 1 }} z {{ count($row['boxes']) }}@endif · {{ $box['count'] }} ks
                    </p>

                    <ul class="mt-3 space-y-1.5 text-base text-gray-950 dark:text-white">
                        @foreach ($box['items'] as $item)
                            <li class="flex gap-2">
                                <span @class(['w-9 shrink-0 text-right font-semibold tabular-nums', 'text-primary-600 dark:text-primary-400' => $item['qty'] > 1])>{{ $item['qty'] }}×</span>
                                <span>{{ $item['name'] }}</span>
                            </li>
                        @endforeach
                    </ul>

                    @if ($box['preview'])
                        <a href="{{ $box['preview'] }}" target="_blank" rel="noopener" class="mt-3 inline-flex text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">Jak krabici poskládat (větší obrázek)</a>
                    @endif
                </div>
            </article>
        @endforeach
    @endforeach
</div>
