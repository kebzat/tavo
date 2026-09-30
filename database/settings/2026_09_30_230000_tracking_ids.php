<?php

use App\Support\ContentSettingsMigration;

/**
 * Měřicí kódy vlastního webu. Pixel a Clarity dodal Pavel, GA4 se doplní
 * v administraci, až vznikne property pod taveo.mkt@gmail.com.
 */
return new class extends ContentSettingsMigration
{
    public function up(): void
    {
        $this->add([
            'seo.ga4_id' => null,
            'seo.clarity_id' => 'yql4xqp3ev',
            'seo.meta_pixel_id' => '3060467497620222',
        ]);
    }
};
