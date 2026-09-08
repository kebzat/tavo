<?php

use App\Support\ContentSettingsMigration;

/**
 * Dvě věci k poptávkovému formuláři.
 *
 * 1. Web má mít jednu adresu, na kterou poptávky chodí: spoluprace@taveo.cz.
 *    Stará ahoj@taveo.cz se mění jen tam, kde ještě stojí, takže instalaci,
 *    kde už je adresa přepsaná v administraci, se migrace nedotkne.
 *
 * 2. Text nad formulářem sliboval odpověď do dvou pracovních dnů. Slib, který
 *    nikdo nehlídá, se v prvním rušném týdnu poruší. Nově se říká, že se
 *    ozveme, a kdo nechce čekat, má pod formulářem telefon na Pavla i Toma.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->replaceIfUntouched('contact.email', 'ahoj@taveo.cz', 'spoluprace@taveo.cz');
        $this->replaceIfUntouched('contact.lead_recipients', ['ahoj@taveo.cz'], ['spoluprace@taveo.cz']);

        $this->replaceIfUntouched(
            'home.cta_perex',
            'Stačí pár vět o tom, co děláte a co od webu čekáte. Ozveme se do dvou pracovních dnů a rovnou napíšeme, jestli vám umíme pomoct. Když ne, doporučíme někoho, kdo ano.',
            'Stačí pár vět o tom, co děláte a co od webu čekáte. Ozveme se vám a rovnou napíšeme, jestli vám umíme pomoct. Když ne, doporučíme někoho, kdo ano.',
        );
    }
};
