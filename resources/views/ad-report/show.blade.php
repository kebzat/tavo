{{--
    Report reklam pro klienta. Tmavá hlavička jako u auditu, pod ní hlavní
    čísla, komentář Pavla, graf po dnech, cesta k nákupu a kampaně.
    Čísla přicházejí naformátovaná z App\Support\Ads\PerformanceView.
--}}
<x-layout.document :title="$report->title" :eyebrow="$report->client->name" :note="text('ads_report.footer', 'Čísla jsou zmrazená ke dni, kdy jsme report připravili. Když se v reklamním systému později dopočítají konverze, tady se nezmění.')">

    @if ($isDraft)
        <div class="section-x pt-4 print:hidden">
            <p class="container-tavo rounded-card bg-brick/10 px-5 py-3 text-sm font-semibold text-brick">
                Koncept. Klient ho zatím nevidí, odkaz začne fungovat po odeslání.
            </p>
        </div>
    @endif

    <section class="section-x pt-6 pb-2">
        <div class="container-tavo">
            <div class="rounded-card bg-ink px-[7vw] py-11 text-cream menu:px-14 menu:py-14 print:bg-white print:px-0 print:py-4 print:text-ink">
                <div class="flex flex-col gap-8 menu:flex-row menu:items-end menu:justify-between">
                    <div class="max-w-[60ch]">
                        <p class="mb-4 text-sm font-bold tracking-[.14em] text-brick uppercase">{{ $report->client->name }}</p>

                        <h1 class="text-h2-sm font-extrabold tracking-[-.02em]">{{ $report->type->title() }}</h1>

                        @if ($view->accounts())
                            <p class="mt-5 text-perex text-cream/70 print:text-muted">
                                {{ text('ads_report.source', 'Čísla z reklamních účtů') }}: {{ implode(', ', $view->accounts()) }}
                            </p>
                        @endif
                    </div>

                    <div class="shrink-0">
                        <p class="text-sm font-semibold text-cream/60 print:text-muted">{{ text('ads_report.period', 'období') }}</p>
                        <p class="mt-1 text-metric-sm font-extrabold tracking-[-.02em] text-brick tabular-nums">{{ $view->period->label() }}</p>
                        <p class="mt-1 text-sm text-cream/60 print:text-muted">{{ text('ads_report.compared', 'srovnání s') }} {{ $view->previousPeriod->label() }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($view->hasData())
        <section class="section-x pt-5">
            <div class="container-tavo grid grid-cols-2 gap-3 menu:grid-cols-4 menu:gap-5">
                @foreach ($tiles as $tile)
                    <div class="rounded-card border border-ink/14 p-5 menu:p-6 print:break-inside-avoid">
                        <p class="text-sm font-semibold text-muted">{{ $tile['label'] }}</p>
                        <p class="mt-2 text-metric-sm font-extrabold tracking-[-.03em] text-ink tabular-nums">{{ $tile['value'] }}</p>
                        <p class="mt-1 text-sm tabular-nums">
                            @if ($tile['change'])
                                <span @class([
                                    'font-bold',
                                    'text-moss' => $tile['tone'] === 'good',
                                    'text-brick' => $tile['tone'] === 'bad',
                                    'text-muted' => $tile['tone'] === 'neutral',
                                ])>{{ $tile['change'] }}</span>
                            @endif
                            <span class="text-muted">{{ text('ads_report.before', 'předtím') }} {{ $tile['previous'] }}</span>
                        </p>
                        @if ($tile['hint'])
                            <p class="mt-1 text-xs text-muted">{{ $tile['hint'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($summary)
        <section class="section-x pt-10">
            <div class="container-tavo grid gap-6 loop:grid-cols-[230px_minmax(0,1fr)] loop:gap-16">
                <h2 class="text-sm font-bold tracking-[.14em] text-brick uppercase">{{ text('ads_report.comment', 'Náš komentář') }}</h2>
                <div class="prose-tavo max-w-[70ch] [&_ol]:list-decimal [&_ul]:list-disc">{{ $summary }}</div>
            </div>
        </section>
    @endif

    @if ($view->hasData())
        @if ($view->shows('chart') || ($funnel && $view->shows('funnel')))
        <section class="section-x pt-10">
            <div class="container-tavo grid gap-5 loop:grid-cols-3">
                @if ($view->shows('chart'))
                <div @class(['rounded-card border border-ink/14 p-5 menu:p-7 print:break-inside-avoid', 'loop:col-span-2' => $funnel && $view->shows('funnel'), 'loop:col-span-3' => ! ($funnel && $view->shows('funnel'))])>
                    <h2 class="mb-4 text-lg font-extrabold tracking-[-.01em]">{{ text('ads_report.daily', 'Po dnech') }}</h2>
                    <x-ads.chart :labels="$chart['labels']" :bars="$chart['bars']" :line="$chart['line']" line-class="text-ink" class="text-body" />
                </div>
                @endif

                @if ($funnel && $view->shows('funnel'))
                    <div @class(['rounded-card border border-ink/14 p-5 menu:p-7 print:break-inside-avoid', 'loop:col-span-3' => ! $view->shows('chart')])>
                        <h2 class="mb-4 text-lg font-extrabold tracking-[-.01em]">{{ text('ads_report.funnel', 'Od zobrazení ke konverzi') }}</h2>
                        <ol class="flex flex-col gap-4">
                            @foreach ($funnel as $step)
                                <li>
                                    <div class="flex items-baseline justify-between gap-2 text-sm">
                                        <span class="text-body">{{ $step['label'] }}</span>
                                        <span class="font-bold tabular-nums">{{ $step['value'] }}</span>
                                    </div>
                                    {{-- Šířka pruhu je poměr z dat, proto inline styl. --}}
                                    <div class="mt-1.5 h-2 rounded-pill bg-sand-100">
                                        <div class="h-2 rounded-pill bg-brick" style="width: {{ $step['width'] }}%"></div>
                                    </div>
                                    @if ($step['rate'])
                                        <p class="mt-1 text-xs text-muted">{{ $step['rate'] }} {{ text('ads_report.from_previous', 'z předchozího kroku') }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        </section>
        @endif

        @if ($byAccount && $view->shows('accounts'))
            <section class="section-x pt-10">
                <div class="container-tavo rounded-card border border-ink/14 p-5 menu:p-7 print:break-inside-avoid">
                    <h2 class="mb-4 text-lg font-extrabold tracking-[-.01em]">{{ text('ads_report.by_account', 'Podle reklamních systémů') }}</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max text-left text-sm">
                            <thead>
                                <tr class="border-b border-ink/14 text-muted">
                                    <th class="py-2 pr-4 font-semibold">{{ text('ads_report.account', 'Účet') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ text('ads_report.spend', 'Útrata') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ text('ads_report.share', 'Podíl') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->conversionLabel() }}</th>
                                    <th class="py-2 pl-3 text-right font-semibold">{{ $view->goal->costLabel() }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink/8">
                                @foreach ($byAccount as $row)
                                    <tr>
                                        <td class="py-2.5 pr-4 font-semibold">{{ $row['name'] }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['spend'] }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['share_label'] }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $row['conversions'] }}</td>
                                        <td class="py-2.5 pl-3 text-right tabular-nums">{{ $row['cost'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif

        @if ($analytics && $view->shows('analytics'))
            <section class="section-x pt-10">
                <div class="container-tavo rounded-card bg-sand-100 p-5 menu:p-7 print:break-inside-avoid">
                    <h2 class="text-lg font-extrabold tracking-[-.01em]">{{ text('ads_report.analytics', 'Co naměřil web') }}</h2>
                    <p class="mt-1 max-w-[70ch] text-sm text-muted">{{ text('ads_report.analytics_note', 'Google Analytics, všechny zdroje návštěv dohromady. Reklamní systémy si nákupy připisují samy, analytika počítá každý nákup jednou.') }}</p>

                    <div class="mt-5 grid grid-cols-2 gap-4 menu:grid-cols-4">
                        @foreach ($analytics['tiles'] as $tile)
                            <div>
                                <p class="text-sm font-semibold text-muted">{{ $tile['label'] }}</p>
                                <p class="mt-1 text-xl font-extrabold tabular-nums">{{ $tile['value'] }}</p>
                                @if ($tile['change'])
                                    <p @class(['text-sm font-bold tabular-nums', 'text-moss' => $tile['tone'] === 'good', 'text-brick' => $tile['tone'] === 'bad', 'text-muted' => $tile['tone'] === 'neutral'])>{{ $tile['change'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($analytics['comparison'])
                        <p class="mt-5 max-w-[80ch] text-sm leading-relaxed text-body">{{ $analytics['comparison'] }}</p>
                    @endif

                    @if ($analytics['channels'])
                        <div class="mt-5 overflow-x-auto">
                            <table class="w-full min-w-max text-left text-sm">
                                <thead>
                                    <tr class="border-b border-ink/14 text-muted">
                                        <th class="py-2 pr-4 font-semibold">{{ text('ads_report.channel', 'Odkud lidé přišli') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold">{{ text('ads_report.sessions', 'Návštěvy') }}</th>
                                        <th class="px-3 py-2 text-right font-semibold">{{ text('ads_report.share', 'Podíl') }}</th>
                                        <th class="py-2 pl-3 text-right font-semibold">{{ $view->goal->value === 'purchases' ? text('ads_report.ga4_purchases', 'Nákupy') : text('ads_report.ga4_events', 'Konverze') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-ink/8">
                                    @foreach ($analytics['channels'] as $channel)
                                        <tr>
                                            <td class="py-2.5 pr-4">{{ $channel['channel'] }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $channel['sessions'] }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $channel['share'] }}</td>
                                            <td class="py-2.5 pl-3 text-right tabular-nums">{{ $channel['purchases'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if ($campaigns && $view->shows('campaigns'))
            <section class="section-x pt-10">
                <div class="container-tavo rounded-card border border-ink/14 p-5 menu:p-7">
                    <h2 class="mb-4 text-lg font-extrabold tracking-[-.01em]">{{ text('ads_report.campaigns', 'Kampaně') }}</h2>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-max text-left text-sm">
                            <thead>
                                <tr class="border-b border-ink/14 text-muted">
                                    <th class="py-2 pr-4 font-semibold">{{ text('ads_report.campaign', 'Kampaň') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ text('ads_report.spend', 'Útrata') }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->conversionLabel() }}</th>
                                    <th class="px-3 py-2 text-right font-semibold">{{ $view->goal->costLabel() }}</th>
                                    @if ($view->goal->hasValue())
                                        <th class="px-3 py-2 text-right font-semibold">ROAS</th>
                                    @endif
                                    <th class="py-2 pl-3 text-right font-semibold">CTR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink/8">
                                @foreach ($campaigns as $campaign)
                                    <tr>
                                        <td class="max-w-[40ch] truncate py-2.5 pr-4 font-semibold">{{ $campaign['name'] }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $campaign['spend'] }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $campaign['conversions'] }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums">{{ $campaign['cost'] }}</td>
                                        @if ($view->goal->hasValue())
                                            <td class="px-3 py-2.5 text-right tabular-nums">{{ $campaign['roas'] }}</td>
                                        @endif
                                        <td class="py-2.5 pl-3 text-right tabular-nums">{{ $campaign['ctr'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
    @else
        <section class="section-x pt-10">
            <p class="container-tavo text-perex text-body">{{ text('ads_report.empty', 'Za tohle období reklamy neběžely.') }}</p>
        </section>
    @endif

    <section class="section-x section-y-sm">
        <div class="container-tavo max-w-[80ch] text-sm leading-relaxed text-muted">
            <p>
                {{ text('ads_report.attribution', 'Konverze a jejich hodnotu přiřazuje reklamní systém podle svého nastavení atribuce. Nejsou to tržby z účetnictví ani zisk a jedna objednávka se může započítat víc kanálům. Slouží ke srovnání kampaní a období mezi sebou.') }}
            </p>
            <p class="mt-3">
                {{ text('ads_report.questions', 'Když vám něco v číslech nesedí, napište nám.') }}
                <a href="{{ $contact->emailHref() }}" class="font-semibold text-ink underline decoration-brick underline-offset-4">{{ $contact->email }}</a>
            </p>
        </div>
    </section>
</x-layout.document>
