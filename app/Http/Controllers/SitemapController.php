<?php

namespace App\Http\Controllers;

use App\Models\CaseStudy;
use App\Models\Page;
use App\Models\Service;
use App\Settings\SeoSettings;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => route('cases.index'), 'priority' => '0.8', 'changefreq' => 'weekly'],
        ];

        foreach (Service::published()->where('has_detail_page', true)->ordered()->get() as $service) {
            $urls[] = [
                'loc' => route('services.show', $service->slug),
                'lastmod' => $service->updated_at?->toAtomString(),
                'priority' => '0.8',
                'changefreq' => 'monthly',
            ];
        }

        foreach (CaseStudy::published()->ordered()->get() as $case) {
            $urls[] = [
                'loc' => route('cases.show', $case->slug),
                'lastmod' => $case->updated_at?->toAtomString(),
                'priority' => '0.7',
                'changefreq' => 'monthly',
            ];
        }

        foreach (Page::published()->get() as $page) {
            $urls[] = [
                'loc' => route('pages.show', $page->slug),
                'lastmod' => $page->updated_at?->toAtomString(),
                'priority' => '0.3',
                'changefreq' => 'yearly',
            ];
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    public function robots(SeoSettings $seo): Response
    {
        $body = $seo->indexable
            ? "User-agent: *\nDisallow: /admin\nDisallow: /poptavka\n\nSitemap: ".url('/sitemap.xml')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
