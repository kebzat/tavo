<x-layout.app>
    <x-home.hero :home="$home" />
    <x-home.problem :home="$home" />
    <x-home.situations :home="$home" />
    <x-home.services :home="$home" :services="$services" />
    <x-home.cases :home="$home" :cases="$cases" />
    <x-home.loop :home="$home" :items="$loopItems" />
    <x-home.founders :home="$home" :founders="$founders" />
    <x-home.process :home="$home" :steps="$processSteps" />

    <x-cta-band
        id="kontakt"
        :eyebrow="$home->cta_eyebrow"
        :title="$home->cta_title"
        :perex="$home->cta_perex"
        :form="true" />
</x-layout.app>
