{{--
    Poděkování po odeslání poptávky. Vykresluje se dvakrát: ze serveru pro
    prohlížeč bez JS, který sem dojde přesměrováním, a schované pod `x-show`
    pro odeslání na pozadí.

    role="status" — čtečka potvrzení přečte, i když se na něj uživatel nedívá.
    tabindex="-1" — po odeslání na něj skáče fokus, viz tavoLeadForm v app.js.
--}}
<div role="status" tabindex="-1"
     class="rounded-card border-[1.5px] border-ink/20 bg-cream p-8 text-center">
    <div class="text-h3 font-extrabold tracking-[-.02em]">Díky, máme to.</div>

    <p class="mx-auto mt-4 mb-6 max-w-[46ch] text-[16px] leading-[1.6] text-body">
        Ozveme se vám na e-mail, který jste vyplnili.
    </p>

    <x-contact-people class="justify-center" />
</div>
