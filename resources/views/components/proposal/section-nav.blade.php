@props([
    'nav' => [],
    'company' => null,
])

{{--
    Lepivé menu sdílené nabídky. Objeví se, jakmile čtenář odscrolluje z úvodu,
    a zvýrazní sekci, ve které právě je. Na mobilu se položky posouvají do
    strany, aktivní se sama doroluje na oči. Bez JS se nezobrazí vůbec,
    tlačítka v úvodu fungují i tak.

    Lepivé nadpisy sekcí (menu:top-30 v proposal/show) počítají s výškou
    lišty kolem 63 px. Když lišta zvýší, posuňte je taky.
--}}
@if ($nav)
    <nav aria-label="Sekce stránky"
         x-data="tavoSectionNav"
         x-show="visible"
         x-transition.opacity.duration.200ms
         style="display: none"
         class="fixed inset-x-0 top-0 z-50 border-b border-ink/10 bg-cream/95 backdrop-blur">
        <div class="section-x">
            <div class="container-tavo flex items-center gap-6">
                @if ($company)
                    <a href="#obsah" class="hidden shrink-0 text-sm font-extrabold tracking-[-.01em] text-ink menu:block">{{ $company }}</a>
                @endif

                {{-- Na mobilu se položky nevejdou. Ztmavení u okraje se šipkou ukazuje,
                     že lištou jde posunout, a zmizí, když už dál nejde. --}}
                <div class="relative -mx-[6vw] min-w-0 flex-1 menu:mx-0">
                    <ul x-ref="list" @scroll.passive="zmerOkraje()" class="flex gap-2 overflow-x-auto px-[6vw] py-3 [scrollbar-width:none] menu:justify-end menu:px-0 [&::-webkit-scrollbar]:hidden">
                        @foreach ($nav as $item)
                            <li class="shrink-0">
                                <a href="#{{ $item['id'] }}"
                                   data-section="{{ $item['id'] }}"
                                   :aria-current="active === @js($item['id']) ? 'location' : false"
                                   class="block rounded-pill border border-ink/14 px-4 py-2 text-sm font-bold whitespace-nowrap text-ink transition-colors duration-200 ease-tavo hover:border-ink aria-[current=location]:border-ink aria-[current=location]:bg-ink aria-[current=location]:text-cream">
                                    {{ $item['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <button type="button" tabindex="-1" aria-hidden="true"
                            x-show="muzeDoleva" x-transition.opacity.duration.200ms
                            @click="posun(-1)"
                            class="absolute inset-y-0 left-0 flex w-16 items-center justify-start bg-gradient-to-r from-cream via-cream/90 to-transparent pl-3">
                        <span class="flex size-7 items-center justify-center rounded-full bg-ink text-sm font-bold text-cream">‹</span>
                    </button>
                    <button type="button" tabindex="-1" aria-hidden="true"
                            x-show="muzeDoprava" x-transition.opacity.duration.200ms
                            @click="posun(1)"
                            class="absolute inset-y-0 right-0 flex w-16 items-center justify-end bg-gradient-to-l from-cream via-cream/90 to-transparent pr-3">
                        <span class="flex size-7 items-center justify-center rounded-full bg-ink text-sm font-bold text-cream">›</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>
@endif
