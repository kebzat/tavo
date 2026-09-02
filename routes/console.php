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
