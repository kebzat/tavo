<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Stav webu</x-slot>
        <x-slot name="description">Přehled prostředí, ve kterém web běží.</x-slot>

        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach ($this->systemInfo() as $label => $value)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Kdy co použít</x-slot>

        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <li><strong class="text-gray-950 dark:text-white">Spustit migrace</strong> — po nasazení verze, která přidává nová pole nebo tabulky.</li>
            <li><strong class="text-gray-950 dark:text-white">Obnovit cache</strong> — když se úpravy obsahu nebo nastavení na webu neprojevují.</li>
            <li><strong class="text-gray-950 dark:text-white">Propojit soubory</strong> — když se místo nahraných obrázků zobrazuje prázdné místo.</li>
        </ul>
    </x-filament::section>
</x-filament-panels::page>
