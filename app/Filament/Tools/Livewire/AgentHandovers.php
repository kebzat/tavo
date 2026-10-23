<?php

namespace App\Filament\Tools\Livewire;

use App\Models\AgentPublication;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use App\Support\Agent\CopyPublisher;
use App\Support\Agent\GitHubError;
use App\Support\Agent\InstantPublisher;
use App\Support\Agent\Publisher;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Hotová práce agenta na stránce projektu. Podle typu projektu:
 *
 * - git: zkontrolovat (snímky, popis) a zveřejnit na web jedním tlačítkem,
 *   průběh, poslední zveřejnění jde vrátit ({@see Publisher}),
 * - server (živý web): agent pracuje v kopii webu, Zveřejnit ji nahraje na
 *   živý web najednou ({@see CopyPublisher}). Úlohy z ClickUpu jdou rovnou
 *   na živý web a v kartě nejsou,
 * - prazdny (Shoptet, Upgates): hotové texty a kódy ke zkopírování.
 *
 * Obnovuje se sama: během zveřejnění každých 10 s (a rovnou ho posouvá),
 * jinak po 30 s, ať je vidět, když agent něco dokončí.
 */
class AgentHandovers extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** Nadpis, popis a prázdný stav karty podle typu projektu. */
    private const COPY = [
        'git' => [
            'icon' => 'heroicon-o-rocket-launch',
            'heading' => 'Hotová práce',
            'description' => 'Co agent dokončil. Zkontrolujte snímky a popis, pak jedním klikem zveřejněte na web.',
            'empty' => 'Teď tu nic nečeká. Až agent dokončí úpravu, objeví se tady i se snímky.',
            'history' => 'Dřív zveřejněno',
        ],
        'server' => [
            'icon' => 'heroicon-o-rocket-launch',
            'heading' => 'Hotová práce',
            'description' => 'Agent pracuje na kopii webu. Zkontrolujte, co udělal, a jedním klikem to nahrajte na živý web.',
            'empty' => 'Teď tu nic nečeká. Až agent v kopii něco dokončí, objeví se to tady i se snímky.',
            'history' => 'Dřív zveřejněno',
        ],
        'prazdny' => [
            'icon' => 'heroicon-o-clipboard-document-list',
            'heading' => 'Hotové podklady',
            'description' => 'Texty a kódy od agenta. Zkopírujte je, vložte do administrace (Shoptet, Upgates…) a označte jako hotové.',
            'empty' => 'Teď tu nic není. Až agent připraví texty nebo kódy, objeví se tady.',
            'history' => 'Dřív',
        ],
    ];

    public string $project;

    /** git / server / prazdny */
    public string $type = 'git';

    private ?array $loaded = null;

    private ?array $copyState = null;

    private ?string $error = null;

    public function render(): View
    {
        $publisher = app(Publisher::class);
        $recent = $publisher->recent($this->project);

        foreach ($recent as $publication) {
            if ($publication->status->isRunning()) {
                $publisher->advance($publication);
                $publication->refresh();
            }
        }

        // Nahoře jen co běží a co skončilo chybou (hodinu), hotové hned dolů do historie.
        [$active, $history] = collect($recent)->partition(fn (AgentPublication $p): bool => $p->status->isRunning()
            || ($p->status->isProblem() && $p->finished_at?->gt(now()->subHour())));

        return view('filament.tools.partials.agent-handovers', [
            'copy' => self::COPY[$this->type] ?? self::COPY['git'],
            'handovers' => $this->handovers(),
            'copyState' => $this->copyState(),
            'error' => $this->error,
            'active' => $active->values(),
            'history' => $history->values(),
            'running' => $active->contains(fn (AgentPublication $p): bool => $p->status->isRunning()),
            'configured' => $this->type !== 'git' || $this->instant() || $publisher->configured(),
            // Vrátit jde poslední zveřejněnou úpravu (ne vrácení samo).
            'revertable' => collect($recent)->first(fn (AgentPublication $p): bool => $p->status->isMerged() && ! $p->isRevert()),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function handovers(): array
    {
        if ($this->loaded === null) {
            try {
                $this->loaded = $this->type === 'git'
                    ? array_map(fn (array $item): array => $item + ['codes' => []], app(Publisher::class)->handovers($this->project))
                    : array_map(self::folderItem(...), app(AgentServer::class)->handovers($this->project));
            } catch (AgentUnavailable $e) {
                $this->error = $e->getMessage();
                $this->loaded = [];
            }
        }

        return $this->loaded;
    }

    /**
     * Kopie živého webu: existuje, změny proti živému, z toho v databázi.
     *
     * @return array{existuje: bool, zmeny: list<array{stav: string, soubor: string}>, migrace: int}|null
     */
    public function copyState(): ?array
    {
        if ($this->type !== 'server') {
            return null;
        }

        $state = $this->copyState ??= rescue(fn (): array => app(AgentServer::class)->copyStatus($this->project), [], report: false);

        return isset($state['existuje'], $state['zmeny']) ? $state : null;
    }

    /**
     * Tlačítka u jedné hotové práce, jen ta, která k typu projektu patří.
     * U živého webu jen kontrola: zveřejňuje se celá kopie najednou.
     *
     * @param  array<string, mixed>  $item
     * @return list<Action>
     */
    public function rowActions(array $item): array
    {
        return collect($this->type === 'server' ? ['review'] : ['discard', 'review', 'publish'])
            ->map(fn (string $name): Action => ($this->{$name.'Action'})(['name' => $item['name'], 'sha' => $item['sha']]))
            ->filter(fn (Action $action): bool => $action->isVisible())
            ->values()
            ->all();
    }

    /** Web na tomhle serveru: zveřejnění rovnou ze serveru, bez GitHubu. */
    public function instant(): bool
    {
        return $this->type === 'git' && InstantPublisher::enabled($this->project);
    }

    /** „3 snímky“, „5 souborů“ */
    public function plural(int $count, string $one, string $few, string $many): string
    {
        return $count.' '.match (true) {
            $count === 1 => $one,
            $count >= 2 && $count <= 4 => $few,
            default => $many,
        };
    }

    public function imageUrl(string $name, string $file): string
    {
        return route('filament.tools.agent.snimek', ['project' => $this->project, 'name' => $name, 'file' => $file]);
    }

    public function reviewAction(): Action
    {
        return Action::make('review')
            ->label('Zkontrolovat')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->size('sm')
            ->slideOver()
            ->modalWidth('3xl')
            ->modalHeading(fn (array $arguments): string => $this->find($arguments)['title'] ?? 'Hotová práce')
            ->modalDescription(match ($this->type) {
                'prazdny' => 'Texty a kódy od agenta. Zkopírujte je tlačítkem a vložte, kam říká popis.',
                'server' => 'Takhle to agent udělal v kopii webu. Zveřejnit nahraje na živý web všechno, co v kopii čeká.',
                default => 'Takhle to agent udělal a vyzkoušel. Když sedí, zveřejněte. Když ne, napište mu do terminálu, co upravit.',
            })
            ->modalContent(fn (array $arguments): ?View => ($item = $this->find($arguments))
                ? view('filament.tools.partials.agent-handover-review', ['item' => $item, 'component' => $this, 'body' => self::markdown($item['description'])])
                : null)
            ->modalSubmitActionLabel(match ($this->type) {
                'prazdny' => 'Vloženo, hotovo',
                'server' => 'Zveřejnit kopii na web',
                default => 'Zveřejnit na web',
            })
            ->modalSubmitAction(fn (Action $action) => $action->visible($this->type !== 'git' || $this->instant() || app(Publisher::class)->configured()))
            ->modalCancelActionLabel('Zavřít')
            ->action(fn (array $arguments) => match ($this->type) {
                'git' => $this->publish($arguments),
                'server' => $this->publishCopy(),
                default => $this->acknowledge($arguments),
            });
    }

    public function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Zveřejnit')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->size('sm')
            ->visible(fn (): bool => $this->type === 'git')
            ->disabled(fn (array $arguments): bool => ! ($this->instant() || app(Publisher::class)->configured()) || ($this->find($arguments)['tests'] ?? null) === 'failed')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedRocketLaunch)
            ->modalHeading(fn (array $arguments): string => 'Zveřejnit „'.($this->find($arguments)['title'] ?? '').'“?')
            ->modalDescription(fn (array $arguments): string => (($this->find($arguments)['sensitive'] ?? []) !== []
                ? 'Pozor, úprava mění i nastavení serveru (soubory v .github/ nebo infra/). '
                : '').match ($this->instant() ? 'instant' : ($this->find($arguments)['tests'] ?? null)) {
                    'instant' => 'Nahraje se rovnou na web, obvykle do 15 sekund.',
                    'passed' => 'Testy už prošly, úprava se hned sloučí a nasadí na web, obvykle do 2 minut.',
                    'running' => 'Testy ještě běží. Jakmile projdou, úprava se sama sloučí a nasadí na web.',
                    default => 'Nejdřív proběhnou testy, pak se úprava sama nasadí na web.',
                }.' Kdyby se něco nepovedlo, jde vrátit.')
            ->modalSubmitActionLabel('Zveřejnit')
            ->action(fn (array $arguments) => $this->publish($arguments));
    }

    /** Zahodit (git), Hotovo (podklady): předání zmizí ze seznamu. */
    public function discardAction(): Action
    {
        return Action::make('discard')
            // U živého webu se zahazuje celá kopie (Zahodit změny nahoře), ne jedna úprava.
            ->visible(fn (): bool => $this->type !== 'server')
            ->label(match ($this->type) {
                'prazdny' => 'Hotovo',
                default => 'Zahodit',
            })
            ->icon($this->type === 'git' ? Heroicon::OutlinedTrash : Heroicon::OutlinedCheck)
            ->color('gray')
            ->link()
            ->size('sm')
            ->extraAttributes(['class' => 'whitespace-nowrap'])
            ->requiresConfirmation()
            ->modalHeading(match ($this->type) {
                'prazdny' => 'Vloženo do administrace?',
                default => 'Zahodit hotovou práci?',
            })
            ->modalDescription(match ($this->type) {
                'prazdny' => 'Podklady zmizí ze seznamu.',
                default => 'Úprava zmizí z tohoto seznamu a na web se nedostane. Agent ji má dál u sebe, kdyby byla potřeba.',
            })
            ->modalSubmitActionLabel(match ($this->type) {
                'prazdny' => 'Hotovo',
                default => 'Zahodit',
            })
            ->modalSubmitAction(fn (Action $action) => $action->color($this->type === 'git' ? 'danger' : 'primary'))
            ->action(fn (array $arguments) => $this->acknowledge($arguments));
    }

    /** Živý web: celá kopie na živý web (snímek, záloha, soubory, migrace). */
    public function publishCopyAction(): Action
    {
        return Action::make('publishCopy')
            ->label('Zveřejnit na web')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->size('sm')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedRocketLaunch)
            ->modalHeading('Zveřejnit kopii na živý web?')
            ->modalDescription(fn (): string => 'Nahraje se všechno, co v kopii čeká ('.$this->plural(count($this->copyState()['zmeny'] ?? []), 'změněný soubor', 'změněné soubory', 'změněných souborů').')'
                .(($this->copyState()['migrace'] ?? 0) > 0 ? ', včetně změn v databázi' : '')
                .'. Před tím se uloží snímek a záloha databáze, obvykle do 20 sekund. Kdyby se něco nepovedlo, jde vrátit.')
            ->modalSubmitActionLabel('Zveřejnit')
            ->action(fn () => $this->publishCopy());
    }

    /** Živý web: kopie zpátky na živý web, nezveřejněné změny pryč. */
    public function discardCopyAction(): Action
    {
        return Action::make('discardCopy')
            ->label('Zahodit změny')
            ->icon(Heroicon::OutlinedTrash)
            ->color('gray')
            ->link()
            ->size('sm')
            ->extraAttributes(['class' => 'whitespace-nowrap'])
            ->requiresConfirmation()
            ->modalHeading('Zahodit změny v kopii?')
            ->modalDescription('Kopie se vrátí na stav živého webu, i její databáze. Na živém webu se nic nezmění.')
            ->modalSubmitActionLabel('Zahodit')
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->action(function (): void {
                try {
                    app(AgentServer::class)->startCopyJob($this->project, 'zahodit');
                } catch (AgentUnavailable $e) {
                    Notification::make()->danger()->title('Nepovedlo se')->body($e->getMessage())->send();

                    return;
                }

                $this->copyState = null;
                Notification::make()->success()->title('Kopie se vrací na živý web')->body('Trvá to pár vteřin.')->send();
            });
    }

    /** Živý web bez kopie: založit ji (jinak se založí sama s první konverzací). */
    public function prepareCopyAction(): Action
    {
        return Action::make('prepareCopy')
            ->label('Připravit kopii')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->size('sm')
            ->action(function (): void {
                try {
                    app(AgentServer::class)->startCopyJob($this->project, 'obnovit');
                } catch (AgentUnavailable $e) {
                    Notification::make()->danger()->title('Nepovedlo se')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Kopie se připravuje')->body('Za chvilku je hotová.')->send();
            });
    }

    public function revertAction(): Action
    {
        return Action::make('revert')
            ->label('Vrátit')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->size('xs')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Vrátit „'.(AgentPublication::find($arguments['id'] ?? null)?->title ?? '').'“?')
            ->modalDescription(fn (): string => $this->type === 'server'
                ? 'Soubory živého webu se vrátí do stavu před tímhle zveřejněním, za pár vteřin. Databáze zůstane, jak je (změny z migrací se nevracejí).'
                : ($this->instant()
                ? 'Web se vrátí na verzi před touhle úpravou, za pár sekund. Databáze zůstane, jak je.'
                : 'Web se vrátí do stavu před touhle úpravou. Jde to stejnou cestou jako zveřejnění: testy, pak nasazení, obvykle do 5 minut.'))
            ->modalSubmitActionLabel('Vrátit')
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->action(function (array $arguments): void {
                $publication = AgentPublication::query()->where('project', $this->project)->find($arguments['id'] ?? null);

                if ($publication === null) {
                    return;
                }

                try {
                    app(Publisher::class)->revert($publication, auth()->user());
                } catch (GitHubError $e) {
                    Notification::make()->danger()->title('Vrátit nejde')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Vrací se')->body('Průběh je vidět tady na stránce.')->send();
            });
    }

    /** @param  array<string, mixed>  $arguments */
    private function publish(array $arguments): void
    {
        $item = $this->find($arguments);

        if ($item === null) {
            Notification::make()->warning()->title('Úprava už v seznamu není')->send();

            return;
        }

        try {
            app(Publisher::class)->start($this->project, $item['name'], $item['sha'], auth()->user());
        } catch (GitHubError|AgentUnavailable $e) {
            Notification::make()->danger()->title('Zveřejnění se nespustilo')->body($e->getMessage())->send();

            return;
        }

        $this->loaded = null;
        Notification::make()->success()->title('Zveřejňuje se')->body('Průběh je vidět tady na stránce. Klidně ji zavřete, dokončí se samo.')->send();
    }

    private function publishCopy(): void
    {
        $state = $this->copyState();

        if (($state['zmeny'] ?? []) === []) {
            Notification::make()->warning()->title('V kopii nejsou žádné změny')->send();

            return;
        }

        try {
            app(CopyPublisher::class)->start($this->project, array_column($this->handovers(), 'title'), count($state['zmeny']), auth()->user());
        } catch (AgentUnavailable $e) {
            Notification::make()->danger()->title('Zveřejnění se nespustilo')->body($e->getMessage())->send();

            return;
        }

        $this->loaded = null;
        $this->copyState = null;
        Notification::make()->success()->title('Zveřejňuje se')->body('Za pár vteřin je na webu, průběh je vidět tady.')->send();
    }

    /** @param  array<string, mixed>  $arguments */
    private function acknowledge(array $arguments): void
    {
        $item = $this->find($arguments);

        if ($item === null) {
            return;
        }

        try {
            app(AgentServer::class)->discardHandover($this->project, $item['name'], $item['sha']);
        } catch (AgentUnavailable $e) {
            Notification::make()->danger()->title('Nepovedlo se')->body($e->getMessage())->send();

            return;
        }

        $this->loaded = null;
        Notification::make()->success()->title($this->type === 'git' ? 'Zahozeno' : 'Hotovo')->send();
    }

    /**
     * Předání projektu bez kódu ve stejném tvaru jako git.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function folderItem(array $item): array
    {
        return [
            'name' => $item['jmeno'],
            'branch' => null,
            'sha' => null,
            'title' => Str::limit($item['nadpis'], 180, '…'),
            'description' => $item['popis'],
            'images' => $item['snimky'],
            'codes' => $item['kody'] ?? [],
            'time' => $item['cas'],
            'commits' => [],
            'files' => 0,
            'sensitive' => [],
            'on_github' => true,
        ];
    }

    /**
     * Hotová práce podle jména větve a commitu. Commit z tlačítka musí sedět:
     * zveřejní se jen to, co člověk viděl.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    private function find(array $arguments): ?array
    {
        return collect($this->handovers())->first(fn (array $item): bool => $item['name'] === ($arguments['name'] ?? null) && $item['sha'] === ($arguments['sha'] ?? null));
    }

    /**
     * Popis od agenta je Markdown. HTML z něj neprojde. Úvodní nadpis je
     * už v hlavičce panelu, podruhé se neukazuje.
     */
    private static function markdown(?string $text): ?HtmlString
    {
        $text = trim((string) preg_replace('/\A\s*#\s+.+\R?/', '', (string) $text));

        return $text !== '' ? new HtmlString(Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false])) : null;
    }
}
