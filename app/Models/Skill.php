<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Skill TAVEO: specialista na jednu oblast (Meta Ads, copywriting, onboarding…).
 * Má pokyny, jak pracuje, svého agenta (projekt bez kódu, kde se s ním
 * probírá a učí) a v mozku vlastní poznatky a archiv. Agenti klientů ho
 * volají jako `/slug` a dostanou ho přes `taveo skill`. Viz docs/MOZEK.md, Skilly.
 */
class Skill extends Model
{
    protected $guarded = [];

    /** Text v SKILL.md, podle kterého `taveo skilly-sync` pozná, že soubor smí přepsat. */
    public const GENERATED_MARK = '<!-- taveo skilly-sync -->';

    /** @return array<string, string> skupiny v pořadí, jak je ukazují Projekty */
    public static function categories(): array
    {
        return [
            'strategie' => 'Strategie a základ',
            'obsah' => 'Obsah',
            'reklama' => 'Reklama',
            'web' => 'Web a konverze',
            'data' => 'Data',
            'provoz' => 'Agenturní provoz',
        ];
    }

    /** @return array<string, string> ikona skupiny (Heroicon) */
    public static function icons(): array
    {
        return [
            'strategie' => 'heroicon-o-map',
            'obsah' => 'heroicon-o-pencil-square',
            'reklama' => 'heroicon-o-megaphone',
            'web' => 'heroicon-o-globe-alt',
            'data' => 'heroicon-o-chart-bar',
            'provoz' => 'heroicon-o-briefcase',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(KnowledgeEntry::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    public function categoryLabel(): string
    {
        return self::categories()[$this->category] ?? $this->category;
    }

    /** Čím se skill zabývá, na kartu: z popisu „Meta Ads TAVEO: struktura kampaní, cílení… Použij, když…“ část za dvojtečkou. */
    public function scope(): string
    {
        $scope = Str::contains($this->description, ': ') ? Str::after($this->description, ': ') : $this->description;

        return Str::ucfirst(Str::before($scope, '. '));
    }

    /** @return Collection<int, Skill> skilly, ze kterých tenhle čerpá, v pořadí z `builds_on` */
    public function buildsOn(): Collection
    {
        $slugs = $this->builds_on ?? [];

        return self::query()->whereIn('slug', $slugs)->get()->sortBy(fn (Skill $skill): int => array_search($skill->slug, $slugs, true))->values();
    }

    /** Pro seznam skillů agentovi (`taveo skilly`, `taveo skilly-sync`). */
    public function toAgent(): array
    {
        return array_filter([
            'slug' => $this->slug,
            'nazev' => $this->name,
            'skupina' => $this->categoryLabel(),
            'popis' => $this->description,
            'projekt' => $this->agent_project,
            'soubor' => $this->skillFile(),
        ], fn ($value): bool => $value !== null);
    }

    /**
     * SKILL.md pro Claude Code (`~/.claude/skills/<slug>/`). Je v něm jen kdy
     * skill použít a jak si ho načíst. Pokyny a know-how jsou v mozku, takže
     * se skill zlepšuje bez přepisování souboru.
     */
    public function skillFile(): string
    {
        $description = str_replace(["\r", "\n"], ' ', $this->description);
        $mark = self::GENERATED_MARK;

        return <<<MD
            ---
            name: {$this->slug}
            description: {$description}
            ---
            {$mark}

            # {$this->name} (skill TAVEO)

            Pokyny a know-how skillu jsou v Mozku TAVEO, ne v tomhle souboru. Než začneš, načti je:

                taveo skill {$this->slug} <slug-klienta>

            (V projektu bez klienta bez slugu.) Dostaneš postup skillu, schválené know-how
            z práce TAVEO pro všechny klienty, poznatky tohoto klienta ke skillu, archiv skillu (case studies, výsledky, vzory)
            a jak se skill učí. Drž se toho. Když si to odporuje s tím, co chce člověk, řekni to.

            Když příkaz selže (skill ještě není zveřejněný nebo nejde spojení), řekni to
            jednou větou a pracuj s tím, co víš, bez vymýšlení čísel a zdrojů.

            ## Učení skillu

            - Co se při práci ukáže (co u klienta fungovalo, oprava od člověka, ověřený postup),
              navrhni jako poznatek skillu: `taveo mozek-navrh klient "Nadpis" <slug> --skill {$this->slug}`
              (zkušenost u klienta) nebo `firma` bez slugu (obecné know-how), text na vstupu.
            - Větší strategii nebo učení skillu probírej v projektu `{$this->projectName()}` (Nástroje → Projekty → Skilly).
            MD;
    }

    public function projectName(): string
    {
        return $this->agent_project ?? ($this->agent_project_request['project'] ?? $this->slug);
    }

    protected function casts(): array
    {
        return [
            'builds_on' => 'array',
            'agent_project_request' => 'array',
        ];
    }
}
