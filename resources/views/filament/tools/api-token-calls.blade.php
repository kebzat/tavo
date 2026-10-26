{{-- Posledních sto volání strojového rozhraní jedním tokenem. --}}
@if ($calls->isEmpty())
    <p class="text-sm text-gray-500">Token zatím nikdo nepoužil.</p>
@else
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10">
                <th class="py-2 pr-4 font-medium">Kdy</th>
                <th class="py-2 pr-4 font-medium">Co</th>
                <th class="py-2 pr-4 font-medium">Odkud</th>
                <th class="py-2 font-medium">Výsledek</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($calls as $call)
                <tr class="border-b border-gray-100 dark:border-white/5">
                    <td class="py-2 pr-4 whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $call->called_at->format('j. n. Y H:i') }}</td>
                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $call->endpoint }}</td>
                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $call->ip }}</td>
                    <td @class([
                        'py-2 font-semibold',
                        'text-success-600' => $call->status < 400,
                        'text-danger-600' => $call->status >= 400,
                    ])>{{ $call->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
