<?php

namespace App\Filament\Tools\Pages\Agent;

use App\Models\Client;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use App\Support\Agent\ClientProjects;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;

/**
 * Projekty agenta na serveru (docs/AGENT.md). Každý má vlastní konverzace
 * s Claudem, otevírají se v detailu projektu.
 */
class AgentProjects extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?string $navigationLabel = 'Agent';

    protected static ?string $title = 'Agent';

    protected static ?string $slug = 'agent';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.tools.pages.agent.projects';

    private const TYPES = [
        'server' => ['label' => 'živý web', 'color' => 'warning'],
        'git' => ['label' => 'GitHub', 'color' => 'info'],
        'prazdny' => ['label' => 'bez kódu', 'color' => 'gray'],
    ];

    /** Kolik projektů na stránku: 10, 20, 50 nebo all. */
    public const PER_PAGE = ['10' => '10', '20' => '20', '50' => '50', 'all' => 'Vše'];

    /** Výstup posledního přidání projektu, typicky klíče pro GitHub. */
    public ?string $output = null;

    /** grid (mřížka po třech) nebo list (seznam), pamatuje si to přihlášení. */
    #[Session]
    public string $display = 'grid';

    #[Session]
    public string $perPage = 'all';

    public int $page = 1;

    /** Hledání v seznamu, filtruje už při psaní. */
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    /** Enter v hledání otevře první nalezený projekt. */
    public function openFirst(): void
    {
        $first = $this->listing()['projects'][0] ?? null;

        if ($first !== null && filled($this->search)) {
            $this->redirect($first['url'], navigate: true);
        }
    }

    public function updatedPerPage(): void
    {
        $this->perPage = array_key_exists($this->perPage, self::PER_PAGE) ? $this->perPage : 'all';
        $this->page = 1;
    }

    public function updatedDisplay(): void
    {
        $this->display = $this->display === 'list' ? 'list' : 'grid';
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function getSubheading(): ?string
    {
        return 'Claude Code na serveru. Každý projekt má svoje konverzace a paměť, vidí je Tom i Pavel.';
    }

    /**
     * Projekty s klientem a posledním pohybem podle abecedy (název klienta,
     * jinak projektu), po stránkách, jen ty, které odpovídají hledání. Chyba spojení se vrátí jako text,
     * stránka ji ukáže místo seznamu.
     *
     * @return array{projects: list<array<string, mixed>>, error: ?string, total: int, all: int, page: int, pages: int, from: int, to: int}
     */
    public function listing(): array
    {
        try {
            $projects = app(AgentServer::class)->projects();
        } catch (AgentUnavailable $e) {
            return ['projects' => [], 'error' => $e->getMessage(), 'total' => 0, 'all' => 0, 'page' => 1, 'pages' => 1, 'from' => 0, 'to' => 0];
        }

        $clients = Client::query()->whereNotNull('agent_project')->pluck('name', 'agent_project');

        $all = collect($projects)->map(fn (array $project): array => $project + [
            'client' => $clients[$project['slug']] ?? null,
            'type_label' => self::TYPES[$project['typ']]['label'] ?? $project['typ'],
            'type_color' => self::TYPES[$project['typ']]['color'] ?? 'gray',
            'threads_label' => self::threadsLabel($project['vlakna']),
            'url' => AgentProject::getUrl(['project' => $project['slug']]),
        ])->sortBy(fn (array $project): string => self::sortKey($project['client'] ?? $project['slug']))->values();

        $found = $all->filter(fn (array $project): bool => self::matches($project, $this->search))->values();

        $size = $this->perPage === 'all' ? max(1, $found->count()) : (int) $this->perPage;
        $pages = max(1, (int) ceil($found->count() / $size));
        $page = min($this->page, $pages);
        $slice = $found->slice(($page - 1) * $size, $size)->values();

        return [
            'projects' => $slice->all(),
            'error' => null,
            'total' => $found->count(),
            'all' => $all->count(),
            'page' => $page,
            'pages' => $pages,
            'from' => $slice->isEmpty() ? 0 : ($page - 1) * $size + 1,
            'to' => ($page - 1) * $size + $slice->count(),
        ];
    }

    /** Každé slovo hledání je ve jméně klienta nebo projektu, bez ohledu na háčky: „ceska“ najde „Česká louka“. */
    private static function matches(array $project, string $search): bool
    {
        $haystack = self::fold(($project['client'] ?? '').' '.$project['slug']);
        $words = preg_split('/\s+/', self::fold($search), -1, PREG_SPLIT_NO_EMPTY);

        return collect($words)->every(fn (string $word): bool => str_contains($haystack, $word));
    }

    private static function fold(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /** Abeceda bez háčků: „Česká louka“ mezi C, ne na konec. */
    private static function sortKey(string $name): string
    {
        return self::fold($name).' '.$name;
    }

    /**
     * Klienti, kterým se projekt zakládá, a ti, kterým se nezaložil (podle chyby).
     *
     * @return array{queued: Collection<int, Client>, failed: Collection<string, Collection<int, Client>>}
     */
    public function clientRequests(): array
    {
        return [
            'queued' => ClientProjects::queued(),
            'failed' => ClientProjects::failed()->groupBy(fn (Client $client): string => $client->agent_project_request['error'] ?? 'neznámá chyba'),
        ];
    }

    /** „Běží 2 konverzace · naposledy před 5 minutami“ */
    private static function threadsLabel(array $windows): string
    {
        if ($windows === []) {
            return 'Žádná konverzace teď neběží';
        }

        $count = count($windows);
        $label = 'Běží '.$count.' '.($count < 5 ? 'konverzace' : 'konverzací');
        $last = self::ago(collect($windows)->max('aktivita'));

        return $last ? $label.' · naposledy '.$last : $label;
    }

    /** „před 5 minutami“, čerstvé „právě teď“ (ne „před 0 sekundami“). */
    public static function ago(?int $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        $time = Carbon::createFromTimestamp($timestamp);

        return $time->diffInSeconds(now()) < 60 ? 'právě teď' : $time->diffForHumans();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clientProjects')
                ->label('Založit agenty klientům')
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('gray')
                ->visible(fn (): bool => ClientProjects::missing()->isNotEmpty())
                ->modalHeading('Založit agenty klientům')
                ->modalDescription('Každý klient dostane projekt bez kódu se stejným názvem jako jeho zkratka. Zakládají se na pozadí po jednom, pár sekund na klienta.')
                ->modalSubmitActionLabel('Založit')
                ->schema([
                    CheckboxList::make('clients')
                        ->label('Klienti bez agenta')
                        ->options(fn (): array => ClientProjects::missing()->mapWithKeys(fn (Client $client): array => [$client->getKey() => $client->name])->all())
                        ->descriptions(fn (): array => ClientProjects::missing()->mapWithKeys(fn (Client $client): array => [
                            $client->getKey() => 'projekt '.$client->slug.(filled($client->agent_project_request['error'] ?? null) ? ' · minule se nepovedlo: '.$client->agent_project_request['error'] : ''),
                        ])->all())
                        ->default(fn (): array => ClientProjects::missing()->modelKeys())
                        ->bulkToggleable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $projects = app(ClientProjects::class);
                    $clients = Client::query()->whereKey($data['clients'])->get();
                    $clients->each(fn (Client $client) => $projects->request($client, auth()->user()?->name));

                    Notification::make()->success()
                        ->title('Zakládá se agentů: '.$clients->count())
                        ->body('Na pozadí po jednom, do pár minut budou v seznamu.')
                        ->send();
                }),
            Action::make('addProject')
                ->label('Přidat projekt')
                ->icon(Heroicon::OutlinedPlus)
                ->modalDescription('Web, který běží na tomto serveru, připojí Tom přes SSH (agent pridat … server …). Dává agentovi přístup k živému webu klienta, proto ne z administrace.')
                ->schema([
                    TextInput::make('slug')
                        ->label('Název projektu')
                        ->helperText('Malá písmena, číslice a pomlčky, třeba iq-hracky.')
                        ->required()
                        ->regex('/^[a-z0-9][a-z0-9-]{1,40}$/'),
                    Select::make('type')
                        ->label('Typ')
                        ->options([
                            'prazdny' => 'Bez kódu (Shoptet, Upgates, marketing)',
                            'git' => 'Repozitář na GitHubu',
                        ])
                        ->default('prazdny')
                        ->required()
                        ->live(),
                    TextInput::make('source')
                        ->label('Adresa repozitáře')
                        ->placeholder('git@github.com:ucet/repozitar.git')
                        ->visible(fn (Get $get): bool => $get('type') === 'git')
                        ->required(fn (Get $get): bool => $get('type') === 'git'),
                    Select::make('client_id')
                        ->label('Klient')
                        ->options(fn (): array => Client::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder('Bez klienta'),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = app(AgentServer::class)->addProject($data['slug'], $data['type'], $data['source'] ?? null);
                    } catch (AgentUnavailable $e) {
                        Notification::make()->danger()->title('Projekt se nepřidal')->body($e->getMessage())->send();

                        return;
                    }

                    if (filled($data['client_id'] ?? null)) {
                        Client::query()->whereKey($data['client_id'])->update(['agent_project' => $data['slug']]);
                    }

                    $this->output = $result['vystup'];

                    if (! $result['ok']) {
                        Notification::make()->warning()->title('Projekt zatím není hotový')->body('Postup je pod seznamem projektů.')->send();

                        return;
                    }

                    Notification::make()->success()->title('Projekt '.$data['slug'].' je připravený')->send();
                    $this->redirect(AgentProject::getUrl(['project' => $data['slug']]));
                }),
        ];
    }
}
