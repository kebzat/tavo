<?php

namespace App\Http\Controllers;

use App\Models\Ads\AdReport;
use App\Support\Ads\PerformanceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Sdílený report reklam. Odkaz chrání jen jeho neuhodnutelnost, stejně
 * jako u auditu. Čísla jsou zmrazená v reportu, stránka nesahá na živá data.
 */
class AdReportController extends Controller
{
    public function __invoke(Request $request, string $key): View|RedirectResponse
    {
        // Přihlášený správce vidí i koncept, aby ho před odesláním zkontroloval.
        $report = AdReport::query()
            ->when($request->user() === null, fn ($query) => $query->public())
            ->sharedAs($key)
            ->with('client.crmCompany')
            ->firstOrFail();

        if ($report->slug && $key !== $report->slug) {
            return redirect()->route('ad-report.show', $report->slug, 301);
        }

        if ($request->user() === null) {
            $report->recordView($request->userAgent());
        }

        $view = new PerformanceView($report->snapshot);

        return view('ad-report.show', [
            'report' => $report,
            'view' => $view,
            'tiles' => $view->tiles(),
            'chart' => $view->chart(),
            'funnel' => $view->funnel(),
            'campaigns' => $view->campaigns(),
            'byAccount' => $view->byAccount(),
            'analytics' => $view->analytics(),
            'summary' => filled($report->summary)
                ? new HtmlString(Str::markdown($report->summary, ['html_input' => 'strip', 'allow_unsafe_links' => false]))
                : null,
            'isDraft' => ! $report->is_public,
        ]);
    }
}
