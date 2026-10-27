<?php

namespace App\Filament\Tools\Pages\Agent;

use App\Filament\Tools\Resources\Skills\SkillResource;
use App\Models\Client;
use App\Models\KnowledgeEntry;
use App\Models\Skill;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;

/**
 * Projekty agenta na serveru (docs/AGENT.md). Každý má vlastní konverzace
 * s Claudem, otevírají se v detailu projektu. Rozdělené na skilly (specialisté
 * po skupinách, docs/MOZEK.md, Skilly), klienty a interní projekty.
 */
class AgentProjects extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static ?string $navigationLabel = 'Projekty';

    protected static ?string $title = 'Projekty';

    protected static ?string $slug = 'agent';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.tools.pages.agent.projects';

    private const TYPES = [
        'server' => ['label' => 'živý web', 'color' => 'warning'],
        'git' => ['label' => 'GitHub', 'color' => 'info'],
        'prazdny' => ['label' => 'bez kódu', 'color' => 'gray'],
    ];

    /** Kolik projektů klientů na stránku: 10, 20, 50 nebo all. */
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

    /** Enter v hledání otevře první nalezený projekt (skill, klient, interní). */
    public function openFirst(): void
    {
        $listing = $this->listing();
        $first = collect($listing['skills'])->flatten(1)->first() ?? $listing['projects'][0] ?? $listing['internal'][0] ?? null;

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
        return 'Claude Code na serveru. Každý projekt má svoje konverzace a paměť, vidí je Tom i Pavel. Skilly jsou specialisté, které agenti klientů volají a kteří se učí ze všech klientů.';
    }

    /**
     * Skilly po skupinách, projekty klientů (podle abecedy, po stránkách) a interní
     * projekty, jen to, co odpovídá hledání. Chyba spojení se vrátí jako text,
     * stránka ji ukáže místo seznamu (skilly zůstanou, jsou v databázi).
     *
     * @return array{skills: array<string, list<array<string, mixed>>>, projects: list<array<string, mixed>>, internal: list<array<string, mixed>>, error: ?string, total: int, all: int, found: int, page: int, pages: int, from: int, to: int}
     */
    public function listing(): array
    {
        $error = null;

        try {
            $projects = collect(app(AgentServer::class)->projects())->keyBy('slug');
        } catch (AgentUnavailable $e) {
            [$projects, $error] = [collect(), $e->getMessage()];
        }

        $clients = Client::query()->whereNotNull('agent_project')->pluck('name', 'agent_project');
        $skills = Skill::query()
            ->withCount([
                'entries as approved_count' => fn ($query) => $query->where('status', KnowledgeEntry::STATUS_APPROVED),
                'entries as proposed_count' => fn ($query) => $query->where('status', KnowledgeEntry::STATUS_PROPOSED),
                'sources',
            ])
            ->orderBy('sort')
            ->get();

        $skillItems = $skills->map(fn (Skill $skill): array => $this->skillItem($skill, $projects->get($skill->agent_project)))
            ->filter(fn (array $item): bool => self::matches($item['haystack'], $this->search));

        $own = $projects->reject(fn (array $project): bool => $skills->contains('agent_project', $project['slug']));
        $all = $own
            ->map(fn (array $project): array => $project + [
                'client' => $clients[$project['slug']] ?? null,
                'type_label' => self::TYPES[$project['typ']]['label'] ?? $project['typ'],
                'type_color' => self::TYPES[$project['typ']]['color'] ?? 'gray',
                'threads_label' => self::threadsLabel($project['vlakna']),
                'url' => AgentProject::getUrl(['project' => $project['slug']]),
            ])
            ->sortBy(fn (array $project): string => self::sortKey($project['client'] ?? $project['slug']))
            ->filter(fn (array $project): bool => self::matches(($project['client'] ?? '').' '.$project['slug'], $this->search))
            ->values();

        [$found, $internal] = $all->partition(fn (array $project): bool => $project['client'] !== null);
        $found = $found->values();

        $size = $this->perPage === 'all' ? max(1, $found->count()) : (int) $this->perPage;
        $pages = max(1, (int) ceil($found->count() / $size));
        $page = min($this->page, $pages);
        $slice = $found->slice(($page - 1) * $size, $size)->values();

        return [
            'skills' => collect(Skill::categories())
                ->map(fn (string $label, string $key): array => $skillItems->where('category', $key)->values()->all())
                ->filter()
                ->all(),
            'projects' => $slice->all(),
            'internal' => $internal->values()->all(),
            'error' => $error,
            'total' => $found->count(),
            'all' => $own->count() + $skills->count(),
            'found' => $skillItems->count() + $all->count(),
            'page' => $page,
            'pages' => $pages,
            'from' => $slice->isEmpty() ? 0 : ($page - 1) * $size + 1,
            'to' => ($page - 1) * $size + $slice->count(),
        ];
    }

    /** Karta skillu: bez projektu (zakládá se) vede do nastavení skillu. */
    private function skillItem(Skill $skill, ?array $project): array
    {
        $request = $skill->agent_project_request ?? [];

        return [
            'slug' => $skill->slug,
            'name' => $skill->name,
            'category' => $skill->category,
            'scope' => $skill->scope(),
            'project' => $project['slug'] ?? null,
            'url' => $project ? AgentProject::getUrl(['project' => $project['slug']]) : SkillResource::getUrl('edit', ['record' => $skill]),
            'status' => match (true) {
                $project !== null => self::threadsLabel($project['vlakna']),
                ($request['status'] ?? null) === ClientProjects::STATUS_QUEUED => 'Agent se zakládá…',
                ($request['status'] ?? null) === ClientProjects::STATUS_FAILED => 'Agent se nezaložil',
                filled($skill->agent_project) => 'Projekt '.$skill->agent_project.' na serveru není',
                default => 'Zatím bez agenta',
            },
            'ready' => $project !== null,
            'knowledge' => self::knowledgeLabel($skill->approved_count, $skill->sources_count),
            'proposed' => $skill->proposed_count,
            'haystack' => $skill->name.' '.$skill->slug.' '.$skill->categoryLabel(),
        ];
    }

    /** „12 poznatků · 3 v archivu“ */
    private static function knowledgeLabel(int $entries, int $sources): string
    {
        $entriesLabel = $entries.' '.match (true) {
            $entries === 1 => 'poznatek', $entries > 1 && $entries < 5 => 'poznatky', default => 'poznatků'
        };

        return $sources > 0 ? $entriesLabel.' · '.$sources.' v archivu' : $entriesLabel;
    }

    /** Každé slovo hledání je ve jméně (klienta, projektu, skillu), bez ohledu na háčky: „ceska“ najde „Česká louka“. */
    private static function matches(string $haystack, string $search): bool
    {
        $haystack = self::fold($haystack);
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
     * Klienti a skilly, kterým se projekt zakládá, a ti, kterým se nezaložil (podle chyby).
     *
     * @return array{queued: Collection<int, Client|Skill>, failed: Collection<string, Collection<int, Client|Skill>>}
     */
    public function clientRequests(): array
    {
        return [
            'queued' => ClientProjects::queued(),
            'failed' => ClientProjects::failed()->groupBy(fn (Model $owner): string => $owner->agent_project_request['error'] ?? 'neznámá chyba'),
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
