<?php

use App\Support\ContentSettingsMigration;

/**
 * Nastavení reklamní grafiky. Klíč ke Gemini je prázdný, vkládá se
 * v nástrojích (Reklamy → Nastavení grafiky) a ukládá se šifrovaně.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('grafika.gemini_api_key')) {
            $this->migrator->addEncrypted('grafika.gemini_api_key', null);
        }

        $this->add([
            'grafika.gemini_model' => 'gemini-2.5-flash-image',
            'grafika.max_images' => 12,
        ]);
    }
};
