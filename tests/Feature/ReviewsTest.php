<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Resources\Audits\Pages\EditAudit;
use App\Filament\Tools\Resources\Audits\Pages\ListAudits;
use App\Models\Audit;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewsTest extends TestCase
{
    use RefreshDatabase;

    private User $pavel;

    private User $tom;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('tools');
        $this->pavel = User::factory()->create(['name' => 'Pavel Zkouška', 'role' => UserRole::Admin, 'is_reviewer' => true]);
        $this->tom = User::factory()->create(['name' => 'Tom Zkouška', 'role' => UserRole::Admin, 'is_reviewer' => true]);
    }

    private function audit(): Audit
    {
        $client = Client::create(['name' => 'Kontrola Zkouška', 'slug' => 'kontrola-zkouska']);

        return Audit::create(['client_id' => $client->id, 'title' => 'Audit ke kontrole', 'body' => 'Text.', 'is_public' => true]);
    }

    public function test_kazdy_odskrtne_svou_kontrolu(): void
    {
        $audit = $this->audit();

        $this->assertTrue($audit->toggleReview($this->pavel));
        $this->assertTrue($audit->isReviewedBy($this->pavel));
        $this->assertFalse($audit->isReviewedBy($this->tom));
        $this->assertSame([$this->tom->id], $audit->missingReviewers()->pluck('id')->all());

        $this->assertFalse($audit->toggleReview($this->pavel));
        $this->assertFalse($audit->isReviewedBy($this->pavel));
    }

    public function test_zmena_obsahu_zastara_kontrolu_ostatnich(): void
    {
        $audit = $this->audit();
        $audit->toggleReview($this->pavel);
        $audit->toggleReview($this->tom);

        $this->actingAs($this->tom);
        $audit->update(['body' => 'Upravený text.']);

        $this->assertFalse($audit->isReviewedBy($this->pavel));
        $this->assertTrue($audit->reviewBy($this->pavel)->isOutdated());
        $this->assertTrue($audit->isReviewedBy($this->tom), 'Kdo upravil, svou kontrolu neztrácí.');

        // Zobrazení klientem ani zapnutí sdílení kontrolu neshodí.
        $audit->toggleReview($this->pavel);
        $audit->recordView('Mozilla/5.0');
        $audit->update(['is_public' => false]);
        $this->assertTrue($audit->isReviewedBy($this->pavel));
    }

    public function test_sloupce_v_tabulce_a_klik_do_vlastniho(): void
    {
        $audit = $this->audit();
        $this->actingAs($this->pavel);

        Livewire::test(ListAudits::class)
            ->assertTableColumnExists('review_'.$this->pavel->id)
            ->assertTableColumnExists('review_'.$this->tom->id)
            ->callTableColumnAction('review_'.$this->pavel->id, $audit);

        $this->assertTrue($audit->fresh()->isReviewedBy($this->pavel));

        Livewire::test(EditAudit::class, ['record' => $audit->getRouteKey()])
            ->assertSee('Pavel Zkouška: zkontrolováno')
            ->assertSee('Tom Zkouška: chybí')
            ->callAction('toggleReview');

        $this->assertFalse($audit->fresh()->isReviewedBy($this->pavel));
    }

    public function test_vypisy_s_kontrolou_se_nactou(): void
    {
        $this->actingAs($this->pavel);
        $this->audit();

        foreach (['audits', 'potencialni-spoluprace', 'checklists', 'reklamy/reporty'] as $resource) {
            $this->get('/nastroje/'.$resource)->assertOk()->assertSee('Pavel Zkouška – zkontrolováno');
        }
    }
}
