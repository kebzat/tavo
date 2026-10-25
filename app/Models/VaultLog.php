<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Záznam v historii trezoru: kdo (člověk nebo agent) kdy co s přístupem
 * udělal. Zůstává i po smazání přístupu (název je uložený zvlášť).
 */
class VaultLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    public static function actions(): array
    {
        return [
            'created' => 'Uložen',
            'updated' => 'Upraven',
            'deleted' => 'Smazán',
            'unlocked' => 'Trezor odemčen',
            'revealed' => 'Heslo zobrazeno',
            'tested' => 'Vyzkoušeno spojení',
            'ftp_list' => 'FTP: výpis složky',
            'ftp_read' => 'FTP: přečten soubor',
            'ftp_zip' => 'FTP: stažena složka',
            'upload_requested' => 'FTP: agent chce nahrát',
            'upload_applied' => 'FTP: nahráno na web',
            'upload_discarded' => 'FTP: nahrání zahozeno',
            'upload_reverted' => 'FTP: nahrání vráceno',
            'upload_failed' => 'FTP: nahrání selhalo',
            'wp_api' => 'WordPress: dotaz',
            'login' => 'Přihlášení agenta',
            'login_failed' => 'Přihlášení se nepovedlo',
            'denied' => 'Odmítnuto',
        ];
    }

    public function actionLabel(): string
    {
        return self::actions()[$this->action] ?? $this->action;
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(VaultEntry::class, 'vault_entry_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
