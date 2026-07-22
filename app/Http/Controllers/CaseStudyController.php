<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\CaseStudyCategory;
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
            'categories' => CaseStudyCategory::ordered()->get(),
            'activeSlug' => $activeSlug,
        ]);
    }

    public function show(string $slug): View
    {
        $case = CaseStudy::published()->where('slug', $slug)->with('category')->firstOrFail();

        return view('case-studies.show', [
            'case' => $case,
            'next' => $case->next(),
        ]);
    }
}
