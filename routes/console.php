<?php

use App\Models\ApiTokenCall;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ranní souhrn obchodu. Jen ve všední dny — o víkendu se neobchoduje a e-mail,
// který nemá co říct, se za měsíc přestane číst.
Schedule::command('crm:daily-digest')
    ->weekdays()
    ->at('07:00')
    ->timezone('Europe/Prague')
    ->onOneServer();

// Posouzení firem, které přibyly importem, přes API nebo ručně. Ráno před
// souhrnem, ať fronta k oslovení v 7:00 už stojí na skóre. Odkládá jen
// nevhodné firmy z rešerše, viz ProspectScout::parkIfRejected().
Schedule::command('crm:scout --unscored --park --limit=60')
    ->dailyAt('05:30')
    ->timezone('Europe/Prague')
    ->onOneServer();

// Jednou týdně nové firmy k oslovení. Bez ANTHROPIC_API_KEY příkaz nic nedělá.
Schedule::command('crm:discover')
    ->weeklyOn(1, '05:00')
    ->timezone('Europe/Prague')
    ->onOneServer();

// Reklamy klientů: stažení čísel, kontrola a ranní souhrn. Meta mívá včerejšek
// dopočítaný kolem páté ráno, v šest je stažení bezpečné. Viz docs/ADS.md.
Schedule::command('ads:sync')
    ->dailyAt('06:00')
    ->timezone('Europe/Prague')
    ->withoutOverlapping(60)
    ->onOneServer();

// Ruční obnova čísel z detailu klienta (tlačítko Obnovit čísla). Bez žádosti nic nevolá,
// účet jde obnovit nejvýš jednou za 2 hodiny (config ads.refresh_cooldown_minutes).
Schedule::command('ads:sync --requested')
    ->everyFiveMinutes()
    ->withoutOverlapping(30)
    ->onOneServer();

// Cílové stránky klientů: odpovídají a mají Meta pixel? Jeden dotaz na adresu za den.
Schedule::command('marketing:landing-check')
    ->dailyAt('06:20')
    ->timezone('Europe/Prague')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('ads:alerts')
    ->dailyAt('06:40')
    ->timezone('Europe/Prague')
    ->onOneServer();

Schedule::command('ads:digest')
    ->weekdays()
    ->at('07:15')
    ->timezone('Europe/Prague')
    ->onOneServer();

// Koncepty reportů. Neodesílají se, čekají na komentář Pavla. V 6:45, ať je
// agent v ranním běhu (6:50) najde a připraví k nim fakta, příčiny a doporučení.
Schedule::command('ads:reports weekly')
    ->weeklyOn(1, '06:45')
    ->timezone('Europe/Prague')
    ->onOneServer();

Schedule::command('ads:reports monthly')
    ->monthlyOn(1, '06:45')
    ->timezone('Europe/Prague')
    ->onOneServer();

// Ukázkový přehled spolupráce ukazuje aktuální měsíc. Viz docs/CLIENT-DASHBOARD.md.
Schedule::command('clients:dashboard-demo --refresh')
    ->monthlyOn(1, '05:00')
    ->timezone('Europe/Prague')
    ->onOneServer();

// Historie volání strojového rozhraní CRM. Na dohledání stačí čtvrt roku.
Schedule::command('model:prune', ['--model' => [ApiTokenCall::class]])
    ->dailyAt('04:00')
    ->timezone('Europe/Prague')
    ->onOneServer();

// Úlohy agenta z ClickUpu, které nedoběhly (docs/AGENT.md, ClickUp). Nic nevolá, když žádné nejsou.
Schedule::command('clickup:hlidat')
    ->everyFifteenMinutes()
    ->onOneServer();

// Klienti z ClickUpu: složka do archivu klienta (Klienti → Načíst z ClickUpu). Bez žádosti nic nevolá,
// velká složka trvá minuty (ClickUp pustí 100 dotazů za minutu), proto na pozadí. Viz docs/AGENT.md.
Schedule::command('clickup:nacist')
    ->everyMinute()
    ->withoutOverlapping(120)
    ->runInBackground()
    ->onOneServer();

// Zveřejnění práce agenta z nástrojů: testy, sloučení a nasazení na GitHubu
// běží minuty, tohle je posouvá dál. Bez rozběhnutých nic nevolá. Viz docs/AGENT.md.
Schedule::command('agent:zverejneni')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();
