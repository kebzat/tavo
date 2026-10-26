<?php

namespace App\Filament\Tools\Pages\Agent;

use App\Models\Client;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

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

    /** Výstup posledního přidání projektu, typicky klíče pro GitHub. */
    public ?string $output = null;

    public function getSubheading(): ?string
    {
        return 'Claude Code na serveru. Každý projekt má svoje konverzace a paměť, vidí je Tom i Pavel.';
    }

    /**
     * Projekty s klientem a posledním pohybem. Chyba spojení se vrátí jako
     * text, stránka ji ukáže místo seznamu.
     *
     * @return array{projects: list<array<string, mixed>>, error: ?string}
     */
    public function listing(): array
    {
        try {
            $projects = app(AgentServer::class)->projects();
        } catch (AgentUnavailable $e) {
            return ['projects' => [], 'error' => $e->getMessage()];
        }

        $clients = Client::query()->whereNotNull('agent_project')->pluck('name', 'agent_project');

        return [
            'projects' => collect($projects)->map(fn (array $project): array => $project + [
                'client' => $clients[$project['slug']] ?? null,
                'type_label' => self::TYPES[$project['typ']]['label'] ?? $project['typ'],
                'type_color' => self::TYPES[$project['typ']]['color'] ?? 'gray',
                'threads_label' => self::threadsLabel($project['vlakna']),
                'url' => AgentProject::getUrl(['project' => $project['slug']]),
            ])->all(),
            'error' => null,
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
