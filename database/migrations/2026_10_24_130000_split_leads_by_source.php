<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Poptávky podle zdroje: formulář přímo na Metě (instant form) a poptávka
 * na webu (pixel). Pavel chce u konverzí vědět, o jaký typ jde; součet
 * zůstává ve sloupci `leads`. Starší řádky se doplní ze surových actions.
 */
return new class extends Migration
{
    private const FORM = ['onsite_conversion.lead_grouped', 'onsite_conversion.lead'];

    private const WEB = ['offsite_conversion.fb_pixel_lead', 'onsite_web_lead'];

    public function up(): void
    {
        Schema::table('ad_daily_stats', function (Blueprint $table) {
            $table->decimal('form_leads', 12, 2)->default(0)->after('leads');
            $table->decimal('web_leads', 12, 2)->default(0)->after('form_leads');
        });

        // Po kouscích v PHP, ať to jede na MySQL i SQLite.
        DB::table('ad_daily_stats')
            ->whereNotNull('raw')
            ->where('leads', '>', 0)
            ->select(['id', 'raw'])
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $actions = json_decode((string) $row->raw, true)['actions'] ?? null;
                    $form = self::pick($actions, self::FORM);
                    $web = self::pick($actions, self::WEB);

                    if ($form > 0 || $web > 0) {
                        DB::table('ad_daily_stats')->where('id', $row->id)->update(['form_leads' => $form, 'web_leads' => $web]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('ad_daily_stats', function (Blueprint $table) {
            $table->dropColumn(['form_leads', 'web_leads']);
        });
    }

    /** @param  list<string>  $types */
    private static function pick(mixed $actions, array $types): float
    {
        if (! is_array($actions)) {
            return 0.0;
        }

        foreach ($types as $type) {
            if (array_key_exists($type, $actions)) {
                return (float) $actions[$type];
            }
        }

        return 0.0;
    }
};
