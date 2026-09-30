@props([
    'nav' => [],
    'company' => null,
])

{{--
    Lepivé menu sdílené nabídky. Objeví se, jakmile čtenář odscrolluje z úvodu,
    a zvýrazní sekci, ve které právě je. Na mobilu se položky posouvají do
    strany, aktivní se sama doroluje na oči. Bez JS se nezobrazí vůbec,
    tlačítka v úvodu fungují i tak.
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

                <ul x-ref="list" class="-mx-[6vw] flex flex-1 gap-2 overflow-x-auto px-[6vw] py-3 [scrollbar-width:none] menu:mx-0 menu:justify-end menu:px-0 [&::-webkit-scrollbar]:hidden">
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
            </div>
        </div>
    </nav>
@endif
