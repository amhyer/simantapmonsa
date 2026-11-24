<?php

namespace App\Console\Commands;

use App\Models\SekolahSettings;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantBackfillCommand extends Command
{
    protected $signature = 'tenant:backfill-existing';

    protected $description = 'Menghubungkan data sekolah dan pengguna eksisting ke tabel tenants';

    public function handle(): int
    {
        $this->info('Memeriksa data sekolah eksisting...');

        $sekolah = SekolahSettings::withoutGlobalScopes()->first();
        $namaSekolah = $sekolah?->nama_sekolah ?: 'UPT SPF SD NEGERI UNGGULAN MONGISIDI 1';
        $npsn = $sekolah?->npsn ?: '40313912';
        $subdomain = 'mongisidi1';

        $tenant = Tenant::where('npsn', $npsn)->orWhere('subdomain', $subdomain)->first();
        $wilayah = $sekolah?->kecamatan ?: 'Kec. Makasar';

        if (! $tenant) {
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'npsn' => $npsn,
                'nama_sekolah' => $namaSekolah,
                'wilayah' => $wilayah,
                'subdomain' => $subdomain,
                'paket' => 'enterprise',
                'kuota_siswa' => 1000,
                'kuota_storage_mb' => 10240,
                'aktif' => true,
                'meta' => [
                    'migrated_from_legacy' => true,
                    'migrated_at' => now()->toIso8601String(),
                ],
            ]);
            $this->info("✓ Tenant baru dibuat: [ID: {$tenant->id}] {$tenant->nama_sekolah} ({$wilayah})");
        } else {
            if (empty($tenant->wilayah)) {
                $tenant->update(['wilayah' => $wilayah]);
            }
            $this->info("Tenant sudah ada: [ID: {$tenant->id}] {$tenant->nama_sekolah} ({$tenant->wilayah})");
        }

        $explicitTables = [
            'users',
            'siswa',
            'rombel',
            'sekolah_settings',
            'mata_pelajaran',
            'semesters',
            'ptk',
            'pembelajaran',
            'anggota_rombel',
            'ekstrakurikuler',
            'tanggal_rapor',
            'tema_kokurikuler',
            'kegiatan_kokurikuler',
            'kelompok_kokurikuler',
            'dapodik_configs',
            'dapodik_sync_logs',
            'cbt_question_banks',
            'cbt_test_sessions',
            'cbt_results',
            'cbt_student_attempts',
            'exam_banks',
            'exam_sessions',
            'exam_attempts',
            'tryout_packages',
            'tryout_sessions',
            'tryout_results',
            'tka_dimensions_enhanced',
            'tka_assessment_items',
            'tka_student_assessments',
            'tka_kesiapan_pengaturan',
            'tka_kesiapan_pemetaan',
            'nilai_erapor',
            'kehadiran',
            'kehadiran_mapel',
            'kebiasaan',
            'catatan',
            'dimensi',
            'hasil_kuis',
            'jadwal_pelajaran',
            'kuis',
            'materi',
            'nilai',
            'presensi_lokasi',
            'presensi_pegawai',
            'presensi_sesi_qr',
            'pengajuan_izin_presensi',
            'presensi_mesin_configs',
            'presensi_mesin_karyawan_mappings',
            'presensi_mesin_sync_logs',
            'kelas_digital',
            'perangkat_pembelajaran',
            'jurnal_mengajar',
            'pm_capaian_pembelajaran',
            'pm_tujuan_pembelajaran',
            'pm_kktp_items',
            'pm_kktp_intervals',
            'pm_prota_items',
            'pm_prosem_items',
            'pm_kokurikuler',
            'pm_alokasi_waktu',
            'pm_kalender_pendidikan',
            'pm_hari_libur',
            'pm_supervisi',
            'visitasi_pengawas',
            'aktivitas',
        ];

        $excludedSystemTables = [
            'migrations',
            'tenants',
            'jobs',
            'job_batches',
            'failed_jobs',
            'cache',
            'cache_locks',
            'sessions',
            'password_reset_tokens',
        ];

        $discoveredTables = [];
        foreach (Schema::getTableListing() as $entry) {
            $name = (string) $entry;
            if (str_contains($name, '.')) {
                $name = substr($name, strrpos($name, '.') + 1);
            }
            $lower = strtolower($name);
            if (! in_array($lower, $excludedSystemTables, true)) {
                $discoveredTables[] = $name;
            }
        }

        $tables = array_values(array_unique(array_merge($explicitTables, $discoveredTables)));

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $query = DB::table($table)->whereNull('tenant_id');

            // Jangan beri tenant_id pada super_admin
            if ($table === 'users') {
                $query->where('peran', '!=', 'super_admin');
            }

            $count = $query->count();
            if ($count > 0) {
                $query->update(['tenant_id' => $tenant->id]);
                $this->line("  ✓ Menghubungkan {$count} baris di tabel `{$table}` ke Tenant #{$tenant->id}");
            }
        }

        $this->info("✓ Seluruh data sekolah utama ({$tenant->nama_sekolah}) berhasil terhubung dengan akun Super Admin!");

        return self::SUCCESS;
    }
}
