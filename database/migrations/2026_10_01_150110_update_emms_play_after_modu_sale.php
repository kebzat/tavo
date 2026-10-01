<?php

use App\Models\Proposal;
use Illuminate\Database\Migrations\Migration;

/**
 * Emm's play během 1. 10. 2026 stáhli prošlou akci na chodítko MODU
 * (horní lišta i první banner). Bod o ní by už nebyl pravda, tak ho
 * odebereme a texty, které se na něj odkazují, přepíšeme.
 *
 * Mění jen texty, které pořád znějí přesně jako v podkladu
 * (database/seeders/proposals/emms-play.json). Co mezitím upravili
 * v nástrojích, nechá být.
 */
return new class extends Migration
{
    private const FINDING = 'Akce na MODU skončila včera, web ji pořád nabízí';

    /** @var array<string, array{0: string, 1: string}> nadpis => [původní text, nový text] */
    private const REPLACE = [
        'timeline:Dnes' => [
            'Horní lišta i první banner nabízí chodítko MODU za 2 399 Kč „jen do 30. 9. 2026“. Pod tím kategorie, novinky do školky a nejprodávanější produkty.',
            'Snímek z rána 1. 10.: horní lišta a první banner ještě nabízely chodítko MODU za 2 399 Kč „jen do 30. 9.“, během dne zmizely. Pod tím kategorie, novinky do školky a nejprodávanější produkty.',
        ],
        'recommendations:Opravit problémová místa na webu do 14 dní' => [
            'Prošlou akci na MODU pryč a bannery s datem nastavené tak, aby samy zmizely. Vánoce do menu a na úvodní stránku, konkrétní výhody do horní lišty a sezónní doporučení u produktů přepnout z jara na zimu. Na WooCommerce, bez migrace a bez výpadku.',
            'Bannery a lišty s datem nastavené tak, aby samy zmizely, až akce skončí. Vánoce do menu a na úvodní stránku, konkrétní výhody do horní lišty a sezónní doporučení u produktů přepnout z jara na zimu. Na WooCommerce, bez migrace a bez výpadku.',
        ],
        'steps:Úklid úvodní stránky' => [
            'Prošlá akce pryč, konkrétní výhody v liště, jaro vystřídá zima.',
            'Akce s datem, které samy zmizí, konkrétní výhody v liště, jaro vystřídá zima.',
        ],
    ];

    public function up(): void
    {
        $proposal = Proposal::where('slug', 'emms-play')->first();

        if (! $proposal) {
            return;
        }

        $original = collect(json_decode(file_get_contents(database_path('seeders/proposals/emms-play.json')), true)['findings'])
            ->firstWhere('title', self::FINDING);

        $changes = [
            'findings' => collect($proposal->findings ?? [])
                ->reject(fn (array $row): bool => ($row['title'] ?? null) === self::FINDING && ($row['body'] ?? null) === ($original['body'] ?? null))
                ->values()
                ->all(),
        ];

        foreach (self::REPLACE as $key => [$from, $to]) {
            [$section, $title] = explode(':', $key, 2);
            $changes[$section] = collect($changes[$section] ?? $proposal->{$section} ?? [])
                ->map(fn (array $row): array => ($row['title'] ?? null) === $title && ($row['body'] ?? null) === $from
                    ? [...$row, 'body' => $to]
                    : $row)
                ->all();
        }

        $proposal->update($changes);
    }

    public function down(): void
    {
        // Akce už na webu není, vracet ji do textů nedává smysl.
    }
};
