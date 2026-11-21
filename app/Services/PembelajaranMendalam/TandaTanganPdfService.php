<?php

namespace App\Services\PembelajaranMendalam;

use App\Models\PengaturanGuru;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TandaTanganPdfService
{
    /**
     * Siapkan data tanda tangan & stempel (base64 data URI agar 100% kompatibel dengan DomPDF).
     *
     * @return array{
     *     kota: string,
     *     nama_kepsek: string,
     *     nip_kepsek: string,
     *     ttd_kepsek_uri: string|null,
     *     stempel_uri: string|null,
     *     nama_guru: string,
     *     nip_guru: string,
     *     jabatan_guru: string,
     *     ttd_guru_uri: string|null
     * }
     */
    public function resolveForPdf(?SekolahSettings $sekolah = null, ?User $guru = null): array
    {
        $guru ??= Auth::user();
        $tenantId = TenantContext::check()
            ? (int) TenantContext::id()
            : ($guru?->tenant_id !== null ? (int) $guru->tenant_id : null);

        if (! $sekolah) {
            $sekolah = SekolahSettings::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->first();
        }

        $pengSekolah = is_array($sekolah?->pengaturan) ? $sekolah->pengaturan : [];

        $pengGuruRow = $guru
            ? PengaturanGuru::query()->where('guru_id', $guru->id)->first()
            : null;
        $pengGuru = is_array($pengGuruRow?->pengaturan) ? $pengGuruRow->pengaturan : [];

        return [
            'kota' => trim((string) ($sekolah?->kota ?? '')),
            'nama_kepsek' => trim((string) ($sekolah?->kepala_sekolah ?? '')),
            'nip_kepsek' => trim((string) ($pengSekolah['nip_kepsek'] ?? '')),
            'ttd_kepsek_uri' => $this->toDataUri($pengSekolah['ttd_kepsek'] ?? null),
            'stempel_uri' => $this->toDataUri($pengSekolah['stempel_sekolah'] ?? null),
            'nama_guru' => trim((string) ($guru?->nama_lengkap ?? '')),
            'nip_guru' => trim((string) ($pengGuru['nip_guru'] ?? '')),
            'jabatan_guru' => trim((string) ($pengGuru['jabatan_guru'] ?? '')) ?: 'Guru Kelas / Mata Pelajaran',
            'ttd_guru_uri' => $this->toDataUri($pengGuru['ttd_guru'] ?? null),
        ];
    }

    public function toDataUri(?string $relativePath): ?string
    {
        if (! $relativePath || trim($relativePath) === '') {
            return null;
        }

        // Prioritaskan disk privat (local), fallback ke public untuk aset legacy
        $bytes = null;
        if (Storage::disk('local')->exists($relativePath)) {
            $bytes = Storage::disk('local')->get($relativePath);
        } elseif (Storage::disk('public')->exists($relativePath)) {
            $bytes = Storage::disk('public')->get($relativePath);
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }
}
