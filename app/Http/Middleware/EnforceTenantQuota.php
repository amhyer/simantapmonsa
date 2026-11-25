<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Contexts\TenantContext;
use App\Services\Tenant\TenantQuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan batas kuota penyimpanan (MB) dan kuota siswa per sekolah (tenant)
 * pada request mutasi tanpa mengganggu mode sekolah tunggal.
 */
class EnforceTenantQuota
{
    public function __construct(
        private readonly TenantQuotaService $quota,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = TenantContext::id();
        if ($tenantId === null || in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // 1. Cek kuota penyimpanan bila request mengunggah berkas
        $allFiles = $request->allFiles();
        $hasUpload = $allFiles !== [];
        if ($hasUpload) {
            $incomingBytes = $this->quota->sumUploadedFilesBytes($allFiles);
            $firstField = (string) array_key_first($allFiles);
            $this->quota->assertCanStoreBytes($tenantId, $incomingBytes, $firstField ?: 'file');
        }

        // 2. Cek kuota siswa pada rute penambahan siswa / akun siswa baru
        if ($this->isStudentCreationRequest($request)) {
            $this->quota->assertCanAddStudents($tenantId, 1);
        }

        $response = $next($request);

        if ($hasUpload) {
            $this->quota->forgetStorageCache($tenantId);
        }

        return $response;
    }

    private function isStudentCreationRequest(Request $request): bool
    {
        if ($request->routeIs('guru.siswa.store', 'guru.siswa.impor')) {
            return true;
        }

        if ($request->routeIs('admin.users.store', 'admin.users.proses-massal')) {
            return strtolower((string) $request->input('peran', '')) === 'siswa';
        }

        return false;
    }
}
