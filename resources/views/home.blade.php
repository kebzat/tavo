<x-layout.app>
    <x-home.hero :home="$home" />

    @if ($trustItems->isNotEmpty())
        <x-home.trust-bar :items="$trustItems" />
    @endif

    <x-home.problem :home="$home" />
    <x-home.situations :home="$home" />

    {{-- Hned za „Web už máme a chceme z něj dostat víc": konkrétní ukázka
         a zároveň předěl mezi textovými sekcemi. --}}
    @if ($latest)
        <x-home.latest :latest="$latest" />
    @endif

    <x-home.services :home="$home" :services="$services" />
    <x-home.cases :home="$home" :cases="$cases" />

    @if ($clientLogos->isNotEmpty())
        <x-home.client-logos :logos="$clientLogos" />
    @endif

    <x-home.loop :home="$home" :items="$loopItems" />
    <x-home.founders :home="$home" :founders="$founders" :photo="$foundersPhoto" />
    <x-home.process :home="$home" :steps="$processSteps" />

    @if ($pricingPlans->isNotEmpty())
        <x-home.pricing :home="$home" :plans="$pricingPlans" />
    @endif

    <x-cta-band
        id="kontakt"
        :eyebrow="$home->cta_eyebrow"
        :title="$home->cta_title"
        :perex="$home->cta_perex"
        :form="true" />
</x-layout.app>
