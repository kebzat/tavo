@props([
    'images' => [],   // pole z ResponsiveImage::make() — src, srcset, width, height, alt
])

@php
    $images = collect($images)->values();
    $count = $images->count();
@endphp

@if ($count)
    <div x-data="tavoGallery(@js($images))"
         @keydown.escape.window="closeLightbox()"
         class="w-full">

        {{-- Rám slideru — pevný poměr, ať tečky pod ním nepodskakují mezi obrázky. --}}
        <div class="group relative aspect-[4/3] overflow-hidden rounded-card bg-gradient-to-br from-sand-100 to-sand-400">
            <template x-for="(image, i) in images" :key="i">
                {{--
                    Skrytý snímek se vypne i pro čtečku a klávesnici (`inert`),
                    aby se tabulátorem procházel jen ten, který je opravdu vidět.
                --}}
                <button type="button"
                        @click="openLightbox(i)"
                        x-bind:inert="i !== index"
                        class="absolute inset-0 cursor-zoom-in transition-opacity duration-500 ease-tavo"
                        :class="i === index ? 'opacity-100' : 'pointer-events-none opacity-0'"
                        :aria-label="`Zvětšit obrázek: ${image.alt}`">
                    {{-- Galerie stojí v hlavičce hned vedle nadpisu, takže první
                         snímek je na první obrazovce a čeká se na něj. --}}
                    <img :src="image.src"
                         :srcset="image.srcset"
                         sizes="(min-width: 861px) 42vw, 88vw"
                         :alt="image.alt"
                         :width="image.width"
                         :height="image.height"
                         :loading="i === 0 ? 'eager' : 'lazy'"
                         :fetchpriority="i === 0 ? 'high' : 'auto'"
                         decoding="async"
                         class="h-full w-full object-cover">
                </button>
            </template>

            {{-- Šipky — jen když je co listovat; zjeví se na hoveru rámu. --}}
            <template x-if="images.length > 1">
                <div>
                    <button type="button"
                            @click="prev()"
                            aria-label="Předchozí obrázek"
                            class="absolute top-1/2 left-3 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-pill bg-cream/85 text-xl text-ink opacity-0 shadow-sm transition duration-300 group-hover:opacity-100 hover:bg-cream focus-visible:opacity-100">
                        <span aria-hidden="true">‹</span>
                    </button>
                    <button type="button"
                            @click="next()"
                            aria-label="Další obrázek"
                            class="absolute top-1/2 right-3 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-pill bg-cream/85 text-xl text-ink opacity-0 shadow-sm transition duration-300 group-hover:opacity-100 hover:bg-cream focus-visible:opacity-100">
                        <span aria-hidden="true">›</span>
                    </button>
                </div>
            </template>
        </div>

        {{--
            Tečky: neaktivní kroužek, aktivní se protáhne do cihlové čárky.
            Nejsou to záložky (role="tab" chce navázaný tabpanel), ale skupina
            tlačítek, která přepínají jeden a týž snímek — proto `group`
            a `aria-current`.
        --}}
        <div x-show="images.length > 1"
             class="mt-6 flex items-center justify-center gap-2"
             role="group"
             aria-label="Obrázky projektu">
            <template x-for="(image, i) in images" :key="i">
                <button type="button"
                        @click="go(i)"
                        :aria-current="i === index ? 'true' : 'false'"
                        :aria-label="`Obrázek ${i + 1} z ${images.length}`"
                        class="h-2 rounded-pill transition-all duration-300 ease-tavo"
                        :class="i === index ? 'w-7 bg-brick' : 'w-2 bg-ink/20 hover:bg-ink/40'"></button>
            </template>
        </div>

        {{--
            Lightbox — plný obrázek bez ořezu. Šipky posouvají jen tady, aby
            klávesnice nepřepínala slider schovaný někde mimo obrazovku.
        --}}
        <div x-show="lightbox"
             x-cloak
             x-transition.opacity
             @keydown.arrow-left.window="lightbox && prev()"
             @keydown.arrow-right.window="lightbox && next()"
             @keydown.tab.prevent="trapFocus($event)"
             role="dialog"
             aria-modal="true"
             aria-label="Zvětšený obrázek"
             class="fixed inset-0 z-[70] flex items-center justify-center bg-ink/95 p-4 menu:p-10"
             @click.self="closeLightbox()">

            <button type="button"
                    @click="closeLightbox()"
                    x-ref="close"
                    aria-label="Zavřít"
                    class="absolute top-4 right-4 flex h-12 w-12 items-center justify-center rounded-pill bg-cream/10 text-2xl text-cream transition hover:bg-cream hover:text-ink menu:top-6 menu:right-6">
                <span aria-hidden="true">×</span>
            </button>

            <template x-if="images.length > 1">
                <button type="button"
                        @click="prev()"
                        aria-label="Předchozí obrázek"
                        class="absolute left-3 flex h-12 w-12 items-center justify-center rounded-pill bg-cream/10 text-2xl text-cream transition hover:bg-cream hover:text-ink menu:left-6">
                    <span aria-hidden="true">‹</span>
                </button>
            </template>

            <template x-if="images.length > 1">
                <button type="button"
                        @click="next()"
                        aria-label="Další obrázek"
                        class="absolute right-3 flex h-12 w-12 items-center justify-center rounded-pill bg-cream/10 text-2xl text-cream transition hover:bg-cream hover:text-ink menu:right-6">
                    <span aria-hidden="true">›</span>
                </button>
            </template>

            <figure class="m-0 flex max-h-full max-w-full flex-col items-center gap-4">
                <img :src="current.src"
                     :srcset="current.srcset"
                     sizes="90vw"
                     :alt="current.alt"
                     class="max-h-[80vh] w-auto max-w-full rounded-card object-contain">
                <figcaption class="text-center text-[13px] text-cream/70">
                    <span x-text="current.alt"></span>
                    <template x-if="images.length > 1">
                        <span class="ml-2 text-cream/45" x-text="`${index + 1} / ${images.length}`"></span>
                    </template>
                </figcaption>
            </figure>
        </div>
    </div>
@endif
