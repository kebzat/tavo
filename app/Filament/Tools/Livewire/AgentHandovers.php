<?php

namespace App\Filament\Tools\Livewire;

use App\Models\AgentPublication;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
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
 * - prazdny (Shoptet, Upgates): hotové texty a kódy ke zkopírování.
 *
 * U živého webu (server) se karta neukazuje: úprav je moc (i z ClickUpu)
 * a na webu jsou hned. Vrátit web jde ručně, `agent vratit` / `agent obnovit`.
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
        'prazdny' => [
            'icon' => 'heroicon-o-clipboard-document-list',
            'heading' => 'Hotové podklady',
            'description' => 'Texty a kódy od agenta. Zkopírujte je, vložte do administrace (Shoptet, Upgates…) a označte jako hotové.',
            'empty' => 'Teď tu nic není. Až agent připraví texty nebo kódy, objeví se tady.',
            'history' => 'Dřív',
        ],
    ];

    public string $project;

    /** git / prazdny (živý web kartu nemá) */
    public string $type = 'git';

    private ?array $loaded = null;

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
     * Tlačítka u jedné hotové práce, jen ta, která k typu projektu patří.
     *
     * @param  array<string, mixed>  $item
     * @return list<Action>
     */
    public function rowActions(array $item): array
    {
        return collect(['discard', 'review', 'publish'])
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
                default => 'Takhle to agent udělal a vyzkoušel. Když sedí, zveřejněte. Když ne, napište mu do terminálu, co upravit.',
            })
            ->modalContent(fn (array $arguments): ?View => ($item = $this->find($arguments))
                ? view('filament.tools.partials.agent-handover-review', ['item' => $item, 'component' => $this, 'body' => self::markdown($item['description'])])
                : null)
            ->modalSubmitActionLabel(match ($this->type) {
                'prazdny' => 'Vloženo, hotovo',
                default => 'Zveřejnit na web',
            })
            ->modalSubmitAction(fn (Action $action) => $action->visible($this->type !== 'git' || $this->instant() || app(Publisher::class)->configured()))
            ->modalCancelActionLabel('Zavřít')
            ->action(fn (array $arguments) => $this->type === 'git' ? $this->publish($arguments) : $this->acknowledge($arguments));
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

    public function revertAction(): Action
    {
        return Action::make('revert')
            ->label('Vrátit')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->size('xs')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Vrátit „'.(AgentPublication::find($arguments['id'] ?? null)?->title ?? '').'“?')
            ->modalDescription(fn (): string => $this->instant()
                ? 'Web se vrátí na verzi před touhle úpravou, za pár sekund. Databáze zůstane, jak je.'
                : 'Web se vrátí do stavu před touhle úpravou. Jde to stejnou cestou jako zveřejnění: testy, pak nasazení, obvykle do 5 minut.')
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
