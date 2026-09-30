{{--
    Musí stát v <head> před všemi ostatními skripty. Google Consent Mode
    potřebuje výchozí „zamítnuto" dřív, než se cokoliv od Googlu načte,
    jinak by první měření odešlo bez souhlasu.

    Samotné měřicí kódy tu nejsou. Načte je až resources/js/consent.js
    podle toho, co návštěvník v cookie liště povolí.
--}}
@if ($tracking->needsConsent())
    <script>
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { dataLayer.push(arguments); };
        gtag('consent', 'default', {
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied',
            analytics_storage: 'denied',
            wait_for_update: 500,
        });
        window.__tavoTracking = @json($tracking->config());
    </script>
@endif
