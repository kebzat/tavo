{{--
    Nabídka „Přidat blok" u statických stránek. Filament ji sází jako seznam
    „ikona + popisek"; my místo ikony podstrčíme drátěnku bloku (viz PageForm::preview)
    a tímhle stylem z řádku uděláme kartu s náhledem nahoře a názvem pod ním.

    Pravidla míří jen na položky, které opravdu mají obrázek, takže ostatní
    rozbalovací nabídky v administraci zůstávají nedotčené.
--}}
{{--
    Pozor: Filament dává třídu `fi-icon` rovnou na `<img>`, žádný obal kolem něj není.
    Selektor proto musí mířit na `img.fi-icon`, jinak nemá co chytit.
--}}
<style>
    .fi-dropdown-list-item:has(img.fi-icon) {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
        padding: 0.5rem;
    }

    .fi-dropdown-list-item img.fi-icon {
        display: block;
        width: 100%;
        height: auto;
        max-width: none;
        border-radius: 0.375rem;
        outline: 1px solid rgb(0 0 0 / 0.08);
        outline-offset: -1px;
    }

    .fi-dropdown-list-item:has(img.fi-icon) .fi-dropdown-list-item-label {
        text-align: center;
        font-weight: 600;
        line-height: 1.3;
    }

    .dark .fi-dropdown-list-item img.fi-icon {
        outline-color: rgb(255 255 255 / 0.12);
    }
</style>
