@props(['code', 'title', 'text'])

<x-layout.app :title="$title">
    <section class="section-x flex min-h-screen items-center justify-center py-40 text-center">
        <div class="container-tavo max-w-[700px]">
            <div class="text-hero font-extrabold tracking-[-.03em] text-brick">{{ $code }}</div>
            <h1 class="text-h2 mt-4 mb-6 font-extrabold tracking-[-.02em]">{{ $title }}</h1>
            <p class="text-perex mx-auto mb-10 max-w-[42ch] text-body">{{ $text }}</p>

            <div class="flex flex-wrap justify-center gap-3.5">
                <x-btn :href="route('home')" variant="primary">Zpět na úvod</x-btn>
                <x-btn :href="route('cases.index')" variant="ghost">Prohlédnout reference</x-btn>
            </div>
        </div>
    </section>
</x-layout.app>
