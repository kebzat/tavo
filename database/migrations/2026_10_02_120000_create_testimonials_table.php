<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recenze klientů na homepage (#recenze). Proklik na ně vede z „5,0 na
 * Googlu" v pruhu s čísly.
 *
 * Výchozí obsah jsou recenze z pavelvcelis.cz a tomaskebza.cz, doslova
 * a střídavě Pavel a Tom. Vynechaná je recenze Pavla na Toma: na společném
 * webu by to byla pochvala od sebe sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_column')->default(0)->index();
            $table->text('text');
            $table->string('author');
            $table->string('role')->nullable();
            $table->string('person', 20)->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('testimonials')->insert(array_map(
            fn (array $row, int $index) => $row + ['order_column' => $index + 1, 'published' => true, 'created_at' => $now, 'updated_at' => $now],
            $this->testimonials(),
            array_keys($this->testimonials()),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }

    /** @return array<int, array{text: string, author: string, role: ?string, person: string, source_url: ?string}> */
    private function testimonials(): array
    {
        return [
            [
                'person' => 'pavel',
                'author' => 'Marek Bezdíček',
                'role' => 'Zakladatel, svetcejlonu.cz',
                'source_url' => 'https://www.svetcejlonu.cz/',
                'text' => 'Jsem rád, že jsem si pro marketing svého e-shopu vybral právě Pavla. Oceňuji jeho profesionální a zároveň lidský přístup i schopnost dobře se naladit na projekt. Už po první kampani byly vidět pěkné výsledky, což mě příjemně překvapilo. Pavel ví, co dělá, a jeho zkušenosti i rady mi pomohly ušetřit čas a posunout věci správným směrem. Doporučuji.',
            ],
            [
                'person' => 'tom',
                'author' => 'ChrudimLab',
                'role' => 'zubní laboratoř, Chrudim',
                'source_url' => 'https://www.chrudimlab.cz/',
                'text' => 'S Tomášem byla skvělá spolupráce od začátku až do konce. Rychle pochopil, co potřebujeme, a navrhl řešení, které nám dnes šetří spoustu času. Oceňuju hlavně jeho samostatnost a to, že nad věcmi přemýšlí i z pohledu běžného uživatele. Určitě doporučuji.',
            ],
            [
                'person' => 'pavel',
                'author' => 'Miroslav Hlubuček',
                'role' => 'Zakladatel, ceskalouka.cz',
                'source_url' => 'https://www.ceskalouka.cz/',
                'text' => 'Spolupráce s Pavlem nám ušetřila měsíce, možná roky trápení v našich začátcích. Před třemi lety jsme otevřeli diskuzi na téma zviditelnění naší městské farmy, ale postupně se aktivity rozšířily na supervizi a pomoc s celým digitálním marketingem. Oceňuji hlavně byznysový přístup k aktivitám a důraz na detail u online kampaní.',
            ],
            [
                'person' => 'tom',
                'author' => 'THEM CARS',
                'role' => 'prodej prémiových vozů',
                'source_url' => 'https://themcars.cz/',
                'text' => 'S Tomášem jsme řešili nový web i systém pro správu nabídky vozů. Všechno funguje tak, jak jsme potřebovali, a auta si dnes můžeme přidávat a upravovat sami. Dobrá komunikace, rychlé řešení požadavků a spolehlivá spolupráce. Můžeme doporučit.',
            ],
            [
                'person' => 'pavel',
                'author' => 'Diana Hornychová',
                'role' => 'Marketing manager, Bigtime.agency',
                'source_url' => 'https://www.bigtime.agency/',
                'text' => 'S Pavlem jsme navázali spolupráci v druhé polovině loňského roku. Hledali jsme někoho, kdo je profesionální, zodpovědný a nezalekne se správy kampaní pro klienty z různých částí světa. Jsem moc ráda, že jsme na základě doporučení oslovili právě jeho. Na Pavla je 100% spolehnutí, oceňujeme jeho flexibilitu i bezproblémovou domluvu na čemkoliv.',
            ],
            [
                'person' => 'tom',
                'author' => 'Včely Uhersko',
                'role' => 'rodinné včelařství',
                'source_url' => 'https://vcelyuhersko.cz/',
                'text' => 'Tomáš nám pomáhal s celou prezentací značky, od loga až po web. Spolupráce byla příjemná, všechno jsme dokázali normálně probrat a výsledek dopadl přesně podle našich představ. Pokud někdo hledá spolehlivého člověka na web, můžu Tomáše doporučit.',
            ],
            [
                'person' => 'pavel',
                'author' => 'Václav Sekvard',
                'role' => 'Senior PPC specialista',
                'source_url' => null,
                'text' => 'S Pavlem spolupracuji na klientském projektu již druhý rokem. Je to profík na sociální sítě se vším všudy. Na všem co je potřeba se vždy domluvíme, drží termíny a má výsledky. Velmi rád ho doporučuji dále.',
            ],
            [
                'person' => 'tom',
                'author' => 'Aleš Malinský',
                'role' => 'realitní makléř',
                'source_url' => null,
                'text' => 'Potřeboval jsem jednoduchý a přehledný web, který zvládnu sám spravovat, a přesně to jsem dostal. Domluva byla rychlá, bez zbytečných komplikací, a kdykoliv jsem později něco potřeboval, Tomáš byl ochotný pomoct. Za mě určitě doporučuji.',
            ],
            [
                'person' => 'pavel',
                'author' => 'Jakub Horák',
                'role' => 'Social Media, wood.cz',
                'source_url' => 'https://www.wood.cz/',
                'text' => 'Super spolupráce, určitě doporučuji! Kromě profi přístupu nechybí ani humor, takže se u práce rozhodně nebudete nudit.',
            ],
            [
                'person' => 'tom',
                'author' => 'SH Mediace',
                'role' => 'mimosoudní řešení sporů',
                'source_url' => 'https://shmediace.cz/',
                'text' => 'Spolupráci s Tomášem můžu určitě doporučit. Od začátku se snažil pochopit, jak má web působit a co je pro mě důležité. Výsledek je přehledný, profesionální a zároveň nepůsobí neosobně, což pro mě bylo zásadní.',
            ],
            [
                'person' => 'tom',
                'author' => 'SVJ U Stadionu 729',
                'role' => 'výbor společenství vlastníků',
                'source_url' => 'https://svjustadionu729.cz/',
                'text' => 'Potřebovali jsme jednoduchý web pro naše SVJ a hlavně uzavřenou část s dokumenty pro vlastníky. Tomáš nám všechno připravil srozumitelně a ukázal, jak si web spravovat sami. Spolupráce proběhla bez problémů a s výsledkem jsme spokojení.',
            ],
            [
                'person' => 'tom',
                'author' => 'Natěračství Balcar',
                'role' => 'natěračské a výškové práce',
                'source_url' => 'https://nateracstvi-balcar.cz/',
                'text' => 'S Tomášem byla dobrá a rychlá domluva. Vysvětlil mi všechno normálně a srozumitelně a vytvořil web, který si dokážu bez problémů spravovat i sám. Kdybych znovu řešil web, určitě bych se na něj obrátil.',
            ],
            [
                'person' => 'tom',
                'author' => 'GEOMA HJ',
                'role' => 'geodetické práce',
                'source_url' => 'https://geomahj.cz/',
                'text' => 'S Tomášem jsme byli se spoluprací spokojení. Dokázal naše služby převést do přehledného webu, který je srozumitelný i pro člověka, který se v geodezii běžně nepohybuje. Všechno probíhalo rychle a bez zbytečných komplikací. Určitě doporučujeme.',
            ],
        ];
    }
};
