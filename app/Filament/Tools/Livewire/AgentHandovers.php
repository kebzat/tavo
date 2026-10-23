<?php

namespace App\Filament\Tools\Livewire;

use App\Models\AgentPublication;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use App\Support\Agent\GitHubError;
use App\Support\Agent\Publisher;
use App\Support\Agent\SiteRestorer;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Hotová práce agenta na stránce projektu. Podle typu projektu:
 *
 * - git: zkontrolovat (snímky, popis) a zveřejnit na web jedním tlačítkem,
 *   průběh, poslední zveřejnění jde vrátit ({@see Publisher}),
 * - server (živý web): co agent změnil, „V pořádku“, nebo vrátit web do stavu
 *   před změnou ({@see SiteRestorer}),
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
            'icon' => 'heroicon-o-eye',
            'heading' => 'Co agent změnil',
            'description' => 'Na živém webu je to už vidět. Zkontrolujte snímky, a když něco nesedí, vraťte web do stavu před změnou.',
            'empty' => 'Teď tu nic není. Až agent na webu něco změní, objeví se to tady i se snímky.',
            'history' => 'Dřív vráceno',
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

        // Nahoře průběh (i hodinu po dokončení, ať je vidět výsledek), dole historie.
        [$active, $history] = collect($recent)->partition(fn (AgentPublication $p): bool => $p->status->isRunning() || $p->finished_at?->gt(now()->subHour()));

        return view('filament.tools.partials.agent-handovers', [
            'copy' => self::COPY[$this->type] ?? self::COPY['git'],
            'handovers' => $this->handovers(),
            'error' => $this->error,
            'active' => $active->values(),
            'history' => $history->values(),
            'running' => $active->contains(fn (AgentPublication $p): bool => $p->status->isRunning()),
            'configured' => $this->type !== 'git' || $publisher->configured(),
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
                    ? array_map(fn (array $item): array => $item + ['codes' => [], 'since' => null], app(Publisher::class)->handovers($this->project))
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
        return collect(['discard', 'restoreSite', 'review', 'publish'])
            ->map(fn (string $name): Action => ($this->{$name.'Action'})(['name' => $item['name'], 'sha' => $item['sha']]))
            ->filter(fn (Action $action): bool => $action->isVisible())
            ->values()
            ->all();
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
                'server' => 'Tohle agent na webu změnil a vyzkoušel. Když něco nesedí, napište mu do terminálu, nebo web vraťte.',
                'prazdny' => 'Texty a kódy od agenta. Zkopírujte je tlačítkem a vložte, kam říká popis.',
                default => 'Takhle to agent udělal a vyzkoušel. Když sedí, zveřejněte. Když ne, napište mu do terminálu, co upravit.',
            })
            ->modalContent(fn (array $arguments): ?View => ($item = $this->find($arguments))
                ? view('filament.tools.partials.agent-handover-review', ['item' => $item, 'component' => $this, 'body' => self::markdown($item['description'])])
                : null)
            ->modalSubmitActionLabel(match ($this->type) {
                'server' => 'V pořádku',
                'prazdny' => 'Vloženo, hotovo',
                default => 'Zveřejnit na web',
            })
            ->modalSubmitAction(fn (Action $action) => $action->visible($this->type !== 'git' || app(Publisher::class)->configured()))
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
            ->disabled(fn (): bool => ! app(Publisher::class)->configured())
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedRocketLaunch)
            ->modalHeading(fn (array $arguments): string => 'Zveřejnit „'.($this->find($arguments)['title'] ?? '').'“?')
            ->modalDescription(fn (array $arguments): string => (($this->find($arguments)['sensitive'] ?? []) !== []
                ? 'Pozor, úprava mění i nastavení serveru (soubory v .github/ nebo infra/). '
                : '').'Nejdřív proběhnou testy. Když projdou, úprava se sama nasadí na web, obvykle do 10 minut. Kdyby se něco nepovedlo, jde vrátit.')
            ->modalSubmitActionLabel('Zveřejnit')
            ->action(fn (array $arguments) => $this->publish($arguments));
    }

    /** Zahodit (git), V pořádku (živý web), Hotovo (podklady): předání zmizí ze seznamu. */
    public function discardAction(): Action
    {
        return Action::make('discard')
            ->label(match ($this->type) {
                'server' => 'V pořádku',
                'prazdny' => 'Hotovo',
                default => 'Zahodit',
            })
            ->icon(match ($this->type) {
                'git' => Heroicon::OutlinedTrash,
                default => Heroicon::OutlinedCheck,
            })
            ->color('gray')
            ->link()
            ->size('sm')
            ->extraAttributes(['class' => 'whitespace-nowrap'])
            ->requiresConfirmation()
            ->modalHeading(match ($this->type) {
                'server' => 'Změna je v pořádku?',
                'prazdny' => 'Vloženo do administrace?',
                default => 'Zahodit hotovou práci?',
            })
            ->modalDescription(match ($this->type) {
                'server' => 'Zmizí ze seznamu, na webu zůstane. Vrátit ji pak jde jen přes Toma (agent vratit).',
                'prazdny' => 'Podklady zmizí ze seznamu.',
                default => 'Úprava zmizí z tohoto seznamu a na web se nedostane. Agent ji má dál u sebe, kdyby byla potřeba.',
            })
            ->modalSubmitActionLabel(match ($this->type) {
                'server' => 'V pořádku',
                'prazdny' => 'Hotovo',
                default => 'Zahodit',
            })
            ->modalSubmitAction(fn (Action $action) => $action->color($this->type === 'git' ? 'danger' : 'primary'))
            ->action(fn (array $arguments) => $this->acknowledge($arguments));
    }

    /** Živý web: vrátit do stavu z doby, kdy agent začal. Databáze jen na přání. */
    public function restoreSiteAction(): Action
    {
        return Action::make('restoreSite')
            ->label('Vrátit')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->size('sm')
            ->visible(fn (): bool => $this->type === 'server')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalHeading(fn (array $arguments): string => 'Vrátit „'.($this->find($arguments)['title'] ?? '').'“?')
            ->modalDescription(fn (array $arguments): string => 'Web se vrátí do stavu z '.$this->sinceLabel($this->find($arguments)).', kdy agent začal. Současný stav se předtím uloží, takže i vrácení jde vrátit.')
            ->schema([
                Toggle::make('database')
                    ->label('Vrátit i databázi')
                    ->helperText('Smaže všechno, co se v databázi od té doby změnilo, i objednávky, zprávy z formulářů a komentáře zákazníků. Zapněte jen, když agent měnil obsah v administraci webu.'),
            ])
            ->modalSubmitActionLabel('Vrátit web')
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->action(function (array $data, array $arguments): void {
                $item = $this->find($arguments);

                if ($item === null) {
                    return;
                }

                try {
                    app(SiteRestorer::class)->start($this->project, $item['raw'], (bool) ($data['database'] ?? false), auth()->user());
                } catch (AgentUnavailable $e) {
                    Notification::make()->danger()->title('Vrátit nejde')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Web se vrací')->body('Trvá to chvilku, průběh je vidět tady na stránce.')->send();
            });
    }

    /** „23. 10. v 9:41“ */
    public function sinceLabel(?array $item): string
    {
        return isset($item['since']) ? Carbon::createFromTimestamp($item['since'])->timezone('Europe/Prague')->format('j. n. \v G:i') : 'doby';
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
            ->modalDescription('Web se vrátí do stavu před touhle úpravou. Jde to stejnou cestou jako zveřejnění: testy, pak nasazení, obvykle do 10 minut.')
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
     * Předání živého webu nebo projektu bez kódu ve stejném tvaru jako git.
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
            'since' => $item['zacatek'] ?? null,
            'time' => $item['cas'],
            'commits' => [],
            'files' => 0,
            'sensitive' => [],
            'on_github' => true,
            'raw' => $item,
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
