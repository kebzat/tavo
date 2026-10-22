{{-- Odpovědi dotazníku v okně v nástrojích. Nezodpovězené otázky zůstávají, ať je vidět, co chybí. --}}
<div class="space-y-8">
    @forelse ($sections as $section)
        <section class="space-y-4">
            @if ($section['title'])
                <h3 class="text-base font-bold text-gray-950 dark:text-white">{{ $section['title'] }}</h3>
            @endif

            @foreach ($section['questions'] as $question)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $question['number'] }}. {{ $question['label'] }}</p>

                    @if (blank($question['answer']))
                        <p class="mt-2 text-sm text-gray-400 italic">bez odpovědi</p>
                    @elseif (is_array($question['answer']))
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                            @foreach ($question['answer'] as $option)
                                <li>{{ $option }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $question['answer'] }}</p>
                    @endif
                </div>
            @endforeach
        </section>
    @empty
        <p class="text-sm text-gray-500">Dotazník zatím nemá žádné otázky.</p>
    @endforelse
</div>
