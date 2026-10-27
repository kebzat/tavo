<?php

namespace App\Support\Knowledge;

use App\Models\KnowledgeSource;

/**
 * Uložení podkladů z formuláře (Archiv a podklady, nahrání u konverzace
 * s agentem). S učením je rovnou pošle knihovníkovi, jinak je jen uloží
 * do archivu. Viz docs/MOZEK.md, Archiv klienta.
 */
class Archive
{
    public function __construct(private readonly BrainAgent $brain) {}

    /** @param  array<string, mixed>  $data  pole z KnowledgeSourceResource::uploadFields() */
    public function store(array $data, ?string $author): KnowledgeSource
    {
        $names = $data['file_names'] ?? [];
        $learn = (bool) ($data['learn'] ?? true);
        $source = KnowledgeSource::create([
            'title' => $data['title'],
            'client_id' => $data['client_id'] ?? null,
            'skill_id' => $data['skill_id'] ?? null,
            'category' => $data['category'] ?? null,
            'document_date' => $data['document_date'] ?? null,
            'learn' => $learn,
            'status' => $learn ? KnowledgeSource::STATUS_QUEUED : KnowledgeSource::STATUS_STORED,
            'body' => filled($data['body'] ?? null) ? $data['body'] : null,
            'files' => collect($data['file_paths'] ?? [])->values()->map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)])->all() ?: null,
            'note' => $data['note'] ?? null,
            'author' => $author,
        ]);

        if ($learn) {
            $this->brain->process($source);
        }

        return $source->refresh();
    }

    /** Hláška po uložení (stejná v archivu i u konverzace). */
    public static function storedTitle(KnowledgeSource $source): string
    {
        return match (true) {
            $source->isFailed() => 'Podklady jsou uložené, ale agenta se nepodařilo spustit',
            $source->isStored() => 'Uloženo do archivu',
            default => 'Uloženo, agent podklady čte',
        };
    }
}
