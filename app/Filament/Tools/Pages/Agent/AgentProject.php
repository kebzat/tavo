<?php

namespace App\Filament\Tools\Pages\Agent;

use App\Filament\Tools\Resources\Clients\ClientResource;
use App\Models\Client;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * Terminál s Claudem pro jeden projekt a pod ním jeho konverzace.
 *
 * Po otevření se připojí k naposledy běžící konverzaci, a když žádná neběží,
 * rovnou založí novou: dá se hned psát. Uspané konverzace jsou v seznamu
 * pod terminálem, kliknutím se probudí.
 *
 * Nepřipnutá konverzace se po 12 hodinách bez pohybu sama uspí (šetří paměť
 * serveru). Připnutá (trvalá) běží pořád, takže je dostupná i v aplikaci
 * Claude na mobilu.
 *
 * Běžící konverzace = okno tmuxu v kontejneru agenta. Běží dál i po zavření
 * stránky a vidí ji každý přihlášený. Terminál se připojuje WebSocketem
 * přes LiteSpeed (/agent-terminal/) se vstupenkou, která platí minutu.
 */
class AgentProject extends Page
{
    protected static ?string $slug = 'agent/projekt';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.tools.pages.agent.project';

    public string $project;

    /** Otevřená konverzace (ID okna tmuxu, např. @3). V adrese, ať jde poslat odkaz. */
    #[Url(as: 'okno')]
    public ?string $window = null;

    /** Načtená data z jednoho požadavku, ať se API nevolá v jednom vykreslení vícekrát. */
    private ?array $loadedProject = null;

    private ?array $loadedConversations = null;

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

        if ($this->window === null || $this->find($this->window) === null) {
            $this->window = $this->latestRunning();
        }
    }

    public function getTitle(): string
    {
        return $this->client()?->name ?? $this->project;
    }

    public function getSubheading(): ?string
    {
        return match ($this->details()['typ'] ?? null) {
            'server' => 'Živý web na serveru. Agent pracuje na jeho kopii, na web ji pošlete tlačítkem Zveřejnit. Úlohy z ClickUpu jdou rovnou na živý web.',
            'git' => 'Repozitář na GitHubu. Hotovou práci agenta zkontrolujete a zveřejníte na web v kartě Hotová práce.',
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
        if ($this->loadedProject === null && $this->error === null) {
            try {
                $this->loadedProject = app(AgentServer::class)->project($this->project) ?? [];
            } catch (AgentUnavailable $e) {
                $this->error = $e->getMessage();
            }
        }

        return $this->loadedProject ?: null;
    }

    public function error(): ?string
    {
        $this->details();

        return $this->error;
    }

    /** @return list<array<string, mixed>> */
    public function conversations(): array
    {
        if ($this->loadedConversations === null) {
            try {
                $this->loadedConversations = $this->error() === null ? app(AgentServer::class)->conversations($this->project) : [];
            } catch (AgentUnavailable $e) {
                $this->error = $e->getMessage();
                $this->loadedConversations = [];
            }
        }

        return $this->loadedConversations;
    }

    /**
     * Konverzace pro seznam pod terminálem, s popisky pro šablonu.
     *
     * @return list<array<string, mixed>>
     */
    public function conversationItems(): array
    {
        return collect($this->conversations())
            ->map(fn (array $item): array => $item + [
                'selected' => $item['okno'] !== null && $item['okno'] === $this->window,
                'meta' => collect([$item['autor'], AgentProjects::ago($item['aktualizace'])])->filter()->implode(' · '),
                'status' => match (true) {
                    $item['okno'] === null => ['label' => 'Uspaná', 'color' => 'gray'],
                    ! $item['claude'] => ['label' => 'Claude ukončen', 'color' => 'warning'],
                    $item['uloha'] ?? false => ['label' => 'Úloha běží', 'color' => 'warning'],
                    $item['trvala'] ?? false => ['label' => 'Trvalá', 'color' => 'primary'],
                    default => ['label' => 'Běží', 'color' => 'success'],
                },
            ])
            ->all();
    }

    /** Otevřená konverzace (pro lištu nad terminálem). */
    public function current(): ?array
    {
        return collect($this->conversationItems())->firstWhere('selected', true);
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

    /** Terminál po načtení stránky nemá kam se připojit: založit novou konverzaci. */
    public function autoStart(): void
    {
        if ($this->window === null) {
            $this->newConversation();
        }
    }

    public function newConversation(): void
    {
        // Prázdná nová konverzace už je otevřená, druhou netřeba.
        if ($this->find($this->window)['nova'] ?? false) {
            $this->dispatch('agent-window-selected');

            return;
        }

        $this->open(null);
    }

    /** Kliknutí v seznamu: běžící konverzaci otevřít, uspanou probudit. */
    public function select(?string $window, ?string $session = null): void
    {
        if ($window !== null && $this->find($window) !== null) {
            $this->window = $window;
            $this->dispatch('agent-window-selected');

            return;
        }

        if ($session !== null && collect($this->conversations())->contains('session', $session)) {
            $this->open($session);
        }
    }

    /** Volá terminál při každém (opětovném) připojení: vstupenka platí jen minutu. */
    public function terminalUrl(): ?string
    {
        if ($this->window === null || $this->find($this->window) === null) {
            return null;
        }

        return app(AgentServer::class)->terminalUrl($this->project, $this->window, (string) auth()->user()?->name, request()->getHost());
    }

    protected function getHeaderActions(): array
    {
        return [
            // Serverový režim Remote Control: v aplikaci Claude (desktop, web,
            // mobil) jde v tomhle projektu zakládat konverzace, které běží tady.
            Action::make('claudeApp')
                ->label('Otevřít v aplikaci Claude')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): ?string => $this->details()['aplikace'] ?? null, shouldOpenInNewTab: true)
                ->visible(fn (): bool => filled($this->details()['aplikace'] ?? null)),

            Action::make('client')
                ->label('Klient')
                ->icon(Heroicon::OutlinedUser)
                ->color('gray')
                ->url(fn (): ?string => $this->clientUrl())
                ->visible(fn (): bool => $this->clientUrl() !== null),
        ];
    }

    /** Připnout jako trvalou / odepnout. Bez potvrzení, jde vrátit jedním klikem. */
    public function togglePin(string $window): void
    {
        $item = $this->find($window);

        if ($item === null) {
            return;
        }

        try {
            app(AgentServer::class)->pinWindow($this->project, $window, ! ($item['trvala'] ?? false));
        } catch (AgentUnavailable $e) {
            Notification::make()->danger()->title('Nepovedlo se')->body($e->getMessage())->send();

            return;
        }

        $this->loadedConversations = null;
    }

    public function sleepWindowAction(): Action
    {
        return Action::make('sleepWindow')
            ->label('Uspat konverzaci')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Claude v ní přestane běžet a uvolní paměť serveru. Konverzace zůstane uložená, kliknutím na Probudit pokračuje tam, kde skončila. Změny v souborech zůstávají.')
            ->modalSubmitActionLabel('Uspat')
            ->action(function (array $arguments): void {
                $window = (string) ($arguments['window'] ?? '');

                if ($this->find($window) === null) {
                    return;
                }

                try {
                    app(AgentServer::class)->sleepWindow($this->project, $window);
                } catch (AgentUnavailable $e) {
                    Notification::make()->danger()->title('Nepovedlo se')->body($e->getMessage())->send();

                    return;
                }

                $this->loadedConversations = null;

                if ($this->window === $window) {
                    $this->window = $this->latestRunning();
                    $this->dispatch('agent-window-selected');
                }
            });
    }

    private function open(?string $session): void
    {
        $replace = ($this->find($this->window)['nova'] ?? false) ? $this->window : null;

        try {
            $this->window = app(AgentServer::class)->openConversation($this->project, (string) auth()->user()?->name, $session, $replace);
        } catch (AgentUnavailable $e) {
            Notification::make()->danger()->title('Konverzace se neotevřela')->body($e->getMessage())->send();

            return;
        }

        $this->loadedConversations = null;
        $this->dispatch('agent-window-selected');
    }

    /** @return array<string, mixed>|null běžící konverzace podle okna */
    private function find(?string $window): ?array
    {
        return $window === null ? null : collect($this->conversations())->firstWhere('okno', $window);
    }

    private function latestRunning(): ?string
    {
        // Úlohu na pozadí (grafika) neotevírat samo, psaním by se do ní zasáhlo.
        return collect($this->conversations())->whereNotNull('okno')->reject(fn (array $item): bool => $item['uloha'] ?? false)->sortByDesc('aktualizace')->first()['okno'] ?? null;
    }
}
