{{--
    Sdílený dotazník. Bez JS se odpovědi odešlou tlačítkem celé najednou,
    s JS (tavoQuestionnaire v app.js) se ukládají samy chvíli po psaní.
--}}
<x-layout.document :title="$questionnaire->title" :eyebrow="$questionnaire->client?->name ?? $questionnaire->respondent">

    <div x-data="tavoQuestionnaire(@js([
            'zodpovezeno' => $progress['answered'],
            'procenta' => $progress['percent'],
            'odeslano' => $questionnaire->submitted_at !== null,
         ]))">

        <section class="section-x pt-6 pb-2">
            <div class="container-tavo">
                <div class="rounded-card bg-ink px-[7vw] py-11 text-cream menu:px-14 menu:py-14">
                    <div class="flex flex-col gap-8 menu:flex-row menu:items-end menu:justify-between">
                        <div class="max-w-[52ch]">
                            <p class="mb-4 text-sm font-bold tracking-[.14em] text-brick uppercase">
                                {{ text('dotaznik.eyebrow', 'Dotazník', 'Dotazník', 'Štítek nad nadpisem') }}
                            </p>

                            <h1 data-line class="text-h2-sm font-extrabold tracking-[-.02em]">
                                {{ $questionnaire->title }}
                            </h1>

                            @if ($questionnaire->intro)
                                <p class="mt-5 text-perex whitespace-pre-line text-cream/70">{{ $questionnaire->intro }}</p>
                            @endif
                        </div>

                        <div class="shrink-0">
                            <p class="text-metric-lg font-extrabold tracking-[-.03em] text-brick">
                                <span x-text="zodpovezeno">{{ $progress['answered'] }}</span><span class="text-cream/40">/{{ $progress['total'] }}</span>
                            </p>
                            <p class="mt-2 text-sm font-semibold text-cream/60">
                                {{ text('dotaznik.progress', 'zodpovězených otázek', 'Dotazník', 'Popisek pod počtem odpovědí') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-9 h-1.5 w-full overflow-hidden rounded-pill bg-cream/15">
                        <div class="h-full rounded-pill bg-brick transition-[width] duration-500 ease-tavo"
                             style="width: {{ $progress['percent'] }}%"
                             :style="{ width: procenta + '%' }"></div>
                    </div>

                    <p role="status"
                       x-show="odeslano"
                       @if (! $questionnaire->submitted_at) x-cloak @endif
                       x-ref="potvrzeni"
                       tabindex="-1"
                       class="mt-8 rounded-2xl bg-cream/10 px-5 py-4 text-[15px] leading-[1.55] text-cream outline-none">
                        {{ text('dotaznik.submitted', 'Odpovědi máme, díky. Upravovat je jde dál, změny se uloží samy.', 'Dotazník', 'Hláška po odeslání') }}
                    </p>
                </div>
            </div>
        </section>

        <form method="POST"
              action="{{ route('questionnaire.save', $questionnaire->slug) }}"
              x-ref="formular"
              @input="zmena"
              @submit.prevent="ulozit(true)"
              class="section-x pt-12 pb-16 menu:pt-16 menu:pb-24">
            {{-- Bez JS je tlačítko jediná cesta, jak uložit, a rovnou odesílá. S JS se
                 pole vypne a ukládání na pozadí ho přidá jen po kliknutí na tlačítko. --}}
            <input type="hidden" name="odeslat" value="1" x-bind:disabled="true">

            <div class="container-tavo flex flex-col gap-16 menu:gap-20">
                @foreach ($sections as $section)
                    <div class="grid gap-6 menu:grid-cols-[minmax(0,1fr)_minmax(0,2.2fr)] menu:gap-12">
                        <div class="menu:sticky menu:top-8 menu:self-start">
                            <p class="text-sm font-bold tracking-[.14em] text-muted uppercase tabular-nums">
                                {{ sprintf('%02d', $loop->iteration) }}
                            </p>

                            @if ($section['title'])
                                <h2 class="mt-2 text-h3-sm font-extrabold tracking-[-.02em] text-ink">{{ $section['title'] }}</h2>
                            @endif

                            @if ($section['intro'])
                                <p class="mt-3 text-[15px] leading-[1.55] text-muted">{{ $section['intro'] }}</p>
                            @endif
                        </div>

                        <div class="flex flex-col gap-4">
                            @foreach ($section['questions'] as $question)
                                <x-questionnaire.question :question="$question" />
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Lišta se stavem ukládání. Drží se spodku okna, ať je vidět, že se nic neztratí. --}}
            <div class="sticky bottom-4 z-10 mt-16 container-tavo">
                <div class="flex flex-col gap-3 rounded-card bg-ink px-6 py-4 text-cream shadow-[0_18px_40px_-20px_rgb(19_17_16/.6)] sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-semibold text-cream/70" aria-live="polite">
                        <span x-show="stav === 'idle'">{{ text('dotaznik.note', 'Odpovědi se ukládají průběžně. K dotazníku se dá kdykoli vrátit.', 'Dotazník', 'Text v liště dole, než se začne psát') }}</span>
                        <span x-show="stav === 'dirty' || stav === 'saving'" x-cloak>{{ text('dotaznik.saving', 'Ukládám…', 'Dotazník', 'Stav ukládání') }}</span>
                        <span x-show="stav === 'saved'" x-cloak>{{ text('dotaznik.saved', 'Uloženo v', 'Dotazník', 'Stav ukládání, za ním následuje čas') }} <span x-text="ulozeno"></span></span>
                        <span x-show="stav === 'error'" x-cloak class="font-bold text-cream">{{ text('dotaznik.error', 'Uložení se nepovedlo. Zkontrolujte připojení a zkuste to znovu.', 'Dotazník', 'Chyba při ukládání') }}</span>
                    </p>

                    <button type="submit"
                            :disabled="stav === 'saving'"
                            class="shrink-0 rounded-pill bg-brick px-6 py-3 text-sm font-bold text-cream transition duration-300 ease-tavo hover:-translate-y-0.5 hover:bg-brick-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cream disabled:opacity-60">
                        <span x-show="! odeslano" @if ($questionnaire->submitted_at) x-cloak @endif>{{ text('dotaznik.submit', 'Odeslat odpovědi', 'Dotazník', 'Tlačítko') }}</span>
                        <span x-show="odeslano" @if (! $questionnaire->submitted_at) x-cloak @endif>{{ text('dotaznik.resubmit', 'Uložit změny', 'Dotazník', 'Tlačítko po odeslání') }}</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-layout.document>
