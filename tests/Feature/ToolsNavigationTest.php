<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Menu nástrojů podle práce: klienti, reklamy, mozek, obchod, nastavení (26. 10. 2026). */
class ToolsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_je_rozdelene_podle_prace(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        Filament::setCurrentPanel('tools');

        $menu = collect(Filament::getCurrentPanel()->getNavigation())
            ->mapWithKeys(fn (NavigationGroup $group): array => [(string) ($group->getLabel() ?? '') => collect($group->getItems())->map(fn (NavigationItem $item): string => (string) $item->getLabel())->all()])
            ->all();

        $this->assertSame(['', 'Klienti', 'Reklamy', 'Mozek', 'Obchod', 'Nastavení'], array_keys($menu));
        $this->assertSame(['Ranní přehled', 'Projekty'], $menu['']);
        $this->assertSame(['Zeptat se', 'Skilly', 'Poznatky', 'Archiv a podklady'], $menu['Mozek']);
        $this->assertSame(['Klienti', 'Hodiny', 'Fakturace', 'Výhled paušálů', 'Checklisty', 'Dotazníky', 'Trezor', 'Automatizace'], $menu['Klienti']);
        $this->assertSame(['Přehled', 'Upozornění', 'Termíny', 'Reporty', 'Reklamní grafika'], $menu['Reklamy']);
        $this->assertContains('Nastavení CRM', $menu['Nastavení']);
        $this->assertContains('Přístupy agentů', $menu['Nastavení']);
    }
}
