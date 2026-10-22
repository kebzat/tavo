<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Dotazník na sdíleném odkazu /dotaznik/{slug}. Spravuje se v nástrojích.
 *
 * Otázky jsou v `sections` (sekce → otázky z repeaterů), odpovědi v `answers`
 * pod klíčem otázky. Přejmenovat nebo přesunout otázku tak jde i potom,
 * co na ni někdo odpověděl.
 *
 * Adresa je jméno a šest náhodných znaků jako u přehledu klienta: odpovědi
 * bývají interní, hádat je nejde.
 */
class Questionnaire extends Model
{
    public const TYPES = [
        'long' => 'Delší odpověď',
        'short' => 'Krátká odpověď',
        'single' => 'Jedna z možností',
        'multi' => 'Více možností',
    ];

    /** Delší odpověď víc nepotřebuje a JSON sloupec tím nenafoukne nikdo cizí. */
    private const MAX_LENGTH = 20000;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'answers' => 'array',
            'is_public' => 'boolean',
            'answered_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $questionnaire): void {
            $questionnaire->slug ??= Str::limit(Str::slug($questionnaire->respondent ?: $questionnaire->title), 60, '')
                .'-'.Str::lower(Str::random(6));
        });

        // Otázka přidaná v administraci nese klíč ze skrytého pole. Kdyby
        // chyběl (ruční zásah do dat), odpověď by neměla kam se uložit.
        static::saving(function (self $questionnaire): void {
            $questionnaire->sections = collect($questionnaire->sections ?? [])
                ->map(function (array $section): array {
                    $section['questions'] = collect($section['questions'] ?? [])
                        ->map(fn (array $question): array => ['key' => ($question['key'] ?? null) ?: Str::lower(Str::random(8))] + $question)
                        ->values()
                        ->all();

                    return $section;
                })
                ->values()
                ->all();
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** Odkaz pro toho, kdo odpovídá. Null, dokud sdílení nezapneme. */
    public function publicUrl(): ?string
    {
        return $this->is_public ? $this->previewUrl() : null;
    }

    /** Náhled pro přihlášeného správce, funguje i u nesdíleného dotazníku. */
    public function previewUrl(): string
    {
        return route('questionnaire.show', $this->slug);
    }

    /**
     * Sekce pro šablonu: bez prázdných řádků z repeateru, každá otázka
     * s pořadovým číslem, možnostmi a uloženou odpovědí.
     *
     * @return list<array{title: ?string, intro: ?string, questions: list<array<string, mixed>>}>
     */
    public function sectionItems(): array
    {
        $number = 0;

        return collect($this->sections ?? [])
            ->map(fn (array $section): array => [
                'title' => filled($section['title'] ?? null) ? $section['title'] : null,
                'intro' => filled($section['intro'] ?? null) ? $section['intro'] : null,
                'questions' => collect($section['questions'] ?? [])
                    ->filter(fn (array $question): bool => filled($question['label'] ?? null) && filled($question['key'] ?? null))
                    ->map(function (array $question) use (&$number): array {
                        $type = array_key_exists($question['type'] ?? '', self::TYPES) ? $question['type'] : 'long';

                        return [
                            'number' => ++$number,
                            'key' => $question['key'],
                            'label' => $question['label'],
                            'help' => filled($question['help'] ?? null) ? $question['help'] : null,
                            'type' => $type,
                            'options' => array_values(array_filter($question['options'] ?? [], 'filled')),
                            'answer' => $this->answers[$question['key']] ?? null,
                        ];
                    })
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $section): bool => $section['questions'] !== [])
            ->values()
            ->all();
    }

    /** @return Collection<string, array<string, mixed>> otázky podle klíče */
    public function questionItems(): Collection
    {
        return collect($this->sectionItems())
            ->flatMap(fn (array $section): array => $section['questions'])
            ->keyBy('key');
    }

    /** @return array{answered: int, total: int, percent: int} */
    public function progress(): array
    {
        $questions = $this->questionItems();
        $answered = $questions->filter(fn (array $question): bool => filled($question['answer']))->count();

        return [
            'answered' => $answered,
            'total' => $questions->count(),
            'percent' => $questions->isEmpty() ? 0 : (int) round($answered / $questions->count() * 100),
        ];
    }

    /**
     * Uloží odpovědi z formuláře. Formulář posílá vždy všechna pole, takže
     * to, co v něm chybí (odškrtnuté možnosti, smazaný text), se maže.
     * Klíče, které dotazník nezná, a možnosti mimo nabídku se zahodí.
     *
     * @param  array<mixed>  $input
     */
    public function fillAnswers(array $input): void
    {
        $answers = $this->questionItems()
            ->map(fn (array $question): string|array|null => $this->cleanAnswer($question, $input[$question['key']] ?? null))
            ->filter(fn (string|array|null $answer): bool => filled($answer))
            ->all();

        if ($answers !== ($this->answers ?? [])) {
            $this->answered_at = now();
        }

        $this->answers = $answers;
    }

    /** @param  array<string, mixed>  $question */
    private function cleanAnswer(array $question, mixed $value): string|array|null
    {
        if ($question['type'] === 'multi') {
            return array_values(array_intersect($question['options'], array_filter((array) $value, 'is_string')));
        }

        if (! is_string($value)) {
            return null;
        }

        if ($question['type'] === 'single') {
            return in_array($value, $question['options'], true) ? $value : null;
        }

        return Str::limit(trim($value), self::MAX_LENGTH, '');
    }

    /**
     * Odpovědi jako Markdown: pro Clauda, do poznámek nebo e-mailu.
     * Nezodpovězené otázky zůstávají, ať je vidět, na co odpověď chybí.
     */
    public function toMarkdown(): string
    {
        $lines = ['# '.$this->title, ''];

        if ($this->respondent) {
            $lines[] = 'Odpovídá: '.$this->respondent;
        }

        if ($this->submitted_at) {
            $lines[] = 'Odesláno: '.$this->submitted_at->timezone('Europe/Prague')->format('j. n. Y H:i');
        }

        foreach ($this->sectionItems() as $section) {
            array_push($lines, '', '## '.($section['title'] ?? 'Otázky'));

            foreach ($section['questions'] as $question) {
                array_push($lines, '', '### '.$question['number'].'. '.$question['label'], '');

                $answer = $question['answer'];

                if (blank($answer)) {
                    $lines[] = '_bez odpovědi_';
                } elseif (is_array($answer)) {
                    array_push($lines, ...array_map(fn (string $option): string => '- '.$option, $answer));
                } else {
                    $lines[] = $answer;
                }
            }
        }

        return implode("\n", $lines)."\n";
    }
}
