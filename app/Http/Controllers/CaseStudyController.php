<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\CaseStudyCategory;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CaseStudyController extends Controller
{
    public function index(Request $request): View
    {
        $activeSlug = $request->query('kategorie');

        $cases = CaseStudy::published()
            ->ordered()
            ->with('category')
            ->when($activeSlug, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $activeSlug)))
            ->get();

        return view('case-studies.index', [
            'cases' => $cases,
            // Kategorie bez zveřejněné reference nezobrazujeme, ať filtr nevede na prázdno.
            'categories' => CaseStudyCategory::ordered()
                ->whereHas('caseStudies', fn ($q) => $q->published())
                ->get(),
            'activeSlug' => $activeSlug,
        ]);
    }

    public function show(string $slug): View
    {
        $case = CaseStudy::published()->where('slug', $slug)->with('category')->firstOrFail();

        return view('case-studies.show', [
            'case' => $case,
            'gallery' => $case->galleryImages(),
            'next' => $case->next(),
            'schema' => [
                StructuredData::breadcrumbs([
                    'Úvod' => route('home'),
                    'Reference' => route('cases.index'),
                    $case->title => route('cases.show', $case->slug),
                ]),
            ],
        ]);
    }
}
