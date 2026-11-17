<?php

namespace App\Services\Admin;

use App\Models\JadwalPelajaran;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;

/**
 * Gelombang 3A + 7B: Pusat Validasi & Kelengkapan Data Sekolah (read-only,
 * 100% terisolasi per tenant_id, tanpa cache model Eloquent).
 */
class ValidasiDataSekolahService
{
    /**
     * @return array<string, mixed>
     */
    public function ringkasan(?int $tenantId = null): array
    {
        $tenantId ??= TenantContext::check() ? (int) TenantContext::id() : null;

        $siswaQuery = Siswa::query()->where('aktif', true);
        $rombelQuery = Rombel::query();
        $jadwalQuery = JadwalPelajaran::query();
        $guruQuery = User::query()->where('peran', 'guru');
        $akunSiswaQuery = User::query()->where('peran', 'siswa');
        $sekolahQuery = SekolahSettings::query();

        if ($tenantId !== null) {
            $siswaQuery->where('tenant_id', $tenantId);
            $rombelQuery->where('tenant_id', $tenantId);
            $jadwalQuery->where('tenant_id', $tenantId);
            $guruQuery->where('tenant_id', $tenantId);
            $akunSiswaQuery->where('tenant_id', $tenantId);
            $sekolahQuery->where('tenant_id', $tenantId);
        }

        $totalSiswaAktif = (clone $siswaQuery)->count();
        $siswaTanpaRombel = (clone $siswaQuery)
            ->whereNull('rombel_id')
            ->orderBy('nama_peserta_didik')
            ->limit(25)
            ->get(['id', 'nis', 'nisn', 'nama_peserta_didik', 'kelas'])
            ->map(fn ($s) => [
                'nis' => (string) ($s->nis ?: $s->nisn ?: '-'),
                'nama' => (string) $s->nama_peserta_didik,
                'kelas' => (string) ($s->kelas ?: 'Belum diatur'),
            ])
            ->all();
        $jumlahSiswaTanpaRombel = (clone $siswaQuery)->whereNull('rombel_id')->count();

        $rombelTanpaWali = (clone $rombelQuery)
            ->whereNull('guru_id')
            ->orderBy('nama_rombel')
            ->limit(25)
            ->get(['id', 'nama_rombel', 'tingkat'])
            ->map(fn ($r) => [
                'nama_rombel' => (string) $r->nama_rombel,
                'tingkat' => (string) ($r->tingkat ?: '-'),
            ])
            ->all();
        $jumlahRombelTanpaWali = (clone $rombelQuery)->whereNull('guru_id')->count();

        $jumlahJadwalBelumLengkap = (clone $jadwalQuery)
            ->where(function ($q): void {
                $q->whereNull('guru_id')->orWhereNull('rombel_id');
            })
            ->count();

        $jumlahGuruTanpaMapel = (clone $guruQuery)
            ->where(function ($q): void {
                $q->whereNull('kelas_mata_pelajaran')->orWhere('kelas_mata_pelajaran', '');
            })
            ->count();

        $akunSiswaCount = $akunSiswaQuery->count();
        $perkiraanSiswaTanpaAkun = max(0, $totalSiswaAktif - $akunSiswaCount);

        $pegawaiNikQuery = $tenantId !== null
            ? User::query()->where('tenant_id', $tenantId)
            : User::forSchool();
        $jumlahNikAmbigu = $pegawaiNikQuery
            ->whereIn('peran', ['guru', 'kepsek', 'tendik'])
            ->where('aktif', true)
            ->whereNotNull('nik')
            ->where('nik', '<>', '')
            ->pluck('nik')
            ->map(fn ($v) => preg_replace('/[^0-9A-Za-z]/', '', trim((string) $v)) ?? '')
            ->filter(fn ($v) => $v !== '')
            ->countBy()
            ->filter(fn (int $c) => $c > 1)
            ->count();

        $sekolah = $sekolahQuery->first(['nama_sekolah', 'npsn', 'alamat', 'kepala_sekolah']);
        $profilSekolahKurang = [];
        if (! $sekolah || trim((string) $sekolah->nama_sekolah) === '') {
            $profilSekolahKurang[] = 'Nama Sekolah';
        }
        if (! $sekolah || trim((string) $sekolah->npsn) === '') {
            $profilSekolahKurang[] = 'NPSN';
        }
        if (! $sekolah || trim((string) $sekolah->kepala_sekolah) === '') {
            $profilSekolahKurang[] = 'Nama Kepala Sekolah';
        }
        if (! $sekolah || trim((string) $sekolah->alamat) === '') {
            $profilSekolahKurang[] = 'Alamat Sekolah';
        }

        $items = [
            [
                'kode' => 'siswa_tanpa_rombel',
                'judul' => 'Siswa Aktif Belum Masuk Rombel',
                'jumlah' => $jumlahSiswaTanpaRombel,
                'status' => $jumlahSiswaTanpaRombel === 0 ? 'ok' : 'warn',
                'keterangan' => $jumlahSiswaTanpaRombel === 0
                    ? 'Semua siswa aktif sudah terhubung ke rombel.'
                    : "{$jumlahSiswaTanpaRombel} siswa aktif belum memiliki rombel_id (akan memengaruhi Kelas Digital, Materi, dan Rapor).",
                'route' => 'admin.users.siswa',
                'aksi_label' => 'Perbaiki di Data Siswa',
            ],
            [
                'kode' => 'rombel_tanpa_wali',
                'judul' => 'Rombel Belum Memiliki Wali Kelas',
                'jumlah' => $jumlahRombelTanpaWali,
                'status' => $jumlahRombelTanpaWali === 0 ? 'ok' : 'warn',
                'keterangan' => $jumlahRombelTanpaWali === 0
                    ? 'Seluruh rombel sudah memiliki wali kelas.'
                    : "{$jumlahRombelTanpaWali} rombel belum ditetapkan wali kelasnya.",
                'route' => 'admin.peta-kelas.daftar',
                'aksi_label' => 'Atur di Rombel & Peta Kelas',
            ],
            [
                'kode' => 'jadwal_belum_lengkap',
                'judul' => 'Jadwal / Pembelajaran Tanpa Guru atau Rombel',
                'jumlah' => $jumlahJadwalBelumLengkap,
                'status' => $jumlahJadwalBelumLengkap === 0 ? 'ok' : 'warn',
                'keterangan' => $jumlahJadwalBelumLengkap === 0
                    ? 'Semua data pembelajaran memiliki guru dan rombel.'
                    : "{$jumlahJadwalBelumLengkap} baris jadwal pelajaran belum memiliki guru atau rombel.",
                'route' => 'admin.referensi.pembelajaran',
                'aksi_label' => 'Periksa Data Pembelajaran',
            ],
            [
                'kode' => 'guru_tanpa_mapel',
                'judul' => 'Akun Guru Belum Diisi Kelas / Mata Pelajaran',
                'jumlah' => $jumlahGuruTanpaMapel,
                'status' => $jumlahGuruTanpaMapel === 0 ? 'ok' : 'info',
                'keterangan' => $jumlahGuruTanpaMapel === 0
                    ? 'Semua akun guru sudah dilengkapi keterangan kelas/mapel.'
                    : "{$jumlahGuruTanpaMapel} akun guru belum diisi kolom Kelas / Mata Pelajaran.",
                'route' => 'admin.users.index',
                'aksi_label' => 'Lengkapi di Manajemen Akun',
            ],
            [
                'kode' => 'siswa_tanpa_akun',
                'judul' => 'Selisih Siswa Aktif vs Akun Login Siswa',
                'jumlah' => $perkiraanSiswaTanpaAkun,
                'status' => $perkiraanSiswaTanpaAkun === 0 ? 'ok' : 'info',
                'keterangan' => $perkiraanSiswaTanpaAkun === 0
                    ? "Jumlah akun siswa ({$akunSiswaCount}) sepadan dengan siswa aktif ({$totalSiswaAktif})."
                    : "Terdapat {$totalSiswaAktif} siswa aktif dan {$akunSiswaCount} akun login siswa.",
                'route' => 'admin.users.index',
                'aksi_label' => 'Kelola Akun Siswa',
            ],
            [
                'kode' => 'nik_pegawai_ambigu',
                'judul' => 'PIN / NIK Pegawai Duplikat (Mesin Presensi)',
                'jumlah' => $jumlahNikAmbigu,
                'status' => $jumlahNikAmbigu === 0 ? 'ok' : 'warn',
                'keterangan' => $jumlahNikAmbigu === 0
                    ? 'Tidak ada PIN/NIK pegawai aktif yang dipakai ganda di sekolah ini.'
                    : "{$jumlahNikAmbigu} kode PIN/NIK dipakai oleh lebih dari satu pegawai aktif — impor mesin presensi akan melewati entri ambigu tersebut.",
                'route' => 'admin.users.index',
                'aksi_label' => 'Perbaiki di Manajemen Akun',
            ],
            [
                'kode' => 'profil_sekolah',
                'judul' => 'Kelengkapan Profil Sekolah (Kop Cetak & PDF)',
                'jumlah' => count($profilSekolahKurang),
                'status' => $profilSekolahKurang === [] ? 'ok' : 'warn',
                'keterangan' => $profilSekolahKurang === []
                    ? 'Nama sekolah, NPSN, alamat, dan kepala sekolah lengkap untuk kop surat PDF.'
                    : 'Masih kosong: '.implode(', ', $profilSekolahKurang).'.',
                'route' => 'admin.sekolah.index',
                'aksi_label' => 'Lengkapi Profil Sekolah',
            ],
        ];

        $totalTemuan = collect($items)->where('status', '!=', 'ok')->sum('jumlah');

        return [
            'total_siswa_aktif' => $totalSiswaAktif,
            'total_temuan' => $totalTemuan,
            'items' => $items,
            'siswa_tanpa_rombel' => $siswaTanpaRombel,
            'rombel_tanpa_wali' => $rombelTanpaWali,
        ];
    }
}
