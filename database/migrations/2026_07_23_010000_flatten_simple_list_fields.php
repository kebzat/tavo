<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jednoduché seznamy (štítky, odrážky, body rolí) se v administraci editují přes
 * „simple" repeater, který pracuje s plochým polem řetězců. Původní seed je uložil
 * jako pole objektů `[{"text": "…"}]`, takže se v administraci zobrazovalo
 * `[object Object]`. Tahle migrace data narovná.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $columns = [
        'case_studies' => ['tags', 'problem_points', 'marketing_items', 'dev_items'],
        'services' => ['target_groups'],
        'founders' => ['tags'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                $this->convert($table, $column, fn (array $item) => $item['text'] ?? reset($item));
            }
        }

        $this->convertSetting('home.problem_points', fn (array $item) => $item['text'] ?? reset($item));
        $this->convertSetting('contact.lead_recipients', fn (array $item) => $item['email'] ?? reset($item));
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                $this->convertBack($table, $column, 'text');
            }
        }

        $this->convertSettingBack('home.problem_points', 'text');
        $this->convertSettingBack('contact.lead_recipients', 'email');
    }

    private function convert(string $table, string $column, callable $extract): void
    {
        DB::table($table)->select('id', $column)->orderBy('id')->each(function ($row) use ($table, $column, $extract) {
            $value = json_decode($row->{$column} ?? 'null', true);

            if (! is_array($value) || $value === []) {
                return;
            }

            $flat = array_map(fn ($item) => is_array($item) ? $extract($item) : $item, $value);

            DB::table($table)->where('id', $row->id)->update([$column => json_encode(array_values($flat))]);
        });
    }

    private function convertBack(string $table, string $column, string $key): void
    {
        DB::table($table)->select('id', $column)->orderBy('id')->each(function ($row) use ($table, $column, $key) {
            $value = json_decode($row->{$column} ?? 'null', true);

            if (! is_array($value) || $value === []) {
                return;
            }

            $nested = array_map(fn ($item) => is_array($item) ? $item : [$key => $item], $value);

            DB::table($table)->where('id', $row->id)->update([$column => json_encode(array_values($nested))]);
        });
    }

    private function convertSetting(string $key, callable $extract): void
    {
        [$group, $name] = explode('.', $key);

        $row = DB::table('settings')->where('group', $group)->where('name', $name)->first();

        if (! $row) {
            return;
        }

        $value = json_decode($row->payload, true);

        if (! is_array($value) || $value === []) {
            return;
        }

        $flat = array_map(fn ($item) => is_array($item) ? $extract($item) : $item, $value);

        DB::table('settings')->where('id', $row->id)->update(['payload' => json_encode(array_values($flat))]);
    }

    private function convertSettingBack(string $key, string $field): void
    {
        [$group, $name] = explode('.', $key);

        $row = DB::table('settings')->where('group', $group)->where('name', $name)->first();

        if (! $row) {
            return;
        }

        $value = json_decode($row->payload, true);

        if (! is_array($value) || $value === []) {
            return;
        }

        $nested = array_map(fn ($item) => is_array($item) ? $item : [$field => $item], $value);

        DB::table('settings')->where('id', $row->id)->update(['payload' => json_encode(array_values($nested))]);
    }
};
