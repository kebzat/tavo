@props([
    'links' => [],
])

{{-- Odkazy mezi sdílenými dokumenty jednoho klienta (checklist ↔ audit).
     Sedí v tmavé hlavičce, proto světlé tlačítko. Bez odkazů se nevykreslí. --}}
@if ($links)
    <ul {{ $attributes->class('flex flex-wrap gap-3') }}>
        @foreach ($links as $link)
            <li>
                <a href="{{ $link['url'] }}"
                   class="group inline-flex items-center gap-2 rounded-pill bg-cream px-5 py-3 text-sm font-bold text-ink transition duration-300 ease-tavo hover:-translate-y-0.5 hover:bg-brick hover:text-cream">
                    {{ $link['label'] }}
                    <span aria-hidden="true" class="transition-transform duration-300 ease-tavo group-hover:translate-x-0.5">→</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
