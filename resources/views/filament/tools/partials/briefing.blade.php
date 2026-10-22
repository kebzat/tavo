{{-- Ranní přehled od agenta: Markdown, bez HTML ze vstupu. --}}
<div class="ads-advice text-sm leading-relaxed text-gray-700 dark:text-gray-300">
    {{ $briefing->bodyHtml() }}
</div>
@if ($briefing->sources)
    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Zdroje: {{ collect($briefing->sources)->map(fn ($source) => is_array($source) ? implode(', ', $source) : $source)->implode(' · ') }}</p>
@endif
