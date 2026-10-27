<?php

namespace App\Support\Knowledge;

use App\Models\Client;
use App\Models\KnowledgeEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Které klienty text zmiňuje (jménem i v jiném pádě, slugem, projektem
 * agenta). Pojistka mozku: firemní poznatek a postup platí pro všechny
 * klienty, takže nesmí nést jméno ani data jednoho z nich, a poznatek
 * klienta nesmí nést data jiného klienta. Viz docs/MOZEK.md, Oddělení klientů.
 */
class ClientMentions
{
    /** @var array<int, array{client: Client, pattern: string}>|null */
    private ?array $patterns = null;

    /**
     * @return Collection<int, Client> zmínění klienti, `$own` (klient poznatku) se nepočítá
     */
    public function in(string $text, ?Client $own = null): Collection
    {
        $text = self::normalize($text);
        $patterns = collect($this->patterns());

        if ($own) {
            // „JMP stroje“ v poznatku klienta JMP stroje není zmínka o klientovi JMP.
            $text = (string) preg_replace($patterns->firstWhere('client.id', $own->getKey())['pattern'] ?? '/(?!)/', ' ', $text);
            $patterns = $patterns->reject(fn (array $item): bool => $item['client']->is($own));
        }

        return $patterns
            ->filter(fn (array $item): bool => preg_match($item['pattern'], $text) === 1)
            ->map(fn (array $item): Client => $item['client'])
            ->values();
    }

    /**
     * Proč poznatek do své vrstvy nepatří (null = v pořádku). Firemní
     * poznatek a postup nesmí zmínit žádného klienta, poznatek klienta
     * jiného klienta.
     */
    public function problem(string $layer, ?Client $client, string ...$texts): ?string
    {
        $isClient = $layer === KnowledgeEntry::LAYER_CLIENT;
        $names = $this->in(implode("\n", $texts), $isClient ? $client : null)->pluck('name');

        if ($names->isEmpty()) {
            return null;
        }

        $list = $names->implode(', ');

        return $isClient
            ? "Zmiňuje jiného klienta ({$list}). Poznatek klienta {$client?->name} nesmí obsahovat data jiného klienta, ať se nemíchají. Co se týká {$list}, patří do jeho mozku."
            : "Zmiňuje klienta ({$list}). Firemní poznatek a postup dostávají agenti všech klientů, proto v nich nesmí být jméno ani data klienta. Dej ho do vrstvy klient, nebo jméno, čísla a údaje klienta vynech a napiš to obecně.";
    }

    public static function normalize(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /** @return array<int, array{client: Client, pattern: string}> */
    private function patterns(): array
    {
        return $this->patterns ??= Client::query()->get(['id', 'name', 'slug', 'agent_project'])
            ->map(fn (Client $client): ?array => ($pattern = self::pattern($client)) ? ['client' => $client, 'pattern' => $pattern] : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Jméno po slovech, každé i s českou koncovkou (Snowbearu, Světa
     * Cejlonu, České louky), dál slug, projekt agenta a jméno bez mezer.
     */
    private static function pattern(Client $client): ?string
    {
        $words = collect(preg_split('/[^a-z0-9]+/', self::normalize((string) $client->name)) ?: [])->filter(fn (string $word): bool => $word !== '')->values();

        if ($words->isEmpty()) {
            return null;
        }

        $name = $words->map(function (string $word): string {
            $stem = strlen($word) >= 4 && preg_match('/[aeiouy]$/', $word) ? substr($word, 0, -1) : $word;

            return preg_quote($stem, '/').'[a-z]{0,3}';
        })->implode('[^a-z0-9]{1,3}');

        $exact = collect([$client->slug, $client->agent_project, $words->implode('')])
            ->filter(fn (?string $value): bool => filled($value) && strlen((string) $value) >= 3)
            ->map(fn (string $value): string => preg_quote(self::normalize($value), '/'))
            ->unique();

        return '/(?<![a-z0-9])(?:'.$exact->push($name)->implode('|').')(?![a-z0-9])/';
    }
}
