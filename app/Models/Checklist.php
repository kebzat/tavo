<?php

namespace App\Models;

use App\Enums\ChecklistItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Checklist extends Model
{
    protected $guarded = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(ChecklistCategory::class);
    }

    /** Všechny sekce napříč kategoriemi. Pro souhrnnou tabulku v administraci. */
    public function sections(): HasManyThrough
    {
        return $this->hasManyThrough(ChecklistSection::class, ChecklistCategory::class);
    }

    /**
     * Všechny položky napříč kategoriemi a sekcemi. Jde to díky sloupci
     * checklist_id na položce, viz komentář v migraci.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    public function scopeTemplates(Builder $query): Builder
    {
        return $query->where('is_template', true);
    }

    public function scopeForClients(Builder $query): Builder
    {
        return $query->where('is_template', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order_column')->orderBy('id');
    }

    /**
     * Progres celého checklistu. Když jsou kategorie s položkami už načtené
     * (sdílená stránka), počítá se z nich a nesahá se znovu do databáze.
     *
     * @return array{total: int, done: int, percent: int}
     */
    public function progress(): array
    {
        $items = $this->relationLoaded('categories')
            ? $this->categories->flatMap(fn (ChecklistCategory $category) => $category->sections->flatMap->items)
            : $this->items;

        return self::progressFrom($items);
    }

    /**
     * Společný výpočet pro checklist, kategorii i sekci.
     *
     * @param  Collection<int, ChecklistItem>  $items
     * @return array{total: int, done: int, percent: int}
     */
    public static function progressFrom(Collection $items): array
    {
        $total = $items->count();
        $done = $items->filter(fn (ChecklistItem $item): bool => $item->status->isFinished())->count();

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    /**
     * Vyrobí klientský checklist ze šablony. Struktura, pořadí i vysvětlivky
     * se přenesou, ale stavy se resetují na „Čeká" a interní poznámky se
     * zahodí, protože patří k šabloně, ne k nové zakázce.
     */
    public function duplicateFor(?Client $client, string $name): self
    {
        return DB::transaction(function () use ($client, $name): self {
            $copy = self::create([
                'client_id' => $client?->getKey(),
                'is_template' => false,
                // Klientský checklist zakládáme rovnou sdílený, ať je odkaz
                // po ruce hned. Vypnout se dá v administraci.
                'is_public' => true,
                'name' => $name,
                'intro' => $this->intro,
            ]);

            $this->copyStructureInto($copy);

            return $copy->fresh();
        });
    }

    /**
     * Přelije strukturu do už založeného checklistu. Používá to jednak
     * duplicateFor(), jednak předvyplnění při zakládání v administraci.
     *
     * Cílový checklist musí být prázdný, jinak by se kategorie zdvojily.
     */
    public function copyStructureInto(self $target): void
    {
        DB::transaction(function () use ($target): void {
            $this->categories()
                ->ordered()
                ->with(['sections' => fn ($query) => $query->ordered()->with(['items' => fn ($q) => $q->ordered()])])
                ->each(function (ChecklistCategory $category) use ($target): void {
                    $newCategory = $target->categories()->create([
                        'title' => $category->title,
                        'slug' => $category->slug,
                        'description' => $category->description,
                        'order_column' => $category->order_column,
                    ]);

                    foreach ($category->sections as $section) {
                        $newSection = $newCategory->sections()->create([
                            'title' => $section->title,
                            'description' => $section->description,
                            'order_column' => $section->order_column,
                        ]);

                        $newSection->items()->createMany(
                            $section->items->map(fn (ChecklistItem $item): array => [
                                'checklist_id' => $target->getKey(),
                                'title' => $item->title,
                                'description' => $item->description,
                                'priority' => $item->priority,
                                // Stavy patří k zakázce, ne k šabloně, a interní
                                // poznámky se nekopírují vůbec.
                                'status' => ChecklistItemStatus::Todo,
                                'order_column' => $item->order_column,
                            ])->all()
                        );
                    }
                });
        });
    }

    /** Odkaz pro klienta. Null, dokud sdílení nezapneme, a u šablony vždy. */
    public function publicUrl(): ?string
    {
        if ($this->is_template || ! $this->is_public || ! $this->public_token) {
            return null;
        }

        return route('checklist.show', $this->public_token);
    }

    protected static function booted(): void
    {
        static::saving(function (self $checklist): void {
            // Token přidělíme hned při založení, ať je odkaz připravený
            // dřív, než ho někdo zapne.
            if (! $checklist->public_token) {
                $checklist->public_token = Str::random(40);
            }

            // Šablona je interní podklad. Kdyby se u ní sdílení omylem zaplo,
            // klient by dostal odkaz na obecný seznam bez kontextu.
            if ($checklist->is_template) {
                $checklist->is_public = false;
                $checklist->client_id = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_template' => 'boolean',
            'is_public' => 'boolean',
        ];
    }
}
