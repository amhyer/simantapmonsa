<?php

namespace App\Services\PembelajaranMendalam;

use App\Models\MataPelajaran;
use App\Models\PembelajaranMendalam\AlokasiWaktu;
use App\Models\PembelajaranMendalam\SupervisiPm;
use App\Models\PembelajaranMendalam\TujuanPembelajaranPm;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PembelajaranContextService
{
    public function tenantId(?User $user = null): ?int
    {
        if (TenantContext::check()) {
            return (int) TenantContext::id();
        }

        return $user?->tenant_id !== null ? (int) $user->tenant_id : null;
    }

    /**
     * Ambil konteks aktif Pembelajaran Mendalam (prioritas: query string -> session -> default).
     *
     * @return array{tahun_ajaran: string, semester: int, tingkat: int, fase: string}
     */
    public function getContext(?Request $request = null): array
    {
        $saved = session('pm_context', []);
        $tahunAjaran = trim((string) ($request?->query('tahun_ajaran') ?: ($saved['tahun_ajaran'] ?? $this->tahunAjaranAktif())));
        if ($tahunAjaran === '') {
            $tahunAjaran = $this->tahunAjaranAktif();
        }

        $semRaw = (int) ($request?->query('semester') ?: ($saved['semester'] ?? 1));
        $semester = in_array($semRaw, [1, 2], true) ? $semRaw : 1;

        $tingkatRaw = (int) ($request?->query('tingkat') ?: ($saved['tingkat'] ?? 1));
        $tingkat = max(1, min(6, $tingkatRaw));

        $faseQuery = strtoupper(trim((string) ($request?->query('fase') ?? '')));
        $fase = in_array($faseQuery, ['A', 'B', 'C'], true)
            ? $faseQuery
            : TujuanPembelajaranPm::faseUntukKelas($tingkat);

        return [
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
            'tingkat' => $tingkat,
            'fase' => $fase,
        ];
    }

    public function setContext(string $tahunAjaran, int $semester, int $tingkat): void
    {
        session([
            'pm_context' => [
                'tahun_ajaran' => trim($tahunAjaran) ?: $this->tahunAjaranAktif(),
                'semester' => in_array($semester, [1, 2], true) ? $semester : 1,
                'tingkat' => max(1, min(6, $tingkat)),
            ],
        ]);
    }

    public function supervisiAktifGuru(?int $tenantId, ?int $guruId, string $tahunAjaran, int $semester): ?SupervisiPm
    {
        if (! $guruId) {
            return null;
        }

        return SupervisiPm::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('guru_id', $guruId)
            ->where('tahun_ajaran', $tahunAjaran)
            ->where('semester', $semester)
            ->first();
    }

    public function tahunAjaranAktif(): string
    {
        $semester = Semester::query()->orderByDesc('semester_id')->first();
        if ($semester && ! empty($semester->tahun_ajaran)) {
            return (string) $semester->tahun_ajaran;
        }

        $year = (int) date('Y');
        $month = (int) date('n');

        return $month >= 7 ? "{$year}/".($year + 1) : ($year - 1)."/{$year}";
    }

    /**
     * Ambil daftar mata pelajaran aktif di sekolah; jika kosong, gunakan daftar standar SD.
     *
     * @return Collection<int, object{id: int|null, nama: string, kode: string}>
     */
    public function daftarMapel(): Collection
    {
        $rows = MataPelajaran::query()
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode']);

        if ($rows->isNotEmpty()) {
            return $rows->map(fn (MataPelajaran $m) => (object) [
                'id' => (int) $m->id,
                'nama' => (string) $m->nama,
                'kode' => (string) ($m->kode ?: strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $m->nama) ?: 'MP', 0, 5))),
            ]);
        }

        $fallbackKode = [
            'Pendidikan Agama dan Budi Pekerti' => 'PAI',
            'Pendidikan Pancasila' => 'PP',
            'Bahasa Indonesia' => 'BIND',
            'Matematika' => 'MTK',
            'Ilmu Pengetahuan Alam dan Sosial (IPAS)' => 'IPAS',
            'Pendidikan Jasmani, Olahraga, dan Kesehatan (PJOK)' => 'PJOK',
            'Seni dan Budaya' => 'SBDP',
            'Bahasa Inggris' => 'BING',
            'Muatan Lokal' => 'MULOK',
        ];

        return collect(array_keys(AlokasiWaktu::STANDAR_PERMENDIKDASMEN_2025))
            ->map(fn (string $nama) => (object) [
                'id' => null,
                'nama' => $nama,
                'kode' => $fallbackKode[$nama] ?? strtoupper(substr($nama, 0, 4)),
            ]);
    }

    public function sekolah(?int $tenantId = null): ?SekolahSettings
    {
        return SekolahSettings::query()
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }
}
