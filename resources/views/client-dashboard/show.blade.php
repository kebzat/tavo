{{--
    Přehled spolupráce pro klienta na paušál. Tmavá hlavička s měsícem
    a odpracovanými hodinami, pod ní paušál po oblastech, hotová práce,
    co čeká na klienta, plán, hodiny po měsících a sdílené dokumenty.
    Data skládá App\Support\ClientDashboard.
--}}
<x-layout.document :title="text('client_dashboard.title', 'Přehled spolupráce').' · '.$client->name" :eyebrow="$client->name">

    @if ($isDraft)
        <div class="section-x pt-4 print:hidden">
            <p class="container-tavo rounded-card bg-brick/10 px-5 py-3 text-sm font-semibold text-brick">
                Náhled. Klient přehled zatím nevidí, odkaz začne fungovat po zapnutí u klienta v nástrojích.
            </p>
        </div>
    @endif

    <section class="section-x pt-6 pb-2">
        <div class="container-tavo">
            <div class="rounded-card bg-ink px-[7vw] py-11 text-cream menu:px-14 menu:py-14 print:bg-white print:px-0 print:py-4 print:text-ink">
                <div class="flex flex-col gap-10 menu:flex-row menu:items-end menu:justify-between">
                    <div class="max-w-[60ch]">
                        <p class="mb-4 text-sm font-bold tracking-[.14em] text-brick uppercase">{{ $client->name }}</p>
                        <h1 class="text-h2-sm font-extrabold tracking-[-.02em]">{{ text('client_dashboard.heading', 'Přehled spolupráce') }}</h1>

                        <p class="mt-4 text-perex font-bold text-cream/80 print:text-ink">{{ $dashboard->label() }}</p>

                        @if ($dashboard->goal())
                            <p class="mt-6 text-perex text-cream/80 print:text-body">
                                <span class="font-bold text-cream print:text-ink">{{ text('client_dashboard.goal', 'Cíl měsíce') }}:</span>
                                {{ $dashboard->goal() }}
                            </p>
                        @endif
                    </div>

                    <div class="shrink-0 menu:text-right">
                        <p class="text-sm font-semibold text-cream/60 print:text-muted">{{ text('client_dashboard.hours_label', 'odpracováno') }}</p>
                        <p class="mt-1 text-metric-lg font-extrabold tracking-[-.03em] text-brick tabular-nums">{{ $dashboard->totalHours() }}</p>
                        @if ($dashboard->totalFee())
                            <p class="mt-2 text-sm text-cream/60 print:text-muted">{{ text('client_dashboard.fee_label', 'paušál') }} {{ $dashboard->totalFee() }} {{ text('client_dashboard.per_month', 'měsíčně') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if (count($months) > 1)
        <nav class="section-x pt-6 print:hidden" aria-label="{{ text('client_dashboard.months', 'Měsíce') }}">
            <div class="container-tavo flex flex-col gap-3 menu:flex-row menu:items-center menu:gap-5">
                <p class="text-sm font-bold text-muted">{{ text('client_dashboard.pick_month', 'Vyberte měsíc') }}</p>
                {{-- Víc měsíců se na mobilu posouvá do strany, stránka sama nepřeteče. --}}
                <ul class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    @foreach ($months as $month)
                        <li class="shrink-0">
                            <a href="{{ $month['url'] }}"
                               @if ($month['active']) aria-current="page" @endif
                               @class([
                                   'block rounded-pill border px-5 py-2.5 text-sm font-bold transition duration-300 ease-tavo',
                                   'border-ink bg-ink text-cream' => $month['active'],
                                   'border-ink/20 text-ink hover:-translate-y-0.5 hover:border-ink' => ! $month['active'],
                               ])>{{ $month['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </nav>
    @endif

    @if ($dashboard->showsRetainers())
        <section class="section-x pt-5">
            <div class="container-tavo grid gap-3 menu:grid-cols-2 menu:gap-5">
                @foreach ($retainers as $retainer)
                    <div class="rounded-card border border-ink/14 p-5 menu:p-7 print:break-inside-avoid">
                        <div class="flex items-baseline justify-between gap-4">
                            <p class="text-lg font-extrabold tracking-[-.01em]">{{ $retainer['label'] }}</p>
                            <p class="text-sm font-semibold text-muted tabular-nums">{{ $retainer['fee'] }} {{ text('client_dashboard.per_month', 'měsíčně') }}</p>
                        </div>
                        <p class="mt-3 text-metric-sm font-extrabold tracking-[-.03em] tabular-nums">
                            {{ $retainer['hours'] }}
                            @if ($retainer['included'])
                                <span class="text-base font-semibold tracking-normal text-muted">{{ text('client_dashboard.of', 'z') }} {{ $retainer['included'] }}</span>
                            @endif
                        </p>
                        @if ($retainer['percent'] !== null)
                            {{-- Šířka pruhu je poměr z dat, proto inline styl. --}}
                            <div class="mt-3 h-2 rounded-pill bg-sand-100">
                                <div class="h-2 rounded-pill {{ $retainer['bar'] }}" style="width: {{ $retainer['percent'] }}%"></div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="section-x pt-12">
        <div class="container-tavo grid gap-6 loop:grid-cols-[230px_minmax(0,1fr)] loop:gap-16">
            <h2 class="text-sm font-bold tracking-[.14em] text-brick uppercase">{{ text('client_dashboard.work', 'Co jsme udělali') }}</h2>
            @if ($work)
                <x-client-dashboard.task-list :rows="$work" />
            @else
                <p class="text-perex text-body">{{ text('client_dashboard.work_empty', 'V tomhle měsíci zatím nemáme zapsanou žádnou práci.') }}</p>
            @endif
        </div>
    </section>

    @if ($summary)
        <section class="section-x pt-12">
            <div class="container-tavo grid gap-6 loop:grid-cols-[230px_minmax(0,1fr)] loop:gap-16">
                <h2 class="text-sm font-bold tracking-[.14em] text-brick uppercase">{{ text('client_dashboard.summary', 'Co jsme zjistili') }}</h2>
                <div class="prose-tavo max-w-[70ch] [&_ol]:list-decimal [&_ul]:list-disc">{{ $summary }}</div>
            </div>
        </section>
    @endif

    @if ($waiting)
        <section class="section-x pt-12">
            <div class="container-tavo grid gap-6 loop:grid-cols-[230px_minmax(0,1fr)] loop:gap-16">
                <div>
                    <h2 class="text-sm font-bold tracking-[.14em] text-brick uppercase">{{ text('client_dashboard.waiting', 'Čeká na vás') }}</h2>
                    <p class="mt-2 text-sm text-muted">{{ text('client_dashboard.waiting_note', 'Bez vás se tu nepohneme: podklady, přístupy nebo schválení.') }}</p>
                </div>
                <x-client-dashboard.task-list :rows="$waiting" />
            </div>
        </section>
    @endif

    @if ($plan)
        <section class="section-x pt-12">
            <div class="container-tavo grid gap-6 loop:grid-cols-[230px_minmax(0,1fr)] loop:gap-16">
                <h2 class="text-sm font-bold tracking-[.14em] text-brick uppercase">{{ text('client_dashboard.plan', 'Co následuje') }}</h2>
                <div class="flex flex-col gap-8">
                    @foreach ($plan as $group)
                        <div>
                            <h3 class="mb-1 text-sm font-bold text-muted">{{ $group['label'] }}</h3>
                            <x-client-dashboard.task-list :rows="$group['tasks']" />
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (count($history) > 1)
        <section class="section-x pt-12">
            <div class="container-tavo grid gap-6 loop:grid-cols-[230px_minmax(0,1fr)] loop:gap-16">
                <div>
                    <h2 class="text-sm font-bold tracking-[.14em] text-brick uppercase">{{ text('client_dashboard.history', 'Hodiny po měsících') }}</h2>
                    @if (count($legend) > 1)
                        <ul class="mt-3 flex flex-col gap-1.5 text-sm text-body">
                            @foreach ($legend as $item)
                                <li class="flex items-center gap-2"><span class="size-3 rounded-[3px] {{ $item['bar'] }}"></span>{{ $item['label'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="rounded-card border border-ink/14 p-5 menu:p-7 print:break-inside-avoid">
                    <ol class="flex h-48 items-stretch gap-2 menu:gap-4">
                        @foreach ($history as $month)
                            <li class="flex min-w-0 flex-1 flex-col items-center gap-2" title="{{ $month['title'] }}: {{ $month['total'] }}">
                                <span @class(['text-xs tabular-nums', 'font-bold text-ink' => $month['current'], 'text-muted' => ! $month['current']])>{{ $month['total'] }}</span>
                                {{-- Výška sloupce je poměr z dat, proto inline styl. --}}
                                <div class="flex w-full max-w-12 flex-1 flex-col-reverse overflow-hidden rounded-t-[6px] bg-sand-100/60">
                                    @foreach ($month['segments'] as $segment)
                                        <div class="{{ $segment['bar'] }}" style="height: {{ $segment['height'] }}%" title="{{ $segment['label'] }}"></div>
                                    @endforeach
                                </div>
                                <span @class(['text-xs', 'font-bold text-ink' => $month['current'], 'text-muted' => ! $month['current']])>{{ $month['label'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>
    @endif

    @if ($documents)
        <section class="section-x pt-12">
            <div class="container-tavo grid gap-6 loop:grid-cols-[230px_minmax(0,1fr)] loop:gap-16">
                <h2 class="text-sm font-bold tracking-[.14em] text-brick uppercase">{{ text('client_dashboard.documents', 'Dokumenty') }}</h2>
                <ul class="grid gap-3 menu:grid-cols-2">
                    @foreach ($documents as $document)
                        <li>
                            <a href="{{ $document['url'] }}" class="group flex h-full flex-col justify-between gap-3 rounded-card border border-ink/14 p-5 transition duration-300 ease-tavo hover:-translate-y-0.5 hover:border-ink">
                                <span class="font-extrabold text-ink">{{ $document['label'] }}</span>
                                <span class="flex items-center justify-between gap-3 text-sm text-muted">
                                    {{ $document['meta'] }}
                                    <span aria-hidden="true" class="text-ink transition-transform duration-300 ease-tavo group-hover:translate-x-0.5">→</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="section-x section-y-sm">
        <div class="container-tavo max-w-[80ch] text-sm leading-relaxed text-muted">
            <p>{{ text('client_dashboard.hours_note', 'Hodiny jsou čas, který jsme na vašem projektu skutečně odpracovali, včetně konzultací a komunikace. Práci, kterou vám neúčtujeme, sem nepočítáme.') }}</p>
            <p class="mt-3">
                {{ text('client_dashboard.questions', 'Když vám něco nesedí nebo chcete změnit priority, napište nám.') }}
                <a href="{{ $contact->emailHref() }}" class="font-semibold text-ink underline decoration-brick underline-offset-4">{{ $contact->email }}</a>
            </p>
        </div>
    </section>
</x-layout.document>
