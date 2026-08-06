@props([
    'item',
    'token',
])

{{--
    Jeden řádek checklistu. Políčko sedí v opravdovém formuláři, takže
    při vypnutém JS se odešle klasicky a stránka se překreslí. Alpine
    odeslání jen odchytí a pošle ho na pozadí.
--}}
<li x-data="tavoChecklistItem({{ $item->status->isFinished() ? 'true' : 'false' }})" class="py-3">
    <div class="flex items-start gap-4">
        <form method="POST"
              action="{{ route('checklist.toggle', [$token, $item]) }}"
              @submit.prevent="prepnout"
              class="pt-0.5">
            @csrf
            <button type="submit"
                    :disabled="odesila"
                    :aria-pressed="hotovo ? 'true' : 'false'"
                    aria-label="Odškrtnout: {{ $item->title }}"
                    :class="hotovo ? 'border-brick bg-brick text-cream' : 'border-ink/25 bg-transparent text-transparent hover:border-brick'"
                    class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-md border text-[13px] leading-none font-bold transition duration-200 ease-tavo">
                <span aria-hidden="true">✓</span>
            </button>
        </form>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                <p class="text-body-lg font-semibold transition-colors duration-200 ease-tavo"
                   :class="hotovo ? 'text-muted line-through' : 'text-ink'">
                    {{ $item->title }}
                </p>

                <span class="rounded-pill px-2.5 py-1 text-[11px] font-bold tracking-wide {{ $item->priority->badgeClasses() }}">
                    {{ $item->priority->getLabel() }}
                </span>

                @if ($item->description)
                    <button type="button"
                            @click="rozbaleno = ! rozbaleno"
                            :aria-expanded="rozbaleno ? 'true' : 'false'"
                            class="cursor-pointer text-[11px] font-bold text-muted underline underline-offset-4 transition-colors duration-200 ease-tavo hover:text-brick">
                        <span x-text="rozbaleno ? 'skrýt' : 'proč'"></span>
                    </button>
                @endif
            </div>

            @if ($item->description)
                <p x-show="rozbaleno" x-cloak class="mt-2 max-w-[70ch] text-perex text-muted">
                    {{ $item->description }}
                </p>
            @endif
        </div>
    </div>
</li>
