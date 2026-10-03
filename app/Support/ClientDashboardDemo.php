<?php

namespace App\Support;

use App\Enums\TaskStatus;
use App\Enums\WorkArea;
use App\Models\Client;
use App\Models\ClientTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ukázkový klient s přehledem spolupráce, ať jde poslat odkaz a ukázat,
 * jak přehled vypadá. E-shop i čísla jsou vymyšlené.
 *
 * Data se vztahují k aktuálnímu měsíci (dva měsíce historie, plán dopředu)
 * a 1. v měsíci se obnoví (clients:dashboard-demo), jinak by za měsíc
 * odkaz ukazoval prázdný měsíc. Klient i jeho odkaz zůstávají stejné.
 */
final class ClientDashboardDemo
{
    public const NAME = 'Ukázka: Bylinky z Podkrkonoší';

    public function client(): ?Client
    {
        return Client::query()->where('name', self::NAME)->first();
    }

    /** Založí klienta, když chybí, a naplní ho čerstvými daty. Vrací odkaz na přehled. */
    public function install(): string
    {
        $client = $this->client() ?? Client::create([
            'name' => self::NAME,
            'slug' => UniqueSlug::for(new Client, self::NAME),
            'note' => 'Ukázka přehledu spolupráce, vymyšlený e-shop. Data se obnovují 1. v měsíci. Smazat: php artisan clients:dashboard-demo --remove',
            'dashboard_enabled' => true,
        ]);

        $this->refresh($client);

        return $client->dashboardPreviewUrl();
    }

    public function remove(): bool
    {
        $client = $this->client();

        if (! $client) {
            return false;
        }

        // Zápisy času nejdřív: na úkoly mají cizí klíč s nullOnDelete, smazaly by se až s klientem.
        $client->timeEntries()->delete();
        $client->delete();

        return true;
    }

    /** Smaže úkoly, hodiny a měsíce ukázky a založí je znovu k dnešku. */
    public function refresh(Client $client): void
    {
        $now = CarbonImmutable::now();
        $m0 = $now->startOfMonth();
        $m1 = $m0->subMonthNoOverflow();
        $m2 = $m0->subMonthsNoOverflow(2);
        $tom = User::query()->where('email', 'tom@taveo.cz')->value('id');
        $pavel = User::query()->where('email', 'pavel@taveo.cz')->value('id');

        DB::transaction(function () use ($client, $now, $m0, $m1, $m2, $tom, $pavel): void {
            $client->timeEntries()->delete();
            $client->tasks()->delete();
            $client->months()->delete();

            $client->update(['started_on' => $m2->toDateString(), 'dashboard_enabled' => true]);

            if (! $client->retainers()->exists()) {
                $client->retainers()->createMany([
                    ['area' => WorkArea::Web, 'label' => 'Vývoj webu', 'monthly_fee' => 10000, 'included_hours' => 8, 'order_column' => 1],
                    ['area' => WorkArea::Marketing, 'label' => 'Marketing', 'monthly_fee' => 5000, 'included_hours' => 4, 'order_column' => 2],
                ]);
            }

            // Den v měsíci. V aktuálním měsíci nejvýš dnešek, ať nejsou zápisy v budoucnu.
            $day = fn (CarbonImmutable $month, int $day): string => $month->addDays($day - 1)->min($now)->toDateString();

            $task = fn (array $data): ClientTask => $client->tasks()->create($data);

            $time = function (?ClientTask $task, ?int $user, string $date, int $minutes, ?WorkArea $area = null, bool $billable = true) use ($client): void {
                $client->timeEntries()->create([
                    'task_id' => $task?->id,
                    'area' => $task?->area ?? $area,
                    'user_id' => $user,
                    'worked_on' => $date,
                    'minutes' => $minutes,
                    'description' => 'Ukázka',
                    'billable' => $billable,
                ]);
            };

            // Předminulý měsíc
            $tracking = $task(['title' => 'Měření objednávek v Google Analytics', 'description' => 'Analytics teď počítá objednávky i jejich hodnotu. Dřív vidělo jen návštěvy.', 'area' => WorkArea::Web, 'status' => TaskStatus::Done, 'planned_for' => $m2, 'done_on' => $day($m2, 18), 'user_id' => $tom]);
            $time($tracking, $tom, $day($m2, 11), 90);
            $time($tracking, $tom, $day($m2, 18), 60);
            $intro = $task(['title' => 'Úvodní konzultace a plán na čtvrt roku', 'description' => 'Prošli jsme sortiment, konkurenci a rozpočet. Z toho vzešel plán, který vidíte níž.', 'area' => WorkArea::Marketing, 'status' => TaskStatus::Done, 'planned_for' => $m2, 'done_on' => $day($m2, 25), 'user_id' => $pavel]);
            $time($intro, $pavel, $day($m2, 25), 90);

            // Minulý měsíc
            $speed = $task(['title' => 'Rychlejší načítání na mobilu', 'description' => 'Úvodní stránka se na mobilu načítala přes 5 sekund. Zmenšili jsme fotky a teď je to pod 2 sekundy.', 'area' => WorkArea::Web, 'status' => TaskStatus::Done, 'planned_for' => $m1, 'done_on' => $day($m1, 14), 'user_id' => $tom]);
            $time($speed, $tom, $day($m1, 6), 120);
            $time($speed, $tom, $day($m1, 14), 60);
            $cart = $task(['title' => 'Oprava košíku na iPhonu', 'description' => 'Tlačítko Objednat bylo na menších displejích schované pod lištou.', 'area' => WorkArea::Web, 'status' => TaskStatus::Done, 'planned_for' => $m1, 'done_on' => $day($m1, 20), 'user_id' => $tom]);
            $time($cart, $tom, $day($m1, 20), 60);
            $time($cart, $tom, $day($m1, 21), 30, billable: false);
            $time(null, $tom, $day($m1, 9), 30, WorkArea::Web);
            $campaign = $task(['title' => 'Kampaň na Facebooku a Instagramu: bylinné čaje', 'description' => 'Nové kreativy a cílení na lidi, kteří už u vás nakoupili.', 'area' => WorkArea::Marketing, 'status' => TaskStatus::Done, 'planned_for' => $m1, 'done_on' => $day($m1, 12), 'user_id' => $pavel]);
            $time($campaign, $pavel, $day($m1, 5), 60);
            $time($campaign, $pavel, $day($m1, 12), 60);
            $profiles = $task(['title' => 'Sjednotit název firmy na Firmy.cz a v Googlu', 'description' => 'Na Firmy.cz byl starý telefon a jiná otevírací doba. Teď všude sedí s webem.', 'area' => WorkArea::Marketing, 'status' => TaskStatus::Done, 'planned_for' => $m1, 'done_on' => $day($m1, 22), 'user_id' => $pavel]);
            $time($profiles, $pavel, $day($m1, 22), 60);
            $time(null, $pavel, $day($m1, 27), 30, WorkArea::Marketing);

            // Tento měsíc
            $wholesale = $task(['title' => 'Stránka pro velkoodběratele', 'description' => 'Kavárny a bistra se ptají na velká balení. Dostanou vlastní stránku s ceníkem a formulářem.', 'area' => WorkArea::Web, 'status' => TaskStatus::InProgress, 'planned_for' => $m0, 'user_id' => $tom]);
            $time($wholesale, $tom, $day($m0, 1), 60);
            $time($wholesale, $tom, $day($m0, 2), 30);
            $newsletter = $task(['title' => 'Newsletter pro stálé zákazníky', 'description' => 'První e-mail s novinkami a slevou na druhý nákup.', 'area' => WorkArea::Marketing, 'status' => TaskStatus::InProgress, 'planned_for' => $m0, 'user_id' => $pavel]);
            $time($newsletter, $pavel, $day($m0, 2), 60);
            $time(null, $pavel, $day($m0, 1), 30, WorkArea::Marketing);

            // Čeká na klienta
            $task(['title' => 'Poslat fotky nových směsí', 'description' => 'Potřebujeme fotky tří nových směsí na produktové stránky. Stačí z mobilu na bílém pozadí.', 'area' => WorkArea::Web, 'status' => TaskStatus::Waiting, 'planned_for' => $m0, 'user_id' => $tom]);
            $task(['title' => 'Schválit text newsletteru', 'description' => 'Návrh máte v e-mailu. Stačí odpovědět, co změnit.', 'area' => WorkArea::Marketing, 'status' => TaskStatus::Waiting, 'planned_for' => $m0, 'user_id' => $pavel]);

            // Plán
            $task(['title' => 'Titulky a popisky kategorií pro Google', 'description' => 'Co se ukáže ve výsledcích hledání. Teď mají všechny kategorie stejný titulek.', 'area' => WorkArea::Web, 'status' => TaskStatus::Planned, 'planned_for' => $m0, 'user_id' => $tom]);
            $task(['title' => 'Recenze zákazníků u produktů', 'description' => 'Hvězdičky u produktů i ve výsledcích Googlu.', 'area' => WorkArea::Web, 'status' => TaskStatus::Planned, 'planned_for' => $m0->addMonthNoOverflow(), 'user_id' => $tom]);
            $task(['title' => 'Feed do Zboží.cz a Google Merchant Center', 'area' => WorkArea::Marketing, 'status' => TaskStatus::Planned, 'planned_for' => $m0->addMonthNoOverflow(), 'user_id' => $pavel]);
            $task(['title' => 'Návod: jak připravit bylinný čaj', 'description' => 'Lidé hledají, jak bylinky louhovat. Návod je přivede na web a rovnou k produktům.', 'area' => WorkArea::Marketing, 'status' => TaskStatus::Planned, 'planned_for' => $m0->addMonthsNoOverflow(2), 'user_id' => $pavel]);
            $task(['title' => 'Věrnostní program', 'area' => WorkArea::Web, 'status' => TaskStatus::Planned]);

            // Cíle a komentáře měsíců
            $client->months()->createMany([
                ['month' => $m2, 'goal' => 'Začít měřit objednávky', 'summary' => 'Do teď Analytics vidělo jen návštěvy. Od poloviny měsíce počítá i objednávky a jejich hodnotu, takže příště uvidíme, odkud nakupující chodí.'],
                ['month' => $m1, 'goal' => 'Zrychlit web na mobilu', 'summary' => "- Z mobilu přichází 7 z 10 návštěv, proto jsme začali rychlostí.\n- Košík padal jen na iPhonech s menším displejem. Jinde fungoval, proto si toho nikdo nevšiml.\n\nVěrnostní program zatím odkládáme. Nejdřív stránka pro velkoodběratele, ta přinese víc."],
                ['month' => $m0, 'goal' => 'Získat první velkoodběratele', 'summary' => 'Stránku pro velkoodběratele stavíme podle dotazů, které vám chodí e-mailem. Ceník do ní doplníme, až ho schválíte.'],
            ]);
        });
    }
}
