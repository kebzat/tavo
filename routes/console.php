<?php

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

Schedule::command('ads:alerts')
    ->dailyAt('06:40')
    ->timezone('Europe/Prague')
    ->onOneServer();

Schedule::command('ads:digest')
    ->weekdays()
    ->at('07:15')
    ->timezone('Europe/Prague')
    ->onOneServer();

// Koncepty reportů. Neodesílají se, čekají na komentář Pavla.
Schedule::command('ads:reports weekly')
    ->weeklyOn(1, '07:30')
    ->timezone('Europe/Prague')
    ->onOneServer();

Schedule::command('ads:reports monthly')
    ->monthlyOn(1, '07:30')
    ->timezone('Europe/Prague')
    ->onOneServer();
