<?php

namespace App\Services\Navigation;

use App\Models\Elearning\KelasDigitalPengumpulan;
use App\Models\Elearning\KelasDigitalTugas;
use App\Models\PengajuanIzinPresensi;
use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\Dapodik\SidebarSyncStatus;
use App\Services\Elearning\SiswaMateriVisibilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Gelombang 3B + 7B: Pusat Notifikasi & Ringkasan Tugas Ringan lintas peran.
 * Aturan tegas:
 * - Hanya mengembalikan array skalar (TIDAK PERNAH cache/serialisasi model Eloquent).
 * - Tidak pernah menampilkan teks/alasan sensitif di pratinjau notifikasi.
 * - Semua kueri 100% terisolasi tenant_id aktif.
 */
class RoleTaskSummaryService
{
    public function __construct(
        private readonly SiswaMateriVisibilityService $siswaVisibility,
    ) {}

    /**
     * @return array<int, array{icon: string, teks: string, waktu: string, url: ?string}>
     */
    public function notificationsFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $tenantId = TenantContext::check()
            ? (int) TenantContext::id()
            : ($user->tenant_id !== null ? (int) $user->tenant_id : null);

        return match ($user->peran) {
            'admin' => $this->forAdmin($tenantId),
            'guru' => $this->forGuru($user, $tenantId),
            'siswa' => $this->forSiswa($user, $tenantId),
            'kepsek' => $this->forKepsek($tenantId),
            default => [],
        };
    }

    /**
     * @return array<int, array{icon: string, teks: string, waktu: string, url: ?string}>
     */
    private function forAdmin(?int $tenantId): array
    {
        $notifs = [];

        $last = SidebarSyncStatus::latest();
        if ($last) {
            $notifs[] = [
                'icon' => 'fas fa-sync',
                'teks' => "Sinkron Dapodik: {$last['berhasil']} berhasil, {$last['gagal']} gagal",
                'waktu' => $last['created_at'] ? Carbon::parse($last['created_at'])->diffForHumans() : '-',
                'url' => Route::has('admin.dapodik.index') ? route('admin.dapodik.index') : null,
            ];
        }

        try {
            if (Schema::hasTable('siswa') && Schema::hasTable('rombel')) {
                $siswaQ = Siswa::query()->where('aktif', true)->whereNull('rombel_id');
                $rombelQ = Rombel::query()->whereNull('guru_id');
                if ($tenantId !== null) {
                    $siswaQ->where('tenant_id', $tenantId);
                    $rombelQ->where('tenant_id', $tenantId);
                }
                $tanpaRombel = $siswaQ->count();
                $tanpaWali = $rombelQ->count();
                if ($tanpaRombel > 0 || $tanpaWali > 0) {
                    $bagian = array_filter([
                        $tanpaRombel > 0 ? "{$tanpaRombel} siswa belum masuk rombel" : null,
                        $tanpaWali > 0 ? "{$tanpaWali} rombel belum ada wali kelas" : null,
                    ]);
                    $notifs[] = [
                        'icon' => 'fas fa-clipboard-check',
                        'teks' => 'Validasi data: '.implode(', ', $bagian),
                        'waktu' => 'perlu dicek',
                        'url' => Route::has('admin.validasi-data.index') ? route('admin.validasi-data.index') : null,
                    ];
                }
            }
        } catch (\Throwable) {
            // Jangan gagalkan render layout jika tabel belum siap saat migrasi awal.
        }

        if ($tenantId === null && ! TenantContext::check()
            && ! \App\Modules\Tenant\Support\TenantMode::isMultiSchool()
            && Schema::hasTable('failed_jobs')) {
            $gagal = DB::table('failed_jobs')->count();
            if ($gagal > 0) {
                $notifs[] = [
                    'icon' => 'fas fa-exclamation-triangle',
                    'teks' => "{$gagal} job antrean gagal perlu perhatian",
                    'waktu' => 'sekarang',
                    'url' => null,
                ];
            }
        }

        return $notifs;
    }

    /**
     * @return array<int, array{icon: string, teks: string, waktu: string, url: ?string}>
     */
    private function forGuru(User $guru, ?int $tenantId): array
    {
        $notifs = [];

        try {
            if (Schema::hasTable('kelas_digital_pengumpulan') && Schema::hasTable('kelas_digital_tugas') && Schema::hasTable('kelas_digital')) {
                $belumDinilai = KelasDigitalPengumpulan::query()
                    ->whereIn('status', ['dikumpulkan', 'terlambat'])
                    ->whereHas('tugas.kelasDigital', function ($q) use ($guru, $tenantId): void {
                        $q->where('guru_id', $guru->id);
                        if ($tenantId !== null) {
                            $q->where('tenant_id', $tenantId);
                        }
                    })
                    ->count();

                if ($belumDinilai > 0) {
                    $notifs[] = [
                        'icon' => 'fas fa-tasks',
                        'teks' => "{$belumDinilai} pengumpulan tugas Kelas Digital menunggu dinilai",
                        'waktu' => 'hari ini',
                        'url' => Route::has('guru.kelas-digital.index') ? route('guru.kelas-digital.index') : null,
                    ];
                }
            }

            if (Schema::hasTable('pengajuan_izin_presensi') && Schema::hasTable('siswa')) {
                $siswaWaliIds = Siswa::query()
                    ->where('aktif', true)
                    ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->where(function ($q) use ($guru): void {
                        $q->whereHas('rombel', fn ($rombel) => $rombel->where('guru_id', $guru->id))
                            ->orWhere(fn ($fallback) => $fallback->whereNull('rombel_id')->where('guru_id', $guru->id));
                    })
                    ->select('id');

                $izinMenunggu = PengajuanIzinPresensi::query()
                    ->where('status', 'menunggu')
                    ->whereIn('siswa_id', $siswaWaliIds)
                    ->count();

                if ($izinMenunggu > 0) {
                    // Privasi: hanya tampilkan jumlah, jangan pernah paparkan alasan izin di pratinjau notifikasi.
                    $notifs[] = [
                        'icon' => 'fas fa-envelope-open-text',
                        'teks' => "{$izinMenunggu} pengajuan izin siswa menunggu verifikasi",
                        'waktu' => 'perlu ditinjau',
                        'url' => Route::has('guru.kehadiran.izin.index') ? route('guru.kehadiran.izin.index') : null,
                    ];
                }
            }

            if (Schema::hasTable('jurnal_mengajar')) {
                $jurnalHariIni = JurnalMengajar::query()
                    ->where('guru_id', $guru->id)
                    ->whereDate('tanggal', today());
                if ($tenantId !== null) {
                    $jurnalHariIni->where('tenant_id', $tenantId);
                }
                if ($jurnalHariIni->count() === 0) {
                    $notifs[] = [
                        'icon' => 'fas fa-book-open',
                        'teks' => 'Jurnal mengajar hari ini belum tercatat',
                        'waktu' => 'pengingat',
                        'url' => Route::has('guru.perangkat.jurnal.index') ? route('guru.perangkat.jurnal.index') : null,
                    ];
                }
            }
        } catch (\Throwable) {
            // Aman saat migrasi berjalan.
        }

        return $notifs;
    }

    /**
     * @return array<int, array{icon: string, teks: string, waktu: string, url: ?string}>
     */
    private function forSiswa(User $user, ?int $tenantId): array
    {
        $notifs = [];

        try {
            if (! Schema::hasTable('siswa') || ! Schema::hasTable('kelas_digital_tugas') || ! Schema::hasTable('kelas_digital')) {
                return [];
            }

            $siswa = $this->siswaVisibility->resolveSiswaForUser($user);
            if (! $siswa) {
                return [];
            }

            $tugasAktif = KelasDigitalTugas::query()
                ->whereHas('pertemuan', fn ($p) => $p->where('dipublikasikan', true))
                ->whereHas('kelasDigital', function ($q) use ($siswa, $tenantId): void {
                    $q->where('aktif', true);
                    if ($tenantId !== null) {
                        $q->where('tenant_id', $tenantId);
                    }
                    $q->where(function ($k) use ($siswa): void {
                        if ($siswa->rombel_id) {
                            $k->where('rombel_id', $siswa->rombel_id);
                        }
                        if ($siswa->kelas) {
                            $k->orWhere('kelas', $siswa->kelas);
                        }
                    });
                })
                ->whereDoesntHave('pengumpulan', fn ($p) => $p->where('siswa_id', $siswa->id))
                ->count();

            if ($tugasAktif > 0) {
                $notifs[] = [
                    'icon' => 'fas fa-clipboard-list',
                    'teks' => "{$tugasAktif} tugas Kelas Digital belum dikumpulkan",
                    'waktu' => 'aktif',
                    'url' => Route::has('siswa.kelas-digital.index') ? route('siswa.kelas-digital.index') : null,
                ];
            }
        } catch (\Throwable) {
            // Aman saat migrasi berjalan.
        }

        return $notifs;
    }

    /**
     * @return array<int, array{icon: string, teks: string, waktu: string, url: ?string}>
     */
    private function forKepsek(?int $tenantId): array
    {
        $notifs = [];

        try {
            if (Schema::hasTable('perangkat_pembelajaran')) {
                $q = PerangkatPembelajaran::query()->where('status', 'final');
                if ($tenantId !== null) {
                    $q->where('tenant_id', $tenantId);
                }
                $jumlah = $q->count();
                if ($jumlah > 0) {
                    $notifs[] = [
                        'icon' => 'fas fa-folder-open',
                        'teks' => "{$jumlah} dokumen perangkat ajar guru siap disupervisi",
                        'waktu' => 'terbaru',
                        'url' => Route::has('kepsek.perangkat.index') ? route('kepsek.perangkat.index') : null,
                    ];
                }
            }
        } catch (\Throwable) {
            // Aman saat migrasi berjalan.
        }

        return $notifs;
    }
}
