<?php

namespace App\Filament\Tools\Livewire;

use App\Enums\PublicationStatus;
use App\Models\AgentPublication;
use App\Support\Agent\AgentUnavailable;
use App\Support\Agent\GitHubError;
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
 * Hotová práce agenta na stránce git projektu: zkontrolovat (snímky, popis)
 * a zveřejnit na web jedním tlačítkem. Pod tím průběh a poslední zveřejnění
 * s možností vrátit. Logika je v {@see Publisher}.
 *
 * Obnovuje se sama: během zveřejnění každých 10 s (a rovnou ho posouvá),
 * jinak po 30 s, ať je vidět, když agent něco dokončí.
 */
class AgentHandovers extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public string $project;

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
            'handovers' => $this->handovers(),
            'error' => $this->error,
            'active' => $active->values(),
            'history' => $history->values(),
            'running' => $active->contains(fn (AgentPublication $p): bool => $p->status->isRunning()),
            'configured' => $publisher->configured(),
            // Vrátit jde poslední úpravu, která je na webu (ne vrácení samo).
            'revertable' => collect($recent)->first(fn (AgentPublication $p): bool => $p->status === PublicationStatus::Done && ! $p->isRevert()),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function handovers(): array
    {
        if ($this->loaded === null) {
            try {
                $this->loaded = app(Publisher::class)->handovers($this->project);
            } catch (AgentUnavailable $e) {
                $this->error = $e->getMessage();
                $this->loaded = [];
            }
        }

        return $this->loaded;
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
            ->modalDescription('Takhle to agent udělal a vyzkoušel. Když sedí, zveřejněte. Když ne, napište mu do terminálu, co upravit.')
            ->modalContent(fn (array $arguments): ?View => ($item = $this->find($arguments))
                ? view('filament.tools.partials.agent-handover-review', ['item' => $item, 'component' => $this, 'body' => self::markdown($item['description'])])
                : null)
            ->modalSubmitActionLabel('Zveřejnit na web')
            ->modalSubmitAction(fn (Action $action) => $action->visible(app(Publisher::class)->configured()))
            ->modalCancelActionLabel('Zavřít')
            ->action(fn (array $arguments) => $this->publish($arguments));
    }

    public function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Zveřejnit')
            ->icon(Heroicon::OutlinedRocketLaunch)
            ->size('sm')
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

    public function discardAction(): Action
    {
        return Action::make('discard')
            ->label('Zahodit')
            ->icon(Heroicon::OutlinedTrash)
            ->color('gray')
            ->link()
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Zahodit hotovou práci?')
            ->modalDescription('Úprava zmizí z tohoto seznamu a na web se nedostane. Agent ji má dál u sebe, kdyby byla potřeba.')
            ->modalSubmitActionLabel('Zahodit')
            ->modalSubmitAction(fn (Action $action) => $action->color('danger'))
            ->action(function (array $arguments): void {
                $item = $this->find($arguments);

                if ($item === null) {
                    return;
                }

                try {
                    app(Publisher::class)->discard($this->project, $item['name'], $item['sha']);
                } catch (AgentUnavailable $e) {
                    Notification::make()->danger()->title('Nepovedlo se')->body($e->getMessage())->send();

                    return;
                }

                $this->loaded = null;
                Notification::make()->success()->title('Zahozeno')->send();
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
