<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

/**
 * Společné nastavení polí, do kterých se v administraci nahrává obrázek.
 *
 * Řeší dvě věci, na kterých se dá naletět:
 *
 * 1. Soubor větší než `upload_max_filesize` zahodí PHP dřív, než se k němu
 *    aplikace dostane. Livewire nedostane odpověď a Filament zůstane viset
 *    na „probíhá upload" — bez chybové hlášky, donekonečna. Limit si proto
 *    bereme přímo z konfigurace PHP a předáváme ho poli: to ho ohlídá už
 *    v prohlížeči a napíše, co je špatně.
 *
 * Filament umí obrázek zmenšit ještě v prohlížeči (`imageResizeMode` a spol.),
 * což by u exportů z Figmy dávalo smysl — web stejně nepoužije nic širšího než
 * 1920 px. Zatím to tu ale není: nepodařilo se mi to vyzkoušet v prohlížeči
 * (automatizovaný Playwright upload ve Filamentu nedotáhne ani u 2 KB souboru)
 * a nasazovat nevyzkoušené zpracování obrázku do pole, které si uživatel
 * stěžuje, že se zasekává, je špatný nápad. Až bude jisté, že nahrávání jede,
 * jsou to čtyři řádky.
 */
final class ImageUpload
{
    /** Strop Livewire na dočasné soubory (výchozí pravidlo `max:12288`). */
    private const LIVEWIRE_MAX_KILOBYTES = 12288;

    /** Pole navázané na MediaLibrary (náhledy referencí, fotky zakladatelů). */
    public static function media(string $name): SpatieMediaLibraryFileUpload
    {
        return self::configure(SpatieMediaLibraryFileUpload::make($name)->image());
    }

    /** Pole ukládající soubor rovnou na disk (bloky obsahu, nastavení SEO). */
    public static function file(string $name): FileUpload
    {
        return self::configure(FileUpload::make($name)->image());
    }

    /**
     * @template T of FileUpload
     *
     * @param  T  $field
     * @return T
     */
    private static function configure(FileUpload $field): FileUpload
    {
        return $field
            ->maxSize(self::maxKilobytes());
    }

    /**
     * Kolik smí mít nahrávaný soubor kilobajtů. Nejnižší ze tří stropů:
     * `upload_max_filesize`, `post_max_size` a limit Livewire.
     */
    public static function maxKilobytes(): int
    {
        $limits = array_filter([
            self::iniKilobytes('upload_max_filesize'),
            self::iniKilobytes('post_max_size'),
            self::LIVEWIRE_MAX_KILOBYTES,
        ]);

        return $limits === [] ? self::LIVEWIRE_MAX_KILOBYTES : (int) min($limits);
    }

    /**
     * Hodnota z php.ini v kilobajtech. Zápis je zkratkový („2M", „512K"),
     * nula nebo prázdno znamená „bez omezení" — takové hodnoty vracíme jako
     * `null`, ať ve výběru nejnižšího stropu nevyhrají.
     */
    private static function iniKilobytes(string $key): ?int
    {
        $value = trim((string) ini_get($key));

        if ($value === '' || $value === '0' || $value === '-1') {
            return null;
        }

        $number = (float) $value;
        $bytes = match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };

        return max(1, (int) floor($bytes / 1024));
    }
}
