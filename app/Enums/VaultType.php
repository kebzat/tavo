<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Druh přístupu v trezoru. Podle něj trezor ví, co s ním smí udělat za agenta. */
enum VaultType: string implements HasIcon, HasLabel
{
    case Ftp = 'ftp';
    case WordPress = 'wordpress';
    case Shoptet = 'shoptet';
    case Upgates = 'upgates';
    case Web = 'web';
    case ApiKey = 'api';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ftp => 'FTP / SFTP',
            self::WordPress => 'WordPress',
            self::Shoptet => 'Shoptet',
            self::Upgates => 'Upgates',
            self::Web => 'Jiná administrace',
            self::ApiKey => 'API klíč',
            self::Other => 'Jiné heslo',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Ftp => Heroicon::OutlinedServerStack,
            self::WordPress, self::Web => Heroicon::OutlinedGlobeAlt,
            self::Shoptet, self::Upgates => Heroicon::OutlinedShoppingBag,
            self::ApiKey => Heroicon::OutlinedKey,
            self::Other => Heroicon::OutlinedLockClosed,
        };
    }

    /** Přihlašuje se formulářem v prohlížeči, takže za agenta umí přihlásit trezor. */
    public function isLogin(): bool
    {
        return in_array($this, [self::WordPress, self::Shoptet, self::Upgates, self::Web], true);
    }

    /**
     * Kde je přihlašovací formulář, když ho správce nevyplní. Upgates zatím
     * neověřené na skutečném obchodě, proto je u něj adresa v nápovědě formuláře.
     */
    public function loginPath(): ?string
    {
        return match ($this) {
            self::WordPress => '/wp-login.php',
            self::Shoptet => '/admin/login/',
            self::Upgates => '/admin/',
            default => null,
        };
    }

    /** Kam agent po přihlášení jde. U jiné administrace neznámé (adresa webu bývá veřejná stránka). */
    public function adminPath(): ?string
    {
        return match ($this) {
            self::WordPress => '/wp-admin/',
            self::Shoptet, self::Upgates => '/admin/',
            default => null,
        };
    }

    /** Popisky polí, ať formulář mluví jazykem služby. */
    public function urlLabel(): string
    {
        return match ($this) {
            self::Ftp => 'Server',
            self::ApiKey => 'Služba nebo adresa API',
            self::Other => 'Kde se používá',
            default => 'Adresa webu',
        };
    }

    public function usernameLabel(): string
    {
        return match ($this) {
            self::Shoptet, self::Upgates => 'E-mail',
            self::ApiKey => 'Účet nebo ID (nepovinné)',
            default => 'Uživatel',
        };
    }

    public function secretLabel(): string
    {
        return match ($this) {
            self::ApiKey => 'Klíč',
            default => 'Heslo',
        };
    }

    /**
     * Co smí agent, srozumitelně pro toho, kdo přístup ukládá. U přihlášení
     * nejde rozlišit čtení a zápis: přihlášený agent může v administraci
     * všechno, co smí ten účet, proto se o tom rozhoduje výběrem účtu.
     *
     * @return array<string, string>
     */
    public function agentAccessOptions(): array
    {
        return match ($this) {
            self::Ftp => [
                'none' => 'Nic, přístup je jen pro lidi',
                'read' => 'Číst soubory (stáhnout a zkontrolovat)',
                'approve' => 'Číst a nahrávat, nahrání schválíš kliknutím',
                'write' => 'Číst a nahrávat hned (zálohuje se, jde vrátit)',
            ],
            self::WordPress => [
                'none' => 'Nic, přístup je jen pro lidi',
                'read' => 'Číst přes aplikační heslo a přihlásit se',
                'write' => 'Číst i měnit přes aplikační heslo a přihlásit se',
            ],
            self::Shoptet, self::Upgates, self::Web => [
                'none' => 'Nic, přístup je jen pro lidi',
                'read' => 'Přihlásit se do administrace (heslo neuvidí)',
            ],
            self::ApiKey => [
                'none' => 'Nic, přístup je jen pro lidi',
                'read' => 'Číst přes API (zatím jen Zaslat), klíč neuvidí',
            ],
            self::Other => [
                'none' => 'Nic, přístup je jen pro lidi',
            ],
        };
    }
}
