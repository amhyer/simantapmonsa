<?php

namespace App\Services\Perangkat;

use App\Models\Perangkat\JurnalMengajar;
use App\Models\Perangkat\PerangkatPembelajaran;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PerangkatAccessService
{
    public function currentTenantId(?User $user = null): ?int
    {
        if (TenantContext::check()) {
            return (int) TenantContext::id();
        }

        return $user?->tenant_id !== null ? (int) $user->tenant_id : null;
    }

    public function sameTenant(?int $a, ?int $b): bool
    {
        if ($a === null && $b === null) {
            return true;
        }

        return $a !== null && $b !== null && (int) $a === (int) $b;
    }

    /**
     * Pastikan guru adalah pemilik perangkat atau jurnal pada tenant yang sama.
     */
    public function authorizeOwner(User $guru, PerangkatPembelajaran|JurnalMengajar $item): void
    {
        $tenantId = $this->currentTenantId($guru);
        if ((int) $item->guru_id !== (int) $guru->id || ! $this->sameTenant($item->tenant_id, $tenantId)) {
            abort(403, 'Anda tidak memiliki izin mengubah dokumen perangkat milik guru lain.');
        }
    }

    /**
     * Cek apakah pengguna boleh membaca / mencetak PDF perangkat pembelajaran:
     * - Pemilik (guru_id = user.id, tenant sama)
     * - Wali kelas (read-only) untuk perangkat yang dibagikan dan kelas/rombelnya cocok
     * - Kepala sekolah atau admin (supervisi read-only) pada tenant yang sama
     */
    public function canViewPerangkat(User $user, PerangkatPembelajaran $perangkat): bool
    {
        $tenantId = $this->currentTenantId($user);
        if (! $this->sameTenant($perangkat->tenant_id, $tenantId)) {
            return false;
        }

        if (in_array($user->peran, ['kepsek', 'admin'], true)) {
            return true;
        }

        if ($user->peran !== 'guru') {
            return false;
        }

        if ((int) $perangkat->guru_id === (int) $user->id) {
            return true;
        }

        if (! $perangkat->dibagikan_ke_walas || ! $user->isWaliKelas()) {
            return false;
        }

        $rombelWalas = $this->rombelDiampuWaliKelas($user, $tenantId);
        if ($rombelWalas->isEmpty()) {
            return false;
        }

        if ($perangkat->rombel_id !== null && $rombelWalas->pluck('id')->contains((int) $perangkat->rombel_id)) {
            return true;
        }

        $namaRombel = $rombelWalas
            ->pluck('nama_rombel')
            ->filter()
            ->map(fn ($v) => mb_strtolower(trim((string) $v)))
            ->all();

        $kelasDoc = mb_strtolower(trim((string) $perangkat->kelas));

        return $kelasDoc !== '' && in_array($kelasDoc, $namaRombel, true);
    }

    public function authorizeViewPerangkat(User $user, PerangkatPembelajaran $perangkat): void
    {
        if (! $this->canViewPerangkat($user, $perangkat)) {
            abort(403, 'Anda tidak memiliki akses ke perangkat pembelajaran ini.');
        }
    }

    /**
     * Rombel yang secara aktif diampu oleh wali kelas pada tenant aktif.
     */
    public function rombelDiampuWaliKelas(User $walas, ?int $tenantId = null): Collection
    {
        $tenantId ??= $this->currentTenantId($walas);

        $query = Rombel::query()->where('guru_id', $walas->id);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get(['id', 'nama_rombel', 'tingkat']);
    }

    /**
     * Query read-only untuk Wali Kelas melihat perangkat guru pengajar rombel binaannya.
     */
    public function queryUntukWaliKelas(User $walas): Builder
    {
        $tenantId = $this->currentTenantId($walas);
        $rombelWalas = $this->rombelDiampuWaliKelas($walas, $tenantId);

        $query = PerangkatPembelajaran::query()
            ->with(['guru:id,nama_lengkap', 'rombel:id,nama_rombel'])
            ->where('guru_id', '!=', $walas->id)
            ->where('dibagikan_ke_walas', true);

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($rombelWalas->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $rombelIds = $rombelWalas->pluck('id')->all();
        $namaRombel = $rombelWalas->pluck('nama_rombel')->filter()->values()->all();

        return $query->where(function (Builder $q) use ($rombelIds, $namaRombel): void {
            $q->whereIn('rombel_id', $rombelIds);
            if ($namaRombel !== []) {
                $q->orWhereIn('kelas', $namaRombel);
            }
        });
    }

    /**
     * Informasi kop surat sekolah (hanya skalar, bukan model Eloquent).
     *
     * @return array<string, string>
     */
    public function kopSekolah(?int $tenantId = null): array
    {
        $query = SekolahSettings::query();
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $s = $query->first([
            'nama_sekolah',
            'npsn',
            'alamat',
            'kecamatan',
            'kota',
            'provinsi',
            'kepala_sekolah',
            'telepon',
            'email',
        ]);

        return [
            'nama_sekolah' => (string) ($s?->nama_sekolah ?: 'UPTD SD Negeri Unggulan Mongisidi 1'),
            'npsn' => (string) ($s?->npsn ?: '-'),
            'alamat' => trim(implode(', ', array_filter([
                $s?->alamat,
                $s?->kecamatan ? 'Kec. '.$s->kecamatan : null,
                $s?->kota,
                $s?->provinsi,
            ]))) ?: '-',
            'kepala_sekolah' => (string) ($s?->kepala_sekolah ?: 'Kepala Sekolah'),
            'telepon' => (string) ($s?->telepon ?: ''),
            'email' => (string) ($s?->email ?: ''),
        ];
    }

    /**
     * Template siap pakai Kurikulum Merdeka & Pembelajaran Mendalam untuk seluruh 7 jenis perangkat.
     *
     * @return array<string, mixed>
     */
    public function templateForJenis(string $jenis): array
    {
        return match ($jenis) {
            PerangkatPembelajaran::JENIS_CP => [
                'elemen' => 'Bilangan / Pemahaman Konsep',
                'capaian_pembelajaran' => 'Pada akhir fase, peserta didik mampu memahami, menerapkan, dan merefleksikan konsep inti secara kontekstual dalam kehidupan sehari-hari.',
                'kompetensi_inti' => "1. Mengidentifikasi konsep utama secara tepat\n2. Menyelesaikan masalah kontekstual secara mandiri dan kolaboratif\n3. Mengomunikasikan proses dan hasil pemecahan masalah",
                'konten_materi' => "1. Konsep dasar materi\n2. Operasi dan penerapannya\n3. Pemecahan masalah sehari-hari",
                'catatan_fase' => 'Diselaraskan dengan BSKAP Kurikulum Merdeka terbaru.',
            ],
            PerangkatPembelajaran::JENIS_TP => [
                'elemen' => 'Pemahaman & Penerapan',
                'capaian_rujukan' => 'Merujuk pada CP Fase aktif mata pelajaran terkait.',
                'daftar_tp' => [
                    ['kode' => 'TP-1', 'deskripsi' => 'Peserta didik mampu menjelaskan konsep utama melalui contoh konkret.', 'materi' => 'Konsep Dasar', 'jp' => '4 JP'],
                    ['kode' => 'TP-2', 'deskripsi' => 'Peserta didik mampu menyelesaikan persoalan kontekstual secara tepat.', 'materi' => 'Penerapan Masalah', 'jp' => '4 JP'],
                ],
                'indikator_ketercapaian' => 'Peserta didik mencapai kriteria minimal Cakap/Baik pada asesmen formatif dan praktik.',
            ],
            PerangkatPembelajaran::JENIS_ATP => [
                'elemen' => 'Alur Pembelajaran 1 Tahun / Semester',
                'prasyarat' => 'Peserta didik telah menguasai kompetensi prasyarat pada jenjang sebelumnya.',
                'alur_item' => [
                    [
                        'urutan' => '1',
                        'kode_tp' => 'TP-1',
                        'tujuan_pembelajaran' => 'Memahami konsep inti dan mengaitkannya dengan pengalaman sehari-hari.',
                        'materi_pokok' => 'Pengenalan & Konsep Dasar',
                        'alokasi_jp' => '6 JP',
                        'semester' => 'Ganjil',
                        'dimensi_profil' => 'Penalaran Kritis, Kemandirian',
                    ],
                    [
                        'urutan' => '2',
                        'kode_tp' => 'TP-2',
                        'tujuan_pembelajaran' => 'Menerapkan konsep untuk memecahkan proyek kolaboratif kelas.',
                        'materi_pokok' => 'Aplikasi & Proyek Mini',
                        'alokasi_jp' => '8 JP',
                        'semester' => 'Ganjil',
                        'dimensi_profil' => 'Kolaborasi, Kreativitas, Komunikasi',
                    ],
                ],
            ],
            PerangkatPembelajaran::JENIS_PROTA => [
                'daftar_unit' => [
                    ['semester' => 'Ganjil', 'nomor_unit' => 'Unit 1', 'materi_topik' => 'Fondasi Konsep & Eksplorasi', 'alokasi_jp' => '16 JP', 'keterangan' => 'Juli – Agustus'],
                    ['semester' => 'Ganjil', 'nomor_unit' => 'Unit 2', 'materi_topik' => 'Aplikasi Kontekstual & Proyek', 'alokasi_jp' => '18 JP', 'keterangan' => 'September – November'],
                    ['semester' => 'Genap', 'nomor_unit' => 'Unit 3', 'materi_topik' => 'Pendalaman & Pemecahan Masalah', 'alokasi_jp' => '18 JP', 'keterangan' => 'Januari – Maret'],
                    ['semester' => 'Genap', 'nomor_unit' => 'Unit 4', 'materi_topik' => 'Refleksi, Asesmen & Karya Akhir', 'alokasi_jp' => '16 JP', 'keterangan' => 'April – Mei'],
                ],
                'catatan_tahunan' => 'Alokasi JP disesuaikan dengan kalender pendidikan dan minggu efektif sekolah.',
            ],
            PerangkatPembelajaran::JENIS_PROMES => [
                'bulan_efektif' => 'Juli, Agustus, September, Oktober, November, Desember',
                'daftar_minggu' => [
                    ['bulan' => 'Juli', 'minggu_ke' => 'M3–M4', 'materi_topik' => 'Asesmen Diagnostik & Konsep Awal', 'alokasi_jp' => '8 JP', 'keterangan' => 'Awal semester'],
                    ['bulan' => 'Agustus', 'minggu_ke' => 'M1–M4', 'materi_topik' => 'Eksplorasi Materi & Latihan Terbimbing', 'alokasi_jp' => '16 JP', 'keterangan' => 'Asesmen formatif 1'],
                    ['bulan' => 'September', 'minggu_ke' => 'M1–M3', 'materi_topik' => 'Proyek Kontekstual & Presentasi', 'alokasi_jp' => '12 JP', 'keterangan' => 'PTS / Sumatif lingkup'],
                ],
                'catatan_kalender' => 'Memperhitungkan hari libur nasional, jeda tengah semester, dan kegiatan P5.',
            ],
            PerangkatPembelajaran::JENIS_MODUL_AJAR => [
                'tujuan_pembelajaran' => 'Peserta didik mampu memahami konsep, mengaplikasikannya dalam situasi nyata, dan merefleksikan manfaat pembelajarannya.',
                'pm' => [
                    'berkesadaran' => true,
                    'bermakna' => true,
                    'menggembirakan' => true,
                ],
                'dimensi' => ['Penalaran Kritis', 'Kolaborasi', 'Komunikasi'],
                'pengalaman' => [
                    'memahami' => 'Eksplorasi fenomena kontekstual dan diskusi terbimbing memahami konsep inti.',
                    'mengaplikasi' => 'Kerja kelompok memecahkan studi kasus nyata dan mempresentasikan solusi.',
                    'merefleksi' => 'Siswa menuliskan hal baru yang dipahami, tantangan, dan rencana penerapan.',
                ],
                'pendahuluan' => 'Salam, doa, apersepsi bermakna, penyampaian tujuan belajar, dan asesmen awal ringan (10 menit).',
                'kegiatan_inti' => 'Aktivitas eksplorasi, kolaborasi kelompok, pendampingan terdiferensiasi, serta umpan balik konstruktif (50 menit).',
                'penutup' => 'Refleksi bersama, penguatan konsep, tindak lanjut, dan doa penutup (10 menit).',
                'asesmen' => 'Formatif (observasi & rubrik diskusi) serta sumatif singkat berbasis pemecahan masalah.',
                'media_sumber' => 'Buku teks Kemendikdasmen, LKPD kontekstual, papan tulis/proyektor, dan bahan ajar Kelas Digital.',
                'refleksi_guru' => 'Catat keberhasilan, siswa yang perlu pendampingan khusus, dan perbaikan pertemuan berikutnya.',
            ],
            PerangkatPembelajaran::JENIS_KKM => [
                'kompleksitas' => 75,
                'daya_dukung' => 78,
                'intake' => 75,
                'nilai_kkm' => 76,
                'interval_perlu_bimbingan' => '0 – 65 (Perlu Bimbingan: belum mencapai ketuntasan, perlu remedial terarah)',
                'interval_cukup' => '66 – 75 (Cukup: mendekati ketuntasan, perlu penguatan bagian tertentu)',
                'interval_baik' => '76 – 88 (Baik / Tuntas: mencapai tujuan pembelajaran dengan baik)',
                'interval_sangat_baik' => '89 – 100 (Sangat Baik: melampaui ekspektasi, siap pengayaan)',
                'catatan_penilaian' => 'Kriteria Ketercapaian Tujuan Pembelajaran (KKTP) dievaluasi melalui rubrik dan deskripsi capaian.',
            ],
            default => [],
        };
    }
}
