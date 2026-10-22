<?php

namespace App\Filament\Tools\Pages\Agent;

use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Vlákna jednoho projektu a terminál s Claudem v prohlížeči.
 *
 * Vlákno = okno tmuxu v kontejneru agenta. Běží dál, i když stránku zavřete,
 * a vidí ho každý přihlášený. Terminál se připojuje WebSocketem přes
 * LiteSpeed (/agent-terminal/) s vstupenkou, která platí minutu.
 */
class AgentProject extends Page
{
    protected static ?string $slug = 'agent/projekt';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.tools.pages.agent.project';

    public string $project;

    /** Otevřené vlákno (ID okna tmuxu, např. @3). V adrese, ať jde poslat odkaz. */
    #[Url(as: 'vlakno')]
    public ?string $thread = null;

    /** Načtený projekt z jednoho požadavku, ať se API nevolá v jednom vykreslení vícekrát. */
    private ?array $loaded = null;

    private ?string $error = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/agent/{project}';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'agent.projekt';
    }

    public function mount(string $project): void
    {
        $this->project = $project;

        abort_if($this->details() === null && $this->error === null, 404);

        // Bez vybraného vlákna otevřeme to, ve kterém se pracovalo naposledy.
        if ($this->thread === null || ! $this->threadExists($this->thread)) {
            $this->thread = collect($this->threads())->sortByDesc('aktivita')->first()['id'] ?? null;
        }
    }

    public function getTitle(): string
    {
        return $this->client()?->name ?? $this->project;
    }

    public function getSubheading(): ?string
    {
        $project = $this->details();

        return match ($project['typ'] ?? null) {
            'server' => 'Živý web na serveru. Před každým novým vláknem se uloží snímek souborů a záloha databáze.',
            'git' => 'Repozitář na GitHubu. Agent posílá jen větve agent/…, na web je dostane člověk.',
            'prazdny' => 'Projekt bez kódu: texty, podklady a kódy k ručnímu vložení.',
            default => null,
        };
    }

    /** @return array<string, string> */
    public function getBreadcrumbs(): array
    {
        return [AgentProjects::getUrl() => 'Agent', $this->project];
    }

    /** @return array<string, mixed>|null */
    public function details(): ?array
    {
        if ($this->loaded === null && $this->error === null) {
            try {
                $this->loaded = app(AgentServer::class)->project($this->project) ?? [];
            } catch (AgentUnavailable $e) {
                $this->error = $e->getMessage();
            }
        }

        return $this->loaded ?: null;
    }

    public function error(): ?string
    {
        $this->details();

        return $this->error;
    }

    /** @return list<array<string, mixed>> */
    public function threads(): array
    {
        return $this->details()['vlakna'] ?? [];
    }

    /**
     * Vlákna pro seznam: nejnovější práce nahoře, s popisky pro šablonu.
     *
     * @return list<array<string, mixed>>
     */
    public function threadItems(): array
    {
        return collect($this->threads())
            ->sortByDesc('aktivita')
            ->map(fn (array $thread): array => $thread + [
                'selected' => $thread['id'] === $this->thread,
                'meta' => collect([
                    $thread['autor'],
                    AgentProjects::ago($thread['aktivita']),
                ])->filter()->implode(' · '),
                'status' => $thread['claude'] ? 'Claude běží' : 'Claude skončil, v okně je příkazová řádka',
            ])
            ->values()
            ->all();
    }

    public function client(): ?Client
    {
        return Client::query()->where('agent_project', $this->project)->first();
    }

    public function clientUrl(): ?string
    {
        $client = $this->client();

        return $client ? ClientResource::getUrl('edit', ['record' => $client]) : null;
    }

    public function selectThread(string $id): void
    {
        if (! $this->threadExists($id)) {
            return;
        }

        $this->thread = $id;
        $this->dispatch('agent-thread-selected');
    }

    /** Volá terminál při každém (opětovném) připojení: vstupenka platí jen minutu. */
    public function terminalUrl(): ?string
    {
        if ($this->thread === null || ! $this->threadExists($this->thread)) {
            return null;
        }

        return app(AgentServer::class)->terminalUrl($this->project, $this->thread, (string) auth()->user()?->name, request()->getHost());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newThread')
                ->label('Nové vlákno')
                ->icon(Heroicon::OutlinedPlus)
                ->modalDescription(fn (): ?string => ($this->details()['typ'] ?? null) === 'server'
                    ? 'Před spuštěním se uloží snímek souborů a záloha databáze. Může to pár vteřin trvat.'
                    : null)
                ->schema([
                    TextInput::make('name')
                        ->label('Na čem se bude pracovat')
                        ->placeholder('Banner na Black Friday')
                        ->required()
                        ->maxLength(60),
                    Toggle::make('continue')
                        ->label('Navázat na poslední konverzaci v projektu')
                        ->helperText('Claude načte, kde se minule skončilo. Jinak začne s čistou hlavou, ale paměť projektu (CLAUDE.md) zná vždycky.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $id = app(AgentServer::class)->startThread($this->project, $data['name'], (string) auth()->user()?->name, (bool) ($data['continue'] ?? false));
                    } catch (AgentUnavailable $e) {
                        Notification::make()->danger()->title('Vlákno se nezaložilo')->body($e->getMessage())->send();

                        return;
                    }

                    $this->loaded = null;
                    $this->thread = $id;
                    $this->dispatch('agent-thread-selected');
                }),

            Action::make('client')
                ->label('Klient')
                ->icon(Heroicon::OutlinedUser)
                ->color('gray')
                ->url(fn (): ?string => $this->clientUrl())
                ->visible(fn (): bool => $this->clientUrl() !== null),
        ];
    }

    public function renameThreadAction(): Action
    {
        return Action::make('renameThread')
            ->label('Přejmenovat vlákno')
            ->fillForm(fn (array $arguments): array => ['name' => collect($this->threads())->firstWhere('id', $arguments['thread'] ?? null)['nazev'] ?? ''])
            ->schema([
                TextInput::make('name')->label('Název')->required()->maxLength(60),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->runOnThread($arguments['thread'] ?? '', fn (AgentServer $agent, string $id) => $agent->renameThread($this->project, $id, $data['name']));
            });
    }

    public function closeThreadAction(): Action
    {
        return Action::make('closeThread')
            ->label('Ukončit vlákno')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Okno s Claudem se zavře. Konverzace zůstane uložená a v novém vlákně na ni jde navázat. Změny v souborech zůstávají.')
            ->modalSubmitActionLabel('Ukončit')
            ->action(function (array $arguments): void {
                $id = $arguments['thread'] ?? '';

                $closed = $this->runOnThread($id, fn (AgentServer $agent, string $id) => $agent->closeThread($this->project, $id));

                if ($closed && $this->thread === $id) {
                    $this->thread = collect($this->threads())->reject(fn (array $t): bool => $t['id'] === $id)->sortByDesc('aktivita')->first()['id'] ?? null;
                    $this->dispatch('agent-thread-selected');
                }
            });
    }

    private function runOnThread(string $id, callable $callback): bool
    {
        if (! $this->threadExists($id)) {
            return false;
        }

        try {
            $callback(app(AgentServer::class), $id);
        } catch (AgentUnavailable $e) {
            Notification::make()->danger()->title('Nepovedlo se')->body($e->getMessage())->send();

            return false;
        } finally {
            $this->loaded = null;
        }

        return true;
    }

    private function threadExists(string $id): bool
    {
        return collect($this->threads())->contains('id', $id);
    }
}
