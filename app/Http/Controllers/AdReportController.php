<?php

namespace App\Http\Controllers;

use App\Models\Ads\AdReport;
use App\Support\Ads\PerformanceView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            $report->recordView($request->userAgent(), $request->ip());
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
            'summary' => self::markdown($report->summary),
            'facts' => self::markdown($report->facts),
            'causes' => self::markdown($report->causes),
            'worked' => self::markdown($report->worked),
            'notWorked' => self::markdown($report->not_worked),
            'changes' => $view->changes(),
            'targets' => $view->targets(),
            'recommendations' => self::markdown($report->recommendations),
            'sources' => collect($report->sources ?? [])->map(fn (array $source): array => $source + [
                'synced_label' => filled($source['synced_at'] ?? null) ? Carbon::parse($source['synced_at'])->timezone('Europe/Prague')->format('j. n. Y H:i') : null,
            ])->all(),
            'isDraft' => ! $report->is_public,
        ]);
    }

    private static function markdown(?string $text): ?HtmlString
    {
        return filled($text) ? new HtmlString(Str::markdown($text, ['html_input' => 'strip', 'allow_unsafe_links' => false])) : null;
    }
}
