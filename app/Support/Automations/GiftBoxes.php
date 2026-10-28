<?php

namespace App\Support\Automations;

use App\Models\Automation;
use Illuminate\Support\Carbon;

/**
 * Dárkové balíčky ze skládačky na e-shopu (zatím Svět Cejlonu). Krabice nese
 * v poznámce položky (ITEM/NOTE v exportu Upgates) obsah balíčku, odkaz na
 * obrázek poskládané krabice a značky `[balicek][idProduktu_idVarianty]`
 * za každý kus:
 *
 *   Dárkový balíček: BELI MAL – Sušený květ 40 g, 2× SOURSOP - ZELENÝ ČAJ 20 G
 *   – OBRÁZEK, JAK KRABICI POSKLÁDAT: https://…/b/sVqGtBlQ [balicek][38_110][61_202][61_202]
 *
 * Z poznámky se bere jen obsah a odkaz, o zákazníkovi nic. Přehled v účtu
 * klienta ukáže balíčky k zabalení i s obrázkem, ať balírna nemusí do
 * detailu objednávky. Viz docs/AUTOMATIZACE.md.
 */
class GiftBoxes
{
    public const MARK = '[balicek]';

    /** Jak dlouho ukazovat už odeslané balíčky. */
    public const DONE_DAYS = 14;

    /**
     * @return ?array{title: string, items: list<array{qty: int, name: string}>, count: int, preview: ?string}
     */
    public static function fromNote(string $title, string $note): ?array
    {
        $mark = strpos($note, self::MARK);

        if ($mark === false) {
            return null;
        }

        $text = substr($note, 0, $mark);
        $preview = null;

        if (preg_match('~https://[a-z0-9.-]+/b/[A-Za-z0-9_-]{8}\b~i', $text, $match, PREG_OFFSET_CAPTURE)) {
            $preview = $match[0][0];
            // Před odkazem je popisek („– OBRÁZEK, JAK KRABICI POSKLÁDAT:“, dřív „– náhled krabice:“).
            $text = preg_replace('~\s*–[^–]*:\s*$~u', '', substr($text, 0, $match[0][1])) ?? '';
        }

        $text = trim(preg_replace('~^\s*Dárkový balíček:\s*~iu', '', $text) ?? '');
        $items = [];

        // Kusy odděluje čárka, za kterou začíná další název (velké písmeno) nebo počet („2× …“).
        foreach (preg_split('~,\s+(?=\d+×\s|\p{Lu})~u', $text) ?: [] as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $items[] = preg_match('~^(\d+)×\s*(.+)$~u', $part, $m)
                ? ['qty' => (int) $m[1], 'name' => trim($m[2])]
                : ['qty' => 1, 'name' => $part];
        }

        $marks = preg_match_all('~\[\d+_\d*\]~', substr($note, $mark));

        return [
            'title' => trim($title) ?: 'Dárková krabice',
            'items' => $items,
            'count' => $marks ?: array_sum(array_column($items, 'qty')),
            'preview' => $preview,
        ];
    }

    /**
     * Obnoví jen přehled balíčků z exportu objednávek, zákazníkům nic
     * neposílá. Volá ho `automations:boxes` (každých 10 minut). Hodinový
     * běh ParcelPickup (i tlačítko Aktualizovat v účtu klienta) přehled
     * obnovuje taky.
     *
     * @return int kolik objednávek čeká na zabalení
     */
    public static function refresh(Automation $automation, UpgatesOrders $orders, ?UpgatesOrderLinks $links = null): int
    {
        $rows = self::overview($automation, $orders->load($automation)['orders']);
        $snapshot = $automation->fresh()?->snapshot ?? [];

        $automation->forceFill(['snapshot' => [...$snapshot, 'boxes' => $rows, 'boxes_updated_at' => now()->toIso8601String()]])->save();
        $links?->resolve($automation, array_column($rows, 'order'));

        return count(array_filter($rows, fn (array $row): bool => $row['to_pack']));
    }

    /** Kdy se přehled balíčků naposledy obnovil (tlačítkem, po 10 minutách nebo hodinovým během). */
    public static function updatedAt(Automation $automation): ?Carbon
    {
        $times = array_filter([$automation->snapshot['boxes_updated_at'] ?? null, $automation->snapshot['updated_at'] ?? null]);

        return $times === [] ? null : Carbon::parse(max(array_map(fn (string $time): string => Carbon::parse($time)->toIso8601String(), $times)));
    }

    /**
     * Objednávky s balíčkem pro přehled: nejdřív k zabalení (nejstarší
     * nahoře), pak odeslané za posledních 14 dní. Stornované se vynechají.
     *
     * @param  array<string, array<string, mixed>>  $orders  z UpgatesOrders::load()
     * @return list<array{order: string, status: string, created_at: ?string, to_pack: bool, boxes: list<array<string, mixed>>}>
     */
    public static function overview(Automation $automation, array $orders): array
    {
        $done = array_values(array_filter(array_map(fn (string $key): string => $automation->status($key), ['sent', 'reminder', 'last_day', 'returned', 'resolved'])));
        $since = now()->subDays(self::DONE_DAYS);
        $rows = [];

        foreach ($orders as $number => $order) {
            if (empty($order['boxes'])) {
                continue;
            }

            $status = trim((string) $order['status']);

            if (preg_match('~storn~iu', $status)) {
                continue;
            }

            $created = $order['created_at'] ?? null;
            $toPack = ! in_array($status, $done, true);

            if (! $toPack && $created !== null && Carbon::parse($created)->lt($since)) {
                continue;
            }

            $rows[] = ['order' => (string) $number, 'status' => $status, 'created_at' => $created, 'to_pack' => $toPack, 'boxes' => $order['boxes']];
        }

        usort($rows, fn (array $a, array $b): int => [$b['to_pack'], $a['to_pack'] ? $a['created_at'] : $b['created_at']]
            <=> [$a['to_pack'], $a['to_pack'] ? $b['created_at'] : $a['created_at']]);

        return $rows;
    }
}
