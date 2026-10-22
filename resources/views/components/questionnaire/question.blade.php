@props(['question'])

{{--
    Jedna otázka dotazníku. Skupina místo fieldsetu: legenda by se usadila
    do rámečku karty. Možnosti jsou zaškrtávátka převlečená za štítky:
    vybraný štítek ztmavne, fokus z klávesnice dostane cihlový obrys.
--}}
<div role="group" aria-labelledby="otazka-{{ $question['key'] }}" class="rounded-card border border-ink/14 bg-cream p-6 menu:p-8">
    <p class="flex gap-3 text-body-lg font-bold leading-[1.35] text-ink">
        <span class="shrink-0 text-brick tabular-nums">{{ $question['number'] }}.</span>
        <span id="otazka-{{ $question['key'] }}">{{ $question['label'] }}</span>
    </p>

    @if ($question['help'])
        <p id="otazka-{{ $question['key'] }}-napoveda" class="mt-2 text-[15px] leading-[1.55] text-muted">{{ $question['help'] }}</p>
    @endif

    <div class="mt-5">
        @switch($question['type'])
            @case('multi')
            @case('single')
                <div class="flex flex-wrap gap-2.5">
                    @foreach ($question['options'] as $option)
                        <label class="cursor-pointer rounded-pill border-[1.5px] border-ink/25 bg-cream/60 px-4 py-2.5 text-[15px] font-semibold text-ink transition duration-300 ease-tavo hover:border-ink has-[:checked]:border-ink has-[:checked]:bg-ink has-[:checked]:text-cream has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-brick">
                            @if ($question['type'] === 'multi')
                                <input type="checkbox" class="sr-only"
                                       name="answers[{{ $question['key'] }}][]"
                                       value="{{ $option }}"
                                       @checked(in_array($option, (array) $question['answer'], true))>
                            @else
                                <input type="radio" class="sr-only"
                                       name="answers[{{ $question['key'] }}]"
                                       value="{{ $option }}"
                                       @checked($question['answer'] === $option)>
                            @endif
                            {{ $option }}
                        </label>
                    @endforeach
                </div>
                @break

            @case('short')
                <input type="text"
                       name="answers[{{ $question['key'] }}]"
                       value="{{ $question['answer'] }}"
                       aria-labelledby="otazka-{{ $question['key'] }}"
                       @if ($question['help']) aria-describedby="otazka-{{ $question['key'] }}-napoveda" @endif
                       class="w-full rounded-2xl border-[1.5px] border-ink/25 bg-cream/60 px-4 py-3.5 text-[15px] text-ink transition focus:border-ink focus:bg-cream focus:outline-none">
                @break

            @default
                <textarea name="answers[{{ $question['key'] }}]"
                          rows="4"
                          aria-labelledby="otazka-{{ $question['key'] }}"
                          @if ($question['help']) aria-describedby="otazka-{{ $question['key'] }}-napoveda" @endif
                          class="field-sizing-content min-h-[7.5rem] w-full rounded-2xl border-[1.5px] border-ink/25 bg-cream/60 px-4 py-3.5 text-[15px] leading-[1.6] text-ink transition focus:border-ink focus:bg-cream focus:outline-none">{{ $question['answer'] }}</textarea>
        @endswitch
    </div>
</div>
