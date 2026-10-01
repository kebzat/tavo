<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dopadová stránka s nabídkou pro e-shopy na jednosegmentové adrese
 * (/mereni-pro-eshopy). Sdílí catch-all routu se statickými stránkami,
 * viz PageController.
 */
class EshopOffer extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published' => 'boolean',
        'sections' => 'array',
        'faq' => 'array',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    public function url(): string
    {
        return route('pages.show', $this->slug);
    }

    /** @return list<string> */
    public function introParagraphs(): array
    {
        return self::paragraphs($this->intro);
    }

    /**
     * Sekce s textem rozděleným na odstavce. Sekce bez nadpisu i bez textu
     * se vynechá.
     *
     * @return list<array{title: string, paragraphs: list<string>}>
     */
    public function contentSections(): array
    {
        return collect($this->sections ?? [])
            ->map(fn (array $section) => [
                'title' => trim((string) ($section['title'] ?? '')),
                'paragraphs' => self::paragraphs($section['text'] ?? null),
            ])
            ->filter(fn (array $section) => $section['title'] !== '' || $section['paragraphs'] !== [])
            ->values()
            ->all();
    }

    /**
     * Otázky s vyplněnou otázkou i odpovědí. Jen ty jdou na stránku i do JSON-LD.
     *
     * @return list<array{question: string, answer: string}>
     */
    public function faqItems(): array
    {
        return collect($this->faq ?? [])
            ->map(fn (array $item) => [
                'question' => trim((string) ($item['question'] ?? '')),
                'answer' => trim((string) ($item['answer'] ?? '')),
            ])
            ->filter(fn (array $item) => $item['question'] !== '' && $item['answer'] !== '')
            ->values()
            ->all();
    }

    /** Odstavce oddělené prázdným řádkem. @return list<string> */
    private static function paragraphs(?string $text): array
    {
        return collect(preg_split('/\R\s*\R/u', trim((string) $text)) ?: [])
            ->map(fn (string $paragraph) => trim($paragraph))
            ->filter()
            ->values()
            ->all();
    }
}
