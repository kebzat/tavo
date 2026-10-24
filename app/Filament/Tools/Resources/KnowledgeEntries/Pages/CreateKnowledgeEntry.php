<?php

namespace App\Filament\Tools\Resources\KnowledgeEntries\Pages;

use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Models\KnowledgeEntry;
use Filament\Resources\Pages\CreateRecord;

/** Poznatek napsaný člověkem platí hned, schvalovat ho nemá kdo jiný. */
class CreateKnowledgeEntry extends CreateRecord
{
    protected static string $resource = KnowledgeEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            'client_id' => $data['layer'] === KnowledgeEntry::LAYER_CLIENT ? ($data['client_id'] ?? null) : null,
            'status' => KnowledgeEntry::STATUS_APPROVED,
            'origin_type' => 'rucne',
            'proposed_by' => KnowledgeEntryResource::reviewer(),
            'reviewed_by' => KnowledgeEntryResource::reviewer(),
            'reviewed_at' => now(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
