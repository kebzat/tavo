<?php

namespace App\Support\Crm;

use App\Enums\Crm\DemandSource;
use App\Enums\Crm\Priority;
use App\Models\Crm\Demand;
use Illuminate\Support\Carbon;

/**
 * Přijetí poptávek z externí automatizace.
 *
 * Zdrojem pravdy je adresa poptávky: podle ní se pozná, jestli řádek zakládáme,
 * nebo aktualizujeme. Ranní běh tak může poslat celý dnešní výpis z portálu
 * a nezaloží duplicity.
 *
 * Náš stav poptávky (`status`, `replied_at`, `company_id`, `notes`) import
 * nikdy nepřepisuje — ten patří nám, ne portálu.
 */
class DemandImporter
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(array $rows): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($rows as $row) {
            $url = is_array($row) ? trim((string) ($row['url'] ?? '')) : '';

            // Bez adresy nemáme podle čeho poznat duplicitu. Takový řádek
            // radši zahodíme, než abychom pokaždé zakládali nový.
            if ($url === '') {
                $result['skipped']++;

                continue;
            }

            $existing = Demand::where('url', $url)->first();

            $attributes = [
                'source' => $this->source($row['source'] ?? null),
                'title' => trim((string) ($row['title'] ?? '')) ?: 'Poptávka bez názvu',
                'summary' => $this->text($row['summary'] ?? null),
                'posted_at' => $this->date($row['posted_at'] ?? null),
                'budget_estimate' => $this->text($row['budget_estimate'] ?? null),
                'priority' => $this->priority($row['priority'] ?? null),
            ];

            if ($existing !== null) {
                $existing->update($attributes);
                $result['updated']++;

                continue;
            }

            // Náš stav (status, replied_at, notes) se u existující poptávky
            // nikdy nepřepisuje, ale u nově zakládané ho převzít můžeme —
            // žádný vlastní zatím nemáme. Tabulka rešerše ho takhle doveze.
            Demand::create($attributes + ['url' => $url] + array_filter([
                'status' => $row['status'] ?? null,
                'replied_at' => $this->date($row['replied_at'] ?? null),
                'notes' => $this->text($row['notes'] ?? null),
            ]));
            $result['created']++;
        }

        return $result;
    }

    private function source(mixed $value): DemandSource
    {
        return DemandSource::tryFrom(mb_strtolower(trim((string) $value))) ?? DemandSource::Other;
    }

    private function priority(mixed $value): Priority
    {
        return Priority::tryFrom(mb_strtoupper(trim((string) $value))) ?? Priority::B;
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function date(mixed $value): ?Carbon
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        // Portály posílají různé tvary data. Nečitelné datum poptávku nezahodí,
        // jen se u ní neukáže — to je pořád lepší než spadlý import.
        try {
            return Carbon::parse($text)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
