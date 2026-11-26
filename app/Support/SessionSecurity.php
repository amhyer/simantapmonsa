<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Utilitas keamanan sesi untuk menginvalidasi sesi aktif milik pengguna
 * saat terjadi peristiwa sensitif (reset sandi, ganti sandi, pencabutan akses).
 */
class SessionSecurity
{
    /**
     * Hapus sesi database milik pengguna tertentu.
     *
     * Bila `$exceptSessionId` diisi, sesi tersebut dipertahankan (mis. sesi aktif
     * saat ini setelah ganti sandi mandiri). Bila null, seluruh sesi pengguna dihapus.
     */
    public static function invalidateUserSessions(int $userId, ?string $exceptSessionId = null): int
    {
        $table = (string) config('session.table', 'sessions');
        $connection = config('session.connection');

        if (! Schema::connection($connection)->hasTable($table)) {
            return 0;
        }

        $query = DB::connection($connection)
            ->table($table)
            ->where('user_id', $userId);

        if ($exceptSessionId !== null && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->delete();
    }
}
