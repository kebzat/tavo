<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Support\Automations\GiftBoxes;
use App\Support\Automations\UpgatesOrders;
use Illuminate\Console\Command;
use Throwable;

/**
 * Obnoví přehled dárkových balíčků k zabalení (každých 10 minut), ať
 * balírna nečeká na hodinový běh. Jen u zapnutých automatizací, které
 * balíčky mají. Zákazníkům nic neposílá. Viz docs/AUTOMATIZACE.md.
 */
class AutomationsBoxes extends Command
{
    protected $signature = 'automations:boxes';

    protected $description = 'Obnoví přehled dárkových balíčků k zabalení z exportu objednávek';

    public function handle(UpgatesOrders $orders): int
    {
        $automations = Automation::query()->where('enabled', true)->orderBy('id')->get()
            ->filter(fn (Automation $automation): bool => $automation->giftBoxes() !== []);

        foreach ($automations as $automation) {
            try {
                $this->line("#{$automation->id}: k zabalení ".GiftBoxes::refresh($automation, $orders).'.');
            } catch (Throwable $e) {
                // Text chyby může obsahovat adresu exportu s klíčem, ven jen druh. Hodinový běh chyby hlásí sám.
                report($e);
                $this->warn("#{$automation->id}: přehled balíčků se nepodařilo obnovit (".class_basename($e).').');
            }
        }

        return self::SUCCESS;
    }
}
