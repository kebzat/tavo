@props([
    'example',
    'light' => false,
])

{{-- Odkaz z ukázky (třeba „Další tipy pro e-shopy“). Na světlém pruhu by
     krémové tlačítko splynulo s pozadím, proto tam je tmavé. --}}
<a href="{{ $example['link_url'] }}" target="_blank" rel="noopener"
   {{ $attributes->class([
       'group inline-flex items-center gap-2 rounded-pill px-5 py-3 text-sm font-bold transition duration-300 ease-tavo hover:-translate-y-0.5 hover:bg-brick hover:text-cream',
       'bg-cream text-ink' => ! $light,
       'bg-ink text-cream' => $light,
   ]) }}>
    {{ $example['link_label'] }}
    <span aria-hidden="true" class="transition-transform duration-300 ease-tavo group-hover:translate-x-0.5">↗</span>
</a>
