<?php

/*
|--------------------------------------------------------------------------
| Interní mini CRM
|--------------------------------------------------------------------------
| Nastavení, které nepatří správci do administrace — tokeny a zakládání účtů.
| Provozní hodnoty (týdenní cíle, adresáti souhrnu) bydlí v App\Settings\CrmSettings,
| protože je chceme měnit z prohlížeče bez nasazení.
*/

return [

    /*
     * Token pro strojové endpointy (import poptávek, export pipeline).
     * Prázdný token endpointy vypne — bez něj by byly veřejné.
     */
    'import_token' => env('CRM_IMPORT_TOKEN'),

    /*
     * Účty, které založí `php artisan db:seed --class=CrmSeeder`.
     * Formát: "Jméno:email:heslo,Jméno:email:heslo"
     */
    'users' => env('CRM_USERS'),

];
