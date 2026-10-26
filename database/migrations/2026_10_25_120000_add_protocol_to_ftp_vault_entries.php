<?php

use App\Models\VaultEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** FTP přístupy z doby před výběrem protokolu: doplní ftp/ftps/sftp, ať ho formulář ukáže. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('vault_entries')->where('type', 'ftp')->get()->each(function (object $row): void {
            $settings = json_decode((string) $row->settings, true) ?: [];

            if (isset($settings['protocol'])) {
                return;
            }

            $settings['protocol'] = VaultEntry::guessFtpProtocol(
                (string) $row->url, (string) $row->username, $settings['port'] ?? null, $settings['tls'] ?? true,
            );
            unset($settings['tls']);

            DB::table('vault_entries')->where('id', $row->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        //
    }
};
