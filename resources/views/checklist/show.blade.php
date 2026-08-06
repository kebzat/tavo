{{--
    Rozcestník sdíleného checklistu. Kategorie jako karty, teprve za nimi
    samotné odškrtávání. Vzhled jde celý z tokenů v resources/css/app.css.
--}}
<x-layout.document :title="$checklist->name" :eyebrow="$checklist->client?->name">

    <x-checklist.hero :checklist="$checklist" :progress="$progress" />

    <section class="section-x section-y-sm">
        <div class="container-tavo grid gap-5 sm:grid-cols-2">
            @foreach ($checklist->categories as $category)
                <a href="{{ route('checklist.category', [$checklist->public_token, $category->slug]) }}"
                   data-reveal
                   class="group flex flex-col rounded-card border border-ink/14 bg-cream p-7 transition duration-300 ease-tavo hover:-translate-y-1 hover:border-ink/30 menu:p-9">

                    <div class="flex items-start justify-between gap-4">
                        <h2 class="text-h3-sm font-extrabold tracking-[-.02em] text-ink transition-colors duration-300 ease-tavo group-hover:text-brick">
                            {{ $category->title }}
                        </h2>
                        <span aria-hidden="true" class="mt-1 text-xl text-muted transition-transform duration-300 ease-tavo group-hover:translate-x-1 group-hover:text-brick">→</span>
                    </div>

                    @if ($category->description)
                        <p class="mt-3 flex-1 text-perex text-muted">{{ $category->description }}</p>
                    @endif

                    <x-checklist.progress-bar :progress="$category->progress()" class="mt-7" />
                </a>
            @endforeach
        </div>
    </section>
</x-layout.document>
