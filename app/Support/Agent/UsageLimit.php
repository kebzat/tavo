<?php

namespace App\Support\Agent;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Jeden limit předplatného Claude: relace (5 h), týden, nebo týden pro jeden
 * model. Procento je vyčerpaná část, `resetsAt` chvíle, kdy se okno vynuluje.
 */
final class UsageLimit
{
    /** Délka okna v hodinách, podle ní se počítá, kolik okna už uběhlo. */
    private const WINDOW_HOURS = ['session' => 5, 'weekly_all' => 168, 'weekly_scoped' => 168];

    public function __construct(
        public readonly string $kind,
        public readonly int $percent,
        public readonly ?CarbonImmutable $resetsAt,
        public readonly ?string $model = null,
    ) {}

    /** @param  array<string, mixed>  $limit  Položka seznamu `limits` z Anthropicu. */
    public static function fromLimit(array $limit): ?self
    {
        $kind = $limit['kind'] ?? null;

        if (! isset(self::WINDOW_HOURS[$kind]) || ! is_numeric($limit['percent'] ?? null)) {
            return null;
        }

        return new self($kind, (int) round($limit['percent']), self::date($limit['resets_at'] ?? null), $limit['scope']['model']['display_name'] ?? null);
    }

    /** @param  array<string, mixed>|null  $window  Starší tvar: `five_hour` / `seven_day`. */
    public static function fromWindow(string $kind, ?array $window): ?self
    {
        if (! is_numeric($window['utilization'] ?? null)) {
            return null;
        }

        return new self($kind, (int) round($window['utilization']), self::date($window['resets_at'] ?? null));
    }

    public function label(): string
    {
        return match ($this->kind) {
            'session' => 'Relace',
            'weekly_all' => 'Týden',
            default => 'Týden · '.($this->model ?? 'jeden model'),
        };
    }

    /** Krátký popisek do hlavičky. */
    public function shortLabel(): string
    {
        return $this->kind === 'session' ? '5 h' : '7 dní';
    }

    /** Pruh nepřeteče, i když Anthropic hlásí přes 100 % (dokoupené využití). */
    public function bar(): int
    {
        return max(0, min(100, $this->percent));
    }

    /** ok / warning / danger: barva pruhu. */
    public function level(): string
    {
        return match (true) {
            $this->percent >= 90 => 'danger',
            $this->percent >= 75 => 'warning',
            default => 'ok',
        };
    }

    /**
     * Kolik procent okna už uběhlo. Na pruhu je to ryska: když je pruh za ní,
     * čerpá se rychleji, než by limit vydržel do konce okna.
     */
    public function elapsed(?CarbonImmutable $now = null): ?int
    {
        if ($this->resetsAt === null) {
            return null;
        }

        $hours = self::WINDOW_HOURS[$this->kind];
        $left = ($now ?? CarbonImmutable::now())->diffInMinutes($this->resetsAt, false) / 60;

        return (int) round(max(0, min(100, (1 - $left / $hours) * 100)));
    }

    /** „za 1 h 20 min“ do druhého dne, pak „v sobotu 19:00“. Český čas. */
    public function resetLabel(?CarbonImmutable $now = null): ?string
    {
        if ($this->resetsAt === null) {
            return null;
        }

        $now ??= CarbonImmutable::now();
        $minutes = (int) floor($now->diffInSeconds($this->resetsAt, false) / 60);

        if ($minutes < 1) {
            return 'za chvíli';
        }

        if ($minutes < 60) {
            return 'za '.$minutes.' min';
        }

        if ($minutes < 24 * 60) {
            return 'za '.intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '');
        }

        // Anthropic posílá 16:59:59.77, ukázat se má 17:00.
        $at = $this->resetsAt->addSeconds(30)->startOfMinute()->timezone('Europe/Prague');
        $day = ['v neděli', 'v pondělí', 'v úterý', 've středu', 've čtvrtek', 'v pátek', 'v sobotu'][$at->dayOfWeek];

        return $day.' '.$at->format('G:i');
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) ? CarbonImmutable::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
