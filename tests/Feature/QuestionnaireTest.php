<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Tools\Resources\Questionnaires\Pages\CreateQuestionnaire;
use App\Filament\Tools\Resources\Questionnaires\Pages\EditQuestionnaire;
use App\Filament\Tools\Resources\Questionnaires\Pages\ListQuestionnaires;
use App\Models\Questionnaire;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private function dotaznik(array $atributy = []): Questionnaire
    {
        return Questionnaire::create($atributy + [
            'title' => 'Zkušební dotazník',
            'respondent' => 'Pavel Zkušební',
            'intro' => 'Úvod dotazníku',
            'is_public' => true,
            'sections' => [
                [
                    'title' => 'První sekce',
                    'questions' => [
                        ['key' => 'tyden', 'type' => 'long', 'label' => 'Jak vypadá týden?', 'help' => 'Nápověda k týdnu'],
                        ['key' => 'nastroje', 'type' => 'multi', 'label' => 'Nástroje?', 'options' => ['Meta', 'Canva', 'Notion']],
                        ['key' => 'smlouvy', 'type' => 'single', 'label' => 'Smlouvy?', 'options' => ['Ano', 'Ne']],
                        ['key' => 'uspora', 'type' => 'short', 'label' => 'Kolik hodin?'],
                    ],
                ],
                ['title' => 'Prázdná sekce', 'questions' => []],
            ],
        ]);
    }

    private function spravceVPaneluNastroju(): User
    {
        Filament::setCurrentPanel('tools');

        return User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_migrace_zalozi_sdileny_dotaznik_pro_pavla(): void
    {
        $pavel = Questionnaire::query()->where('respondent', 'Pavel Včeliš')->sole();

        $this->assertTrue($pavel->is_public);
        $this->assertStringStartsWith('pavel-vcelis-', $pavel->slug);
        $this->assertGreaterThan(20, $pavel->progress()['total']);

        $this->get($pavel->publicUrl())
            ->assertOk()
            ->assertSee('Co ti můžeme zautomatizovat')
            ->assertSee('Která data by AI neměla vidět vůbec?');
    }

    public function test_adresa_ma_nahodnou_cast(): void
    {
        $this->assertMatchesRegularExpression('/^pavel-zkusebni-[a-z0-9]{6}$/', $this->dotaznik()->slug);
    }

    public function test_sdileny_dotaznik_ukaze_otazky_a_vynecha_prazdnou_sekci(): void
    {
        $this->get($this->dotaznik()->publicUrl())
            ->assertOk()
            ->assertSee('noindex, nofollow', false)
            ->assertSee('Jak vypadá týden?')
            ->assertSee('Nápověda k týdnu')
            ->assertSee('value="Canva"', false)
            ->assertDontSee('Prázdná sekce');
    }

    public function test_nesdileny_dotaznik_vidi_jen_prihlaseny(): void
    {
        $dotaznik = $this->dotaznik(['is_public' => false]);

        $this->assertNull($dotaznik->publicUrl());
        $this->get($dotaznik->previewUrl())->assertNotFound();
        $this->post(route('questionnaire.save', $dotaznik->slug), ['answers' => ['tyden' => 'x']])->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
            ->get($dotaznik->previewUrl())
            ->assertOk();
    }

    public function test_ukladani_na_pozadi_vycisti_odpovedi_a_vrati_progres(): void
    {
        $dotaznik = $this->dotaznik();

        $this->postJson(route('questionnaire.save', $dotaznik->slug), [
            'answers' => [
                'tyden' => '  Hlavně kampaně.  ',
                'nastroje' => ['Meta', 'Cizí nástroj', 'Notion'],
                'smlouvy' => 'Možná',
                'uspora' => '',
                'neznamy' => 'zahodit',
            ],
        ])
            ->assertOk()
            ->assertJson(['answered' => 2, 'total' => 4, 'percent' => 50, 'odeslano' => false]);

        $dotaznik->refresh();

        $this->assertSame(['tyden' => 'Hlavně kampaně.', 'nastroje' => ['Meta', 'Notion']], $dotaznik->answers);
        $this->assertNotNull($dotaznik->answered_at);
        $this->assertNull($dotaznik->submitted_at);
    }

    public function test_formular_posila_vse_takze_odskrtnuta_moznost_zmizi(): void
    {
        $dotaznik = $this->dotaznik(['answers' => ['nastroje' => ['Meta'], 'tyden' => 'Text']]);

        $this->postJson(route('questionnaire.save', $dotaznik->slug), ['answers' => ['tyden' => 'Text']])->assertOk();

        $this->assertSame(['tyden' => 'Text'], $dotaznik->fresh()->answers);
    }

    public function test_odeslani_bez_js_ulozi_a_presmeruje_zpet(): void
    {
        $dotaznik = $this->dotaznik();

        // Bez CSRF tokenu: oprávnění dává adresa a vypršelá session nesmí odpovědi zahodit.
        $this->post(route('questionnaire.save', $dotaznik->slug), [
            'answers' => ['smlouvy' => 'Ano'],
            'odeslat' => '1',
        ])->assertRedirect($dotaznik->previewUrl());

        $dotaznik->refresh();
        $this->assertSame(['smlouvy' => 'Ano'], $dotaznik->answers);
        $this->assertNotNull($odeslano = $dotaznik->submitted_at);

        // Další úprava čas prvního odeslání nepřepíše.
        $this->travel(1)->hour();
        $this->postJson(route('questionnaire.save', $dotaznik->slug), ['answers' => ['smlouvy' => 'Ne'], 'odeslat' => '1'])->assertOk();
        $this->assertEquals($odeslano, $dotaznik->fresh()->submitted_at);

        $this->get($dotaznik->previewUrl())->assertSee('Uložit změny');
    }

    public function test_markdown_obsahuje_odpovedi_i_chybejici(): void
    {
        $markdown = $this->dotaznik(['answers' => ['tyden' => 'Kampaně', 'nastroje' => ['Meta', 'Canva']]])->toMarkdown();

        $this->assertStringContainsString("# Zkušební dotazník\n", $markdown);
        $this->assertStringContainsString("### 1. Jak vypadá týden?\n\nKampaně", $markdown);
        $this->assertStringContainsString("- Meta\n- Canva", $markdown);
        $this->assertStringContainsString("### 4. Kolik hodin?\n\n_bez odpovědi_", $markdown);
    }

    public function test_robots_txt_dotazniky_skryva(): void
    {
        $this->get('/robots.txt')->assertSee('Disallow: /dotaznik');
    }

    public function test_seznam_editace_a_zalozeni_v_nastrojich(): void
    {
        $admin = $this->spravceVPaneluNastroju();
        $dotaznik = $this->dotaznik(['answers' => ['tyden' => 'Kampaně']]);

        Livewire::actingAs($admin)
            ->test(ListQuestionnaires::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$dotaznik])
            ->assertSee('1 z 4');

        Livewire::actingAs($admin)
            ->test(EditQuestionnaire::class, ['record' => $dotaznik->getRouteKey()])
            ->assertOk()
            ->assertActionVisible('answers');

        $okno = view('filament.tools.questionnaire-answers', ['sections' => $dotaznik->sectionItems()])->render();
        $this->assertStringContainsString('Kampaně', $okno);
        $this->assertStringContainsString('bez odpovědi', $okno);

        Livewire::actingAs($admin)
            ->test(CreateQuestionnaire::class)
            ->fillForm([
                'title' => 'Dotazník pro klienta',
                'respondent' => 'Klient s.r.o.',
                'sections' => [
                    ['title' => 'Úvod', 'intro' => null, 'questions' => [
                        ['key' => null, 'label' => 'Co prodáváte?', 'type' => 'long', 'help' => null, 'options' => []],
                    ]],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $novy = Questionnaire::query()->where('title', 'Dotazník pro klienta')->sole();
        $otazka = $novy->sectionItems()[0]['questions'][0];

        $this->assertSame('Co prodáváte?', $otazka['label']);
        $this->assertNotEmpty($otazka['key']);
        $this->assertFalse($novy->is_public);
    }
}
