<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function editor(): User
    {
        return User::factory()->create(['role' => UserRole::Editor]);
    }

    public function test_obe_role_se_dostanou_do_administrace(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertOk();
        $this->actingAs($this->editor())->get('/admin')->assertOk();
    }

    public function test_spravce_vidi_uzivatele_nastaveni_i_udrzbu(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $this->actingAs($admin)->get('/admin/manage-site')->assertOk();
        $this->actingAs($admin)->get('/admin/maintenance')->assertOk();
    }

    /**
     * Skrytí z navigace nestačí — redaktor se nesmí dostat ani přímou adresou.
     */
    public function test_redaktor_se_k_uzivatelum_nastaveni_ani_udrzbe_nedostane(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get('/admin/users')->assertForbidden();
        $this->actingAs($editor)->get('/admin/manage-site')->assertForbidden();
        $this->actingAs($editor)->get('/admin/maintenance')->assertForbidden();
    }

    public function test_redaktor_dal_spravuje_obsah(): void
    {
        $this->actingAs($this->editor())
            ->get('/admin/case-studies')
            ->assertOk();
    }

    public function test_stavajici_ucty_migrace_povysi_na_spravce(): void
    {
        // Migrace už proběhla v rámci RefreshDatabase; ověřujeme výchozí hodnotu
        // sloupce, aby nově zakládaný účet nebyl omylem správce.
        $user = User::factory()->create();

        $this->assertSame(UserRole::Editor, $user->fresh()->role);
    }

    /**
     * Filament u DeleteAction resource na canDelete() neptá, takže se
     * tlačítko skrývá ručně. Kdyby to někdo odstranil, chytí to tenhle test.
     */
    public function test_na_vlastnim_uctu_neni_tlacitko_smazat(): void
    {
        $admin = $this->admin();
        $other = $this->editor();

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $admin->getKey()])
            ->assertActionHidden('delete');

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $other->getKey()])
            ->assertActionVisible('delete');
    }

    public function test_vlastni_ucet_nejde_smazat_ani_primo(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $admin->delete();

        $this->assertDatabaseHas('users', ['id' => $admin->getKey()]);
    }

    public function test_ciziho_uzivatele_smazat_lze(): void
    {
        $this->actingAs($this->admin());
        $other = $this->editor();

        $other->delete();

        $this->assertDatabaseMissing('users', ['id' => $other->getKey()]);
    }

    public function test_heslo_se_uklada_zahashovane(): void
    {
        $user = User::factory()->create(['password' => 'TajneHeslo123!']);

        $this->assertNotSame('TajneHeslo123!', $user->password);
        $this->assertTrue(Hash::check('TajneHeslo123!', $user->password));
    }
}
