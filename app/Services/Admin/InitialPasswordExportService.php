<?php

namespace App\Services\Admin;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Support\InitialPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InitialPasswordExportService
{
    private const CACHE_PREFIX = 'initial_pw:';

    /**
     * Simpan daftar kredensial sementara secara terenkripsi (TTL 10 menit)
     * dan kembalikan URL unduhan sekali-pakai yang terikat pada admin & tenant.
     *
     * @param  array<int, array{nama: string, kelas: string, username: string, password: string}>  $rows
     */
    public function store(int $adminId, ?int $tenantId, array $rows): string
    {
        $exportToken = (string) Str::uuid();
        $payload = Crypt::encryptString(json_encode([
            'admin_id' => $adminId,
            'tenant_id' => $tenantId,
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE));

        Cache::put(self::CACHE_PREFIX.$exportToken, $payload, now()->addMinutes(10));

        return route('admin.users.ekspor-sandi-awal', ['token' => $exportToken]);
    }

    public function streamOnce(Request $request, string $token): StreamedResponse
    {
        if (! Str::isUuid($token)) {
            abort(404);
        }

        $encrypted = Cache::pull(self::CACHE_PREFIX.$token);
        if (! is_string($encrypted) || $encrypted === '') {
            abort(404, 'Tautan unduhan sudah kedaluwarsa atau sudah pernah diunduh.');
        }

        $data = json_decode(Crypt::decryptString($encrypted), true);
        $currentTenant = TenantContext::id();
        if (! is_array($data)
            || (int) ($data['admin_id'] ?? 0) !== (int) $request->user()?->id
            || ($data['tenant_id'] ?? null) !== $currentTenant) {
            abort(403, 'Anda tidak berwenang mengunduh daftar kata sandi ini.');
        }

        $rows = $data['rows'] ?? [];
        $filename = 'kata-sandi-awal-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Nama', 'Kelas', 'Username', 'Password Awal']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    InitialPassword::sanitizeCsvCell($row['nama'] ?? ''),
                    InitialPassword::sanitizeCsvCell($row['kelas'] ?? '-'),
                    InitialPassword::sanitizeCsvCell($row['username'] ?? ''),
                    $row['password'] ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }
}
