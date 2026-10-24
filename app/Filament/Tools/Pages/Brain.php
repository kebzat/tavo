<?php

namespace App\Filament\Tools\Pages;

use App\Filament\Tools\Pages\Agent\AgentProject;
use App\Filament\Tools\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Filament\Tools\Resources\KnowledgeSources\KnowledgeSourceResource;
use App\Models\AgentJob;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeSource;
use App\Support\Agent\AgentServer;
use App\Support\Knowledge\BrainAgent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

/**
 * Mozek TAVEO: společná paměť týmu a agent, který z ní odpovídá. Otázky
 * jdou do jedné dlouhé konverzace (navazuje, dokud ji někdo nezačne znovu),
 * průběh jde sledovat v terminálu a zasáhnout. Viz docs/MOZEK.md.
 */
class Brain extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Zeptat se';

    protected static ?string $title = 'Mozek TAVEO';

    protected static string|\UnitEnum|null $navigationGroup = 'Mozek';

    protected static ?string $slug = 'mozek';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.tools.pages.mozek.brain';

    #[Validate('required|string|min:3|max:5000', as: 'otázka')]
    public string $question = '';

    public bool $fresh = false;

    public function getSubheading(): ?string
    {
        return 'Co se tým naučil, na jednom místě. Agenti z mozku čerpají při každé úloze a navrhují do něj nové poznatky. Tady se ho můžete na cokoli zeptat.';
    }

    /** @return array{approved: int, proposed: int, always: int, sources: int, running: int} */
    #[Computed]
    public function stats(): array
    {
        return [
            'approved' => KnowledgeEntry::query()->approved()->count(),
            'proposed' => KnowledgeEntry::query()->proposed()->count(),
            'always' => KnowledgeEntry::query()->approved()->where('always', true)->count(),
            'sources' => KnowledgeSource::query()->count(),
            'running' => KnowledgeSource::query()->whereIn('status', [KnowledgeSource::STATUS_QUEUED, KnowledgeSource::STATUS_RUNNING])->count(),
        ];
    }

    /** @return Collection<string, int> platné poznatky po vrstvách */
    #[Computed]
    public function layers(): Collection
    {
        return KnowledgeEntry::query()->approved()->selectRaw('layer, count(*) as total')->groupBy('layer')->pluck('total', 'layer');
    }

    /**
     * Je projekt mozku u služby agenta? Null: nejde zjistit (služba není
     * nastavená nebo neodpovídá), pak se stránka neptá a nic nenabízí.
     */
    #[Computed]
    public function ready(): ?bool
    {
        $agent = app(AgentServer::class);

        return $agent->configured()
            ? rescue(fn (): bool => $agent->project(BrainAgent::project()) !== null, null, report: false)
            : null;
    }

    /** @return Collection<int, AgentJob> otázky od nejnovější */
    #[Computed]
    public function questions(): Collection
    {
        return AgentJob::query()->where('source', BrainAgent::SOURCE_QUESTION)->latest('id')->limit(15)->get();
    }

    #[Computed]
    public function running(): ?AgentJob
    {
        return $this->questions->first(fn (AgentJob $job): bool => $job->status === AgentJob::STATUS_RUNNING);
    }

    public function ask(): void
    {
        $this->validate();

        if ($this->running) {
            Notification::make()->title('Mozek ještě odpovídá na předchozí otázku')->warning()->send();

            return;
        }

        $job = app(BrainAgent::class)->ask(trim($this->question), auth()->user()?->name ?? 'nástroje', $this->fresh);

        if ($job->status === AgentJob::STATUS_FAILED) {
            Notification::make()->title('Mozek se nepodařilo zeptat')->body($job->error)->danger()->send();
        } else {
            $this->reset('question', 'fresh');
        }

        unset($this->questions, $this->running);
    }

    public function answer(AgentJob $job): ?HtmlString
    {
        return filled($job->result) ? new HtmlString(Str::markdown($job->result, ['html_input' => 'escape', 'allow_unsafe_links' => false])) : null;
    }

    /** Terminál právě běžící otázky (sledovat, po Zasáhnout psát). */
    public function terminalUrl(bool $write = false): ?string
    {
        $session = AgentJob::query()->where('source', BrainAgent::SOURCE_QUESTION)->where('status', AgentJob::STATUS_RUNNING)->latest('id')->value('session');

        if ($session === null) {
            return null;
        }

        $agent = app(AgentServer::class);
        $window = rescue(fn (): ?string => collect($agent->conversations(BrainAgent::project()))->whereNotNull('okno')->firstWhere('session', $session)['okno'] ?? null, null, report: false);

        return $window ? $agent->terminalUrl(BrainAgent::project(), $window, (string) auth()->user()?->name, request()->getHost(), readOnly: ! $write) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setup')
                ->label('Připravit mozek')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->visible(fn (): bool => $this->ready === false)
                ->requiresConfirmation()
                ->modalDescription('Založí u agenta projekt „'.BrainAgent::project().'“ (bez kódu), ve kterém mozek pracuje. Stačí jednou.')
                ->action(function (): void {
                    $result = rescue(fn (): array => app(AgentServer::class)->addProject(BrainAgent::project(), 'prazdny'), fn (\Throwable $e): array => ['ok' => false, 'vystup' => $e->getMessage()], report: false);
                    unset($this->ready);

                    $result['ok']
                        ? Notification::make()->title('Mozek je připravený')->success()->send()
                        : Notification::make()->title('Nepodařilo se')->body(Str::limit($result['vystup'], 500))->danger()->send();
                }),
            Action::make('sources')
                ->label('Nahrát podklady')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->color('gray')
                ->url(KnowledgeSourceResource::getUrl('create')),
            Action::make('conversation')
                ->label('Konverzace s agentem')
                ->icon(Heroicon::OutlinedCommandLine)
                ->color('gray')
                ->visible(fn (): bool => $this->ready === true)
                ->url(fn (): string => AgentProject::getUrl(['project' => BrainAgent::project()])),
        ];
    }

    public function entriesUrl(string $tab): string
    {
        return KnowledgeEntryResource::getUrl('index', ['activeTab' => $tab]);
    }
}
