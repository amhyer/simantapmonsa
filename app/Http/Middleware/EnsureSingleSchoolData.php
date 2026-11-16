<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Support\TenantMode;
use Closure;
use Illuminate\Http\Request;

/** Fitur yang membaca/mengubah data global tidak aman untuk admin tenant. */
class EnsureSingleSchoolData
{
    public function handle(Request $request, Closure $next, ?string $fitur = null)
    {
        // Beberapa fitur punya izin khusus untuk instalasi satu sekolah,
        // misalnya tarik presensi dari database mesin yang ada di server ini.
        $dikecualikan = $fitur === 'presensi-mesin'
            && (bool) config('presensi.mesin_izinkan_tenant', false);

        if (TenantMode::isMultiSchool() && ! $dikecualikan) {
            $message = 'Fitur data global ini dinonaktifkan untuk admin sekolah sampai tersedia isolasi per tenant.';

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 503)
                : response()->view('errors.503', [
                    'judul' => 'Fitur Khusus Mode Sekolah Tunggal',
                    'pesan' => $message,
                    'tombol' => ['url' => route('home'), 'label' => 'Kembali ke Dasbor'],
                ], 503);
        }

        return $next($request);
    }
}
