<?php

namespace App\Models;

use App\Enums\ChecklistItemStatus;
use App\Enums\ChecklistPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $guarded = [];

    public function section(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class, 'checklist_section_id');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    /**
     * Přepnutí ze sdílené stránky. Klient tam má jen zaškrtnuto a nezaškrtnuto,
     * takže stavy „Probíhá" a „Neřeší se" (ty se nastavují v administraci)
     * se chovají jako nezaškrtnuté a klik je překlopí na hotovo.
     */
    public function toggleDone(): ChecklistItemStatus
    {
        $this->status = $this->status === ChecklistItemStatus::Done
            ? ChecklistItemStatus::Todo
            : ChecklistItemStatus::Done;

        $this->save();

        return $this->status;
    }

    /**
     * Sloupec checklist_id je odvozený od sekce, ale zapisuje se natvrdo.
     * Doplníme ho sami, ať na něj nemusí myslet každé volání create().
     */
    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            if (! $item->checklist_id && $item->checklist_section_id) {
                $item->checklist_id = ChecklistSection::find($item->checklist_section_id)
                    ?->category?->checklist_id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => ChecklistItemStatus::class,
            'priority' => ChecklistPriority::class,
        ];
    }
}
