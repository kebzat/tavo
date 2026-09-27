<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pavel i Tom mají devět let praxe, web ale u Pavla pořád psal „osm let"
 * (medailonek, stránka Reklama a marketing včetně meta popisku).
 *
 * Nahrazuje jen přesné obraty o letech praxe, zbytek textu zůstává.
 */
return new class extends Migration
{
    private const FIXES = [
        'Osm let výkonnostního marketingu' => 'Devět let výkonnostního marketingu',
        'Osm let dělá výkonnostní marketing' => 'Devět let dělá výkonnostní marketing',
        'dělá výkonnostní reklamu osm let' => 'dělá výkonnostní reklamu devět let',
        'Osm let praxe' => 'Devět let praxe',
    ];

    public function up(): void
    {
        DB::table('founders')->orderBy('id')->each(function (object $row): void {
            $bio = strtr((string) $row->bio, self::FIXES);

            if ($bio !== (string) $row->bio) {
                DB::table('founders')->where('id', $row->id)->update(['bio' => $bio]);
            }
        });

        DB::table('services')->where('slug', 'reklama-a-marketing')->orderBy('id')->each(function (object $row): void {
            $changes = [];

            foreach (['excerpt', 'hero_perex', 'seo_description'] as $column) {
                $fixed = strtr((string) $row->{$column}, self::FIXES);

                if ($row->{$column} !== null && $fixed !== $row->{$column}) {
                    $changes[$column] = $fixed;
                }
            }

            if ($changes !== []) {
                DB::table('services')->where('id', $row->id)->update($changes);
            }
        });
    }

    public function down(): void
    {
        // Zpátky na osm let nechceme.
    }
};
