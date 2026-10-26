<?php

namespace App\Support\ClickUp;

use App\Models\Client;
use App\Models\KnowledgeSource;
use App\Support\Agent\AgentServer;
use App\Support\Agent\AgentUnavailable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Klienti ze složek ClickUpu (Klienti → Načíst z ClickUpu). Složka = klient:
 * založí ho (nebo doplní stávajícího se stejným názvem), napojí celou složku
 * na agenta, založí projekt agenta bez kódu a do archivu klienta načte všechno
 * ze složky: každý seznam jako dokument s úkoly, komentáři a přílohami a každý
 * Doc jako dokument se stránkami. Prázdný seznam ani prázdný Doc dokument nemá.
 *
 * Načtení trvá minuty (ClickUp pustí 100 dotazů za minutu), proto ho dělá
 * `clickup:nacist` na pozadí. Opakované načtení dokumenty přepíše
 * (`external_ref`), nové přidá. Viz docs/AGENT.md, Klienti z ClickUpu.
 */
class ClientImport
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /** Přílohy nad tuhle velikost zůstanou jen v ClickUpu (v dokumentu je odkaz). */
    public const MAX_FILE_BYTES = 30 * 1024 * 1024;

    /** Celkem příloh na jednoho klienta, ať jedna složka nezaplní disk. */
    public const MAX_TOTAL_BYTES = 500 * 1024 * 1024;

    public const AUTHOR = 'ClickUp';

    private int $downloaded = 0;

    public function __construct(
        private readonly ClickUp $clickUp,
        private readonly AgentServer $agent,
    ) {}

    /**
     * Složky z ClickUpu pro výběr. U každé, jestli už klient je (podle složky
     * nebo stejného názvu).
     *
     * @return list<array{id: string, name: string, space: string, lists: int, client: ?string, linked: bool}>
     */
    public function folders(): array
    {
        $clients = Client::query()->get(['id', 'name', 'slug', 'clickup_folder_id']);

        return collect($this->clickUp->folders())
            ->map(function (array $folder) use ($clients): array {
                $linked = $clients->firstWhere('clickup_folder_id', $folder['id']);
                $same = $linked ?? $this->sameName($clients, $folder['name']);

                return [
                    'id' => $folder['id'],
                    'name' => $folder['name'],
                    'space' => $folder['space'],
                    'lists' => count($folder['lists']),
                    'client' => $same?->name,
                    'linked' => $linked !== null,
                ];
            })
            ->sortBy(fn (array $folder): string => $folder['space'].' '.Str::slug($folder['name']))
            ->values()
            ->all();
    }

    /**
     * Klient ze složky: založí ho, nebo napojí stávajícího se stejným názvem,
     * přidá projekt agenta (když ho klient ještě nemá) a zařadí načtení archivu.
     *
     * @param  array{id: string, name: string}  $folder
     */
    public function create(array $folder, ?string $author): Client
    {
        $client = Client::query()->where('clickup_folder_id', $folder['id'])->first()
            ?? $this->sameName(Client::query()->get(), $folder['name']);

        if ($client === null) {
            $client = Client::create([
                'name' => $folder['name'],
                'slug' => $this->uniqueSlug(Str::slug($folder['name']) ?: 'klient'),
            ]);
        }

        $client->forceFill(['clickup_folder_id' => $folder['id']])->save();
        $note = blank($client->agent_project) ? $this->addProject($client) : null;
        $this->queue($client, $author, $note);

        return $client;
    }

    /** Načíst složku do archivu znovu (tlačítko u klienta). */
    public function queue(Client $client, ?string $author, ?string $note = null): void
    {
        $client->forceFill(['clickup_import' => array_filter([
            'status' => self::STATUS_QUEUED,
            'requested_at' => now()->toIso8601String(),
            'author' => $author,
            'project_note' => $note,
        ])])->save();
    }

    /**
     * Načte složku do archivu klienta. Chyba jednoho úkolu, přílohy nebo Docu
     * načtení nezastaví, jen se zapíše do přehledu.
     */
    public function run(Client $client): void
    {
        $state = $client->clickup_import ?? [];
        $client->forceFill(['clickup_import' => [...$state, 'status' => self::STATUS_RUNNING, 'started_at' => now()->toIso8601String()]])->save();
        $this->downloaded = 0;
        $problems = [];
        $counts = ['lists' => 0, 'tasks' => 0, 'docs' => 0, 'files' => 0];

        try {
            $folder = $this->clickUp->folder((string) $client->clickup_folder_id);

            foreach ($folder['lists'] as $list) {
                $result = $this->importList($client, $folder, $list, $problems);
                $counts['lists'] += $result['tasks'] > 0 ? 1 : 0;
                $counts['tasks'] += $result['tasks'];
                $counts['files'] += $result['files'];
            }

            foreach ($this->docsOf($folder, $problems) as $doc) {
                $counts['docs'] += $this->importDoc($client, $doc, $problems) ? 1 : 0;
            }
        } catch (Throwable $e) {
            report($e);
            $client->forceFill(['clickup_import' => [...$state, 'status' => self::STATUS_FAILED, 'finished_at' => now()->toIso8601String(), 'error' => Str::limit($e->getMessage(), 500), ...$counts]])->save();

            return;
        }

        $client->forceFill(['clickup_import' => [
            ...$state,
            'status' => self::STATUS_DONE,
            'finished_at' => now()->toIso8601String(),
            ...$counts,
            'problems' => array_slice($problems, 0, 20) ?: null,
        ]])->save();
    }

    /** „Načteno 26. 10. 21:40: 3 seznamy, 45 úkolů, 1 Doc, 12 příloh“ do archivu klienta. */
    public static function summary(Client $client): ?string
    {
        $state = $client->clickup_import;

        if (! is_array($state)) {
            return null;
        }

        $time = fn (?string $value): string => $value ? CarbonImmutable::parse($value)->timezone('Europe/Prague')->format('j. n. H:i') : '';
        $counts = collect([
            'lists' => ['seznam', 'seznamy', 'seznamů'],
            'tasks' => ['úkol', 'úkoly', 'úkolů'],
            'docs' => ['Doc', 'Docs', 'Docs'],
            'files' => ['příloha', 'přílohy', 'příloh'],
        ])->map(fn (array $words, string $key): ?string => ($state[$key] ?? 0) > 0 ? $state[$key].' '.self::plural((int) $state[$key], $words) : null)->filter()->implode(', ');

        return match ($state['status'] ?? null) {
            self::STATUS_QUEUED => 'Z ClickUpu: čeká na načtení (do minuty začne).',
            self::STATUS_RUNNING => 'Z ClickUpu: načítá se od '.$time($state['started_at'] ?? null).'. Velká složka trvá i několik minut.',
            self::STATUS_FAILED => 'Z ClickUpu se načíst nepovedlo ('.$time($state['finished_at'] ?? null).'): '.($state['error'] ?? 'neznámá chyba').'.',
            self::STATUS_DONE => 'Z ClickUpu načteno '.$time($state['finished_at'] ?? null).': '.($counts ?: 'složka je prázdná').'.'
                .(filled($state['problems'] ?? null) ? ' Nenačteno: '.implode('; ', $state['problems']).'.' : ''),
            default => null,
        }.(filled($state['project_note'] ?? null) ? ' '.$state['project_note'] : '');
    }

    /** @param  list<string>  $problems */
    private function importList(Client $client, array $folder, array $list, array &$problems): array
    {
        $tasks = $this->clickUp->listTasks($list['id']);

        if ($tasks === []) {
            return ['tasks' => 0, 'files' => 0];
        }

        $ref = 'clickup:list:'.$list['id'];
        $source = KnowledgeSource::query()->where('client_id', $client->getKey())->where('external_ref', $ref)->first();
        $oldFiles = collect($source?->files ?? [])->pluck('path')->filter()->all();
        $files = [];
        $children = collect($tasks)->groupBy(fn (array $task): string => (string) ($task['parent'] ?? ''));
        $sections = [];

        foreach ($children->get('', collect()) as $task) {
            $sections[] = $this->taskMarkdown($client, $task, $children, 2, $files, $problems);
        }

        // Podúkoly, jejichž nadřazený úkol je v jiném seznamu.
        $ids = collect($tasks)->pluck('id')->map(fn ($id): string => (string) $id);

        foreach ($children->except([''])->filter(fn ($group, $parent): bool => ! $ids->contains((string) $parent))->flatten(1) as $task) {
            $sections[] = $this->taskMarkdown($client, $task, $children, 2, $files, $problems);
        }

        $closed = collect($tasks)->filter(fn (array $task): bool => in_array($task['status']['type'] ?? null, ['closed', 'done'], true))->count();
        $body = '# '.$list['name']."\n\n"
            .'Seznam ze složky „'.$folder['name'].'“ v ClickUpu, načteno '.now()->timezone('Europe/Prague')->format('j. n. Y H:i').'. '
            .count($tasks).' '.self::plural(count($tasks), ['úkol', 'úkoly', 'úkolů']).', z toho hotové: '.$closed.'. U každého popis, komentáře a přílohy.'
            ."\n\n".implode("\n\n", $sections)."\n";

        $data = [
            'client_id' => $client->getKey(),
            'title' => 'ClickUp: '.$list['name'],
            'category' => 'jine',
            'document_date' => today(),
            'summary' => Str::limit('Úkoly ze seznamu „'.$list['name'].'“ v ClickUpu ('.count($tasks).', z toho hotové: '.$closed.') s popisy a komentáři'.($files ? ', přílohy: '.count($files) : '').'.', 500),
            'learn' => false,
            'status' => KnowledgeSource::STATUS_STORED,
            'body' => $body,
            'files' => $files ?: null,
            'author' => self::AUTHOR,
            'external_ref' => $ref,
        ];

        $source ? $source->update($data) : KnowledgeSource::create($data);
        Storage::disk('local')->delete(array_diff($oldFiles, collect($files)->pluck('path')->all()));

        return ['tasks' => count($tasks), 'files' => count($files)];
    }

    /**
     * @param  array<string, mixed>  $task  úkol z výpisu seznamu
     * @param  Collection<string, Collection<int, array<string, mixed>>>  $children
     * @param  list<array{path: string, name: string}>  $files
     * @param  list<string>  $problems
     */
    private function taskMarkdown(Client $client, array $task, $children, int $level, array &$files, array &$problems): string
    {
        $id = (string) $task['id'];

        try {
            $task = [...$task, ...$this->clickUp->taskDetail($id)];
        } catch (Throwable $e) {
            $problems[] = 'úkol „'.Str::limit((string) ($task['name'] ?? $id), 60).'“ bez příloh a checklistů';
        }

        $date = fn ($ms): ?string => filled($ms) ? CarbonImmutable::createFromTimestampMs((int) $ms)->timezone('Europe/Prague')->format('j. n. Y') : null;
        $heading = str_repeat('#', min(6, $level));
        $meta = collect([
            'Stav' => $task['status']['status'] ?? null,
            'Priorita' => $task['priority']['priority'] ?? null,
            'Začátek' => $date($task['start_date'] ?? null),
            'Termín' => $date($task['due_date'] ?? null),
            'Hotovo' => $date($task['date_done'] ?? $task['date_closed'] ?? null),
            'Založeno' => $date($task['date_created'] ?? null),
            'Řeší' => collect($task['assignees'] ?? [])->pluck('username')->filter()->implode(', ') ?: null,
            'Štítky' => collect($task['tags'] ?? [])->pluck('name')->filter()->implode(', ') ?: null,
        ])->filter()->map(fn (string $value, string $label): string => $label.': '.$value)->implode(' · ');

        $lines = [$heading.' '.trim((string) ($task['name'] ?? 'Bez názvu'))];
        $lines[] = trim($meta.(filled($task['url'] ?? null) ? "\nOdkaz: ".$task['url'] : ''));

        $fields = collect($task['custom_fields'] ?? [])
            ->map(fn (array $field): ?string => ($value = $this->fieldValue($field)) !== null ? '- '.$field['name'].': '.$value : null)
            ->filter();

        if ($fields->isNotEmpty()) {
            $lines[] = $fields->implode("\n");
        }

        $description = trim((string) ($task['markdown_description'] ?? $task['text_content'] ?? $task['description'] ?? ''));

        if ($description !== '') {
            $lines[] = $description;
        }

        foreach ($task['checklists'] ?? [] as $checklist) {
            $items = collect($checklist['items'] ?? [])->map(fn (array $item): string => '- ['.(! empty($item['resolved']) ? 'x' : ' ').'] '.$item['name']);

            if ($items->isNotEmpty()) {
                $lines[] = '**Checklist '.($checklist['name'] ?? '').'**'."\n".$items->implode("\n");
            }
        }

        $attachments = $this->attachments($client, $task, $files, $problems);

        if ($attachments !== []) {
            $lines[] = '**Přílohy**'."\n".implode("\n", $attachments);
        }

        try {
            $comments = $this->clickUp->allComments($id);
        } catch (Throwable) {
            $comments = [];
            $problems[] = 'komentáře úkolu „'.Str::limit((string) ($task['name'] ?? $id), 60).'“';
        }

        $comments = array_values(array_filter($comments, fn (array $comment): bool => $comment['text'] !== ''));

        if ($comments !== []) {
            $lines[] = '**Komentáře**'."\n".collect($comments)
                ->map(fn (array $comment): string => '- '.$comment['author'].($comment['date'] ? ', '.$comment['date'] : '').': '.str_replace("\n", "\n  ", $comment['text']))
                ->implode("\n");
        }

        foreach ($children->get($id, collect()) as $child) {
            $lines[] = $this->taskMarkdown($client, $child, $children, $level + 1, $files, $problems);
        }

        return implode("\n\n", array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    /**
     * Stáhne přílohy úkolu do archivu. Vrátí řádky do dokumentu.
     *
     * @param  list<array{path: string, name: string}>  $files
     * @param  list<string>  $problems
     * @return list<string>
     */
    private function attachments(Client $client, array $task, array &$files, array &$problems): array
    {
        $lines = [];

        foreach ($task['attachments'] ?? [] as $attachment) {
            $url = (string) ($attachment['url'] ?? '');
            $name = $this->fileName($attachment);
            $size = (int) ($attachment['size'] ?? 0);

            if ($url === '') {
                continue;
            }

            if ($size > self::MAX_FILE_BYTES || $this->downloaded + $size > self::MAX_TOTAL_BYTES) {
                $lines[] = '- '.$name.' (velká, jen v ClickUpu: '.$url.')';

                continue;
            }

            $content = rescue(fn (): ?string => $this->clickUp->download($url, self::MAX_FILE_BYTES), null, report: false);

            if ($content === null) {
                $lines[] = '- '.$name.' (nestáhla se, v ClickUpu: '.$url.')';
                $problems[] = 'příloha '.$name;

                continue;
            }

            $path = 'mozek/clickup/'.$client->getKey().'/'.Str::lower(Str::random(12)).'-'.Str::limit(Str::slug(pathinfo($name, PATHINFO_FILENAME)), 60, '').$this->extension($name);
            Storage::disk('local')->put($path, $content);
            $this->downloaded += strlen($content);
            $files[] = ['path' => $path, 'name' => $name];
            $lines[] = '- '.$name.' (v archivu u tohoto dokumentu)';
        }

        return $lines;
    }

    /**
     * Docs složky a jejích seznamů: hledáním podle rodiče i z pohledů typu Doc.
     *
     * @param  list<string>  $problems
     * @return list<array{id: string, name: string}>
     */
    private function docsOf(array $folder, array &$problems): array
    {
        $docs = [];

        foreach ([['id' => $folder['id'], 'name' => $folder['name']], ...$folder['lists']] as $parent) {
            try {
                $docs = [...$docs, ...$this->clickUp->docs($parent['id'])];
            } catch (Throwable) {
                $problems[] = 'Docs u „'.$parent['name'].'“';
            }
        }

        $docs = [...$docs, ...rescue(fn (): array => $this->clickUp->docViews($folder['id']), [], report: false)];

        return collect($docs)->unique('id')->values()->all();
    }

    /** @param  list<string>  $problems */
    private function importDoc(Client $client, array $doc, array &$problems): bool
    {
        try {
            $pages = $this->clickUp->docPages($doc['id']);
        } catch (Throwable) {
            $problems[] = 'Doc „'.$doc['name'].'“';

            return false;
        }

        // Prázdný Doc (jen názvy stránek bez textu) do archivu nepatří.
        if (collect($pages)->every(fn (array $page): bool => $page['content'] === '')) {
            return false;
        }

        $body = collect($pages)
            ->map(fn (array $page): string => trim(str_repeat('#', min(6, $page['depth'] + 2)).' '.($page['name'] ?: 'Stránka')."\n\n".$page['content']))
            ->prepend('# '.$doc['name']."\n\nDoc z ClickUpu, načteno ".now()->timezone('Europe/Prague')->format('j. n. Y H:i').'.')
            ->implode("\n\n")."\n";

        $ref = 'clickup:doc:'.$doc['id'];
        $data = [
            'client_id' => $client->getKey(),
            'title' => 'ClickUp Doc: '.$doc['name'],
            'category' => 'jine',
            'document_date' => today(),
            'summary' => Str::limit('Doc „'.$doc['name'].'“ z ClickUpu ('.count($pages).' '.self::plural(count($pages), ['stránka', 'stránky', 'stránek']).'): '.Str::squish(collect($pages)->pluck('content')->implode(' ')), 500),
            'learn' => false,
            'status' => KnowledgeSource::STATUS_STORED,
            'body' => $body,
            'files' => null,
            'author' => self::AUTHOR,
            'external_ref' => $ref,
        ];

        $source = KnowledgeSource::query()->where('client_id', $client->getKey())->where('external_ref', $ref)->first();
        $source ? $source->update($data) : KnowledgeSource::create($data);

        return true;
    }

    /** Vlastní pole úkolu jako text. Prázdná a složitá (vztahy, soubory) se vynechají. */
    private function fieldValue(array $field): ?string
    {
        $value = $field['value'] ?? null;

        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $options = collect($field['type_config']['options'] ?? []);
        $option = fn ($key): ?string => ($found = $options->first(fn (array $o): bool => (string) ($o['id'] ?? '') === (string) $key || (string) ($o['orderindex'] ?? '') === (string) $key)) ? (string) ($found['name'] ?? $found['label'] ?? '') : null;

        return match ($field['type'] ?? null) {
            'drop_down' => $option($value),
            'labels' => collect((array) $value)->map($option)->filter()->implode(', ') ?: null,
            'date' => CarbonImmutable::createFromTimestampMs((int) $value)->timezone('Europe/Prague')->format('j. n. Y'),
            'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'ano' : 'ne',
            'users' => collect((array) $value)->pluck('username')->filter()->implode(', ') ?: null,
            default => is_scalar($value) ? Str::limit((string) $value, 1000) : null,
        };
    }

    private function fileName(array $attachment): string
    {
        $name = trim((string) ($attachment['title'] ?? ''));
        $extension = trim((string) ($attachment['extension'] ?? ''));

        if ($name === '') {
            $name = basename((string) parse_url((string) ($attachment['url'] ?? ''), PHP_URL_PATH)) ?: 'priloha';
        }

        return $extension !== '' && ! str_ends_with(Str::lower($name), '.'.Str::lower($extension)) ? $name.'.'.$extension : $name;
    }

    private function extension(string $name): string
    {
        $extension = Str::lower((string) pathinfo($name, PATHINFO_EXTENSION));

        return preg_match('/^[a-z0-9]{1,8}$/', $extension) ? '.'.$extension : '';
    }

    /**
     * Projekt agenta bez kódu se stejným názvem jako zkratka klienta. Když
     * projekt už existuje a nepatří jinému klientovi, jen se přiřadí.
     * Vrátí větu do přehledu, když se nepovedl.
     */
    private function addProject(Client $client): ?string
    {
        $slug = Str::limit(trim(preg_replace('/[^a-z0-9-]+/', '-', Str::slug($client->slug)), '-'), 40, '');

        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,40}$/', $slug)) {
            return 'Projekt agenta se nezaložil (zkratka „'.$client->slug.'“ nejde použít), přidejte ho v Agent → Přidat projekt.';
        }

        try {
            $existing = collect($this->agent->projects())->pluck('slug');

            if ($existing->contains($slug) && Client::query()->where('agent_project', $slug)->whereKeyNot($client->getKey())->exists()) {
                return 'Projekt agenta „'.$slug.'“ už patří jinému klientovi, přidejte klientovi jiný v Agent → Přidat projekt.';
            }

            $result = $existing->contains($slug) ? ['ok' => true] : $this->agent->addProject($slug, 'prazdny');
        } catch (AgentUnavailable $e) {
            return 'Projekt agenta se nezaložil (služba agenta neodpovídá), přidejte ho v Agent → Přidat projekt.';
        }

        if (! $result['ok']) {
            return 'Projekt agenta se nezaložil: '.Str::limit(trim((string) ($result['vystup'] ?? '')), 200).'.';
        }

        $client->forceFill(['agent_project' => $slug])->save();

        return null;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;

        for ($i = 2; Client::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** „Svět Cejlonu“ ve složce = klient „Svět Cejlonu“ (zkratka i název). */
    private function sameName($clients, string $name): ?Client
    {
        $key = Str::slug($name);

        return $clients->first(fn (Client $client): bool => blank($client->clickup_folder_id ?? null) && (Str::slug($client->name) === $key || $client->slug === $key));
    }

    /** @param  array{0: string, 1: string, 2: string}  $words  1, 2–4, 5+ */
    private static function plural(int $count, array $words): string
    {
        return $count === 1 ? $words[0] : ($count >= 2 && $count <= 4 ? $words[1] : $words[2]);
    }
}
