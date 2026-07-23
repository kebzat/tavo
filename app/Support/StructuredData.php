<?php

namespace App\Support;

use App\Models\Founder;
use App\Models\Service;
use App\Settings\ContactSettings;
use App\Settings\SeoSettings;
use App\Settings\SiteSettings;

/**
 * Strukturovaná data pro vyhledávače. Držíme je v PHP, ne v šabloně, ať se dá
 * na výstup napsat test a ať v Blade nezůstávají @php bloky s logikou.
 *
 * ProfessionalService (podtyp LocalBusiness) je tu kvůli lokálnímu vyhledávání.
 * Bez adresy a areaServed nás Google nespojí s Hradcem Králové.
 */
class StructuredData
{
    public const ORGANIZATION_ID = '#organizace';

    /** @return array<string, mixed> */
    public static function professionalService(): array
    {
        $site = app(SiteSettings::class);
        $seo = app(SeoSettings::class);
        $contact = app(ContactSettings::class);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            '@id' => url('/').self::ORGANIZATION_ID,
            'name' => $site->brand_name,
            'url' => url('/'),
            'logo' => url('/images/tavo-logo-dark.svg'),
            'description' => $seo->default_description,
            'email' => $contact->email,
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $contact->address ?: 'Hradec Králové',
                'addressRegion' => 'Královéhradecký kraj',
                'addressCountry' => 'CZ',
            ],
            'areaServed' => [
                ['@type' => 'City', 'name' => 'Hradec Králové'],
                ['@type' => 'AdministrativeArea', 'name' => 'Královéhradecký kraj'],
                ['@type' => 'Country', 'name' => 'Česko'],
            ],
            'knowsLanguage' => 'cs',
        ];

        if ($contact->phone && ! str_contains($contact->phone, '000 000 000')) {
            $data['telephone'] = $contact->phone;
        }

        $founders = Founder::orderBy('order_column')->get();

        if ($founders->isNotEmpty()) {
            $data['founder'] = $founders
                ->map(fn (Founder $founder) => array_filter([
                    '@type' => 'Person',
                    'name' => $founder->name,
                    'jobTitle' => $founder->role_label,
                    'url' => $founder->external_url,
                ]))
                ->all();
        }

        $sameAs = collect($contact->socials)
            ->pluck('url')
            ->merge($founders->pluck('external_url'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        $services = Service::published()->ordered()->get();

        if ($services->isNotEmpty()) {
            $data['hasOfferCatalog'] = [
                '@type' => 'OfferCatalog',
                'name' => 'Služby',
                'itemListElement' => $services
                    ->map(fn (Service $service) => array_filter([
                        '@type' => 'Offer',
                        'itemOffered' => array_filter([
                            '@type' => 'Service',
                            'name' => $service->title,
                            'description' => $service->excerpt,
                            'url' => $service->url(),
                        ]),
                    ]))
                    ->all(),
            ];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    public static function service(Service $service): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $service->title,
            'description' => $service->seo_description ?: $service->excerpt,
            'url' => $service->url(),
            'serviceType' => $service->title,
            'provider' => ['@id' => url('/').self::ORGANIZATION_ID],
            'areaServed' => [
                ['@type' => 'City', 'name' => 'Hradec Králové'],
                ['@type' => 'Country', 'name' => 'Česko'],
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $items  název => URL, v pořadí od úvodní stránky
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        $position = 0;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)
                ->map(function (string $url, string $name) use (&$position) {
                    $position++;

                    return [
                        '@type' => 'ListItem',
                        'position' => $position,
                        'name' => $name,
                        'item' => $url,
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
