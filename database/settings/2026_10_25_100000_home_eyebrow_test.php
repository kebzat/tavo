<?php

use App\Support\ContentSettingsMigration;

/**
 * Nadpis nad heroem na úvodní stránce dostane na konec „test“.
 * Text na webu je z administrace, takže jen když tam pořád stojí tahle verze.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->replaceIfUntouched(
            'home.hero_eyebrow',
            'Dva specialisté. Méně režie. Více efektivní práce.',
            'Dva specialisté. Méně režie. Více efektivní práce. test',
        );
    }
};
