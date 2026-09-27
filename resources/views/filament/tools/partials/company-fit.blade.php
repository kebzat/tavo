{{--
    Posouzení firmy na kartě v CRM: verdikt, skóre, proč, a co jsme na webu
    našli. Data připravuje App\Support\Crm\Scout\ProspectScout.
--}}
@php($company = $getRecord())

@if (! $company?->scouted_at)
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Firma ještě není proklepnutá. Použij tlačítko <strong>Audit → Proklepnout web</strong> nahoře.
    </p>
@else
    @php($scout = $company->scout_data ?? [])
    @php($m = $scout['measurements'] ?? [])
    @php($ai = $scout['ai'] ?? null)

    <div class="space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $company->fit_score }}</span>
            <x-filament::badge :color="$company->fit_verdict?->getColor()">{{ $company->fit_verdict?->getLabel() }}</x-filament::badge>
            <span class="text-xs text-gray-500 dark:text-gray-400">proklepnuto {{ $company->scouted_at->format('j. n. Y H:i') }}</span>
        </div>

        @if ($ai)
            <div class="space-y-1 text-sm text-gray-700 dark:text-gray-300">
                <p><span class="font-semibold">Co dělají:</span> {{ $ai['summary'] }}</p>
                <p><span class="font-semibold">Úsudek:</span> {{ $ai['note'] }}</p>
            </div>
        @endif

        @if (! ($m['reachable'] ?? false))
            <p class="text-sm font-semibold text-danger-600 dark:text-danger-400">{{ $m['error'] ?? 'Web nejde načíst.' }}</p>
        @else
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Platforma</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $m['platform'] ?? 'nepoznali jsme' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">PageSpeed mobil</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ isset($m['pagespeed']['score']) ? $m['pagespeed']['score'].' / 100' : 'neměřeno' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Adres v sitemapě</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $m['sitemap_urls'] ?? 'bez sitemapy' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 dark:text-gray-400">Reklama a měření</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">
                        {{ implode(', ', $company->trackingLabels()) ?: 'nic' }}
                    </dd>
                </div>
            </dl>
        @endif

        <details class="text-sm">
            <summary class="cursor-pointer font-semibold text-gray-950 dark:text-white">Proč {{ $company->fit_score }} bodů</summary>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-gray-700 dark:text-gray-300">
                @foreach ($scout['reasons'] ?? [] as $reason)
                    <li>{{ $reason }}</li>
                @endforeach
                @if ($ai)
                    <li>{{ $ai['adjustment'] > 0 ? '+' : '' }}{{ $ai['adjustment'] }} úsudek Clauda</li>
                @endif
            </ul>
        </details>

        @if ($scout['findings'] ?? [])
            <div>
                <p class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Nálezy ({{ count($scout['findings']) }})</p>
                <ul class="space-y-1.5">
                    @foreach ($scout['findings'] as $finding)
                        <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <x-filament::badge size="sm" :color="match ($finding['severity']) { 'critical' => 'danger', 'high' => 'warning', default => 'gray' }">
                                {{ \App\Support\Crm\Scout\Findings::severityTag($finding['severity']) }}
                            </x-filament::badge>
                            <span>{{ $finding['title'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
