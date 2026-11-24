<?php

namespace App\Services\Dapodik;

use App\Models\JadwalPelajaran;
use App\Models\Kehadiran;
use App\Models\MataPelajaran;
use App\Models\NilaiErapot;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DapodikBridgeAcademicSyncService
{
    public function isClassName(string $name): bool
    {
        $lower = strtolower(trim($name));
        if (str_starts_with($lower, 'kelas')) {
            return true;
        }
        if (preg_match('/^kelas\s+\d+/i', $lower)) {
            return true;
        }
        if (preg_match('/^(xii|xi|x|ix|viii|vii|vi|v|iv|iii|ii|i)[\s\.]/i', $lower)) {
            return true;
        }
        if (preg_match('/^\d+\s*[a-zA-Z]/', $name)) {
            return true;
        }

        return false;
    }

    public function syncMataPelajaran(array $data, callable $batchRunner, callable $jenjangNormalizer): array
    {
        $sekolah = SekolahSettings::first();
        $jenjangSekolah = $sekolah?->jenjang ?? 'SD';

        return $batchRunner(
            $data,
            function (array $m) use ($jenjangSekolah, $jenjangNormalizer): string {
                $nama = trim($m['nama'] ?? $m['nama_mata_pelajaran'] ?? $m['mata_pelajaran'] ?? '');
                if ($nama === '' || $this->isClassName($nama)) {
                    return 'dilewati';
                }

                $kode = trim($m['kode'] ?? $m['kode_mata_pelajaran'] ?? $m['mata_pelajaran_id'] ?? '');
                $rawJenjang = $m['tingkat_pendidikan_id_str'] ?? $m['jenjang'] ?? null;
                $itemJenjang = $jenjangNormalizer($rawJenjang) ?? $jenjangSekolah;

                $existing = MataPelajaran::where('nama', $nama)
                    ->where('jenjang', $itemJenjang)
                    ->first();

                $payload = [
                    'nama' => $nama,
                    'kode' => $kode ?: ($existing?->kode ?? null),
                    'jenjang' => $itemJenjang,
                    'kelompok' => $m['kelompok'] ?? $existing?->kelompok ?? 'A',
                    'aktif' => true,
                ];

                if ($existing) {
                    $existing->update($payload);

                    return 'diperbarui';
                }

                MataPelajaran::create($payload);

                return 'berhasil';
            }
        );
    }

    public function syncJadwal(array $data, Semester $semester, callable $batchRunner): array
    {
        $sekolah = SekolahSettings::first();
        $jenjangSekolah = $sekolah?->jenjang ?? 'SD';

        return $batchRunner(
            $data,
            function (array $j) use ($semester, $jenjangSekolah): string {
                $dapodikId = $j['pembelajaran_id'] ?? $j['jadwal_id'] ?? $j['dapodik_id'] ?? null;
                $namaMapel = trim($j['nama_mata_pelajaran'] ?? $j['mata_pelajaran_id_str'] ?? $j['nama_mapel'] ?? $j['mata_pelajaran'] ?? '');
                $namaRombel = trim($j['nama_rombel'] ?? $j['rombongan_belajar_id_str'] ?? $j['kelas'] ?? '');

                $rombelObj = null;
                if (! empty($j['rombongan_belajar_id'])) {
                    $rombelObj = Rombel::where('dapodik_id', $j['rombongan_belajar_id'])
                        ->where('semester_id', $semester->id)
                        ->first();
                    if ($namaRombel === '' && $rombelObj) {
                        $namaRombel = $rombelObj->nama_rombel;
                    }
                }

                if ($namaMapel !== '' && ! $this->isClassName($namaMapel)) {
                    MataPelajaran::firstOrCreate(
                        [
                            'nama' => $namaMapel,
                            'jenjang' => $jenjangSekolah,
                        ],
                        [
                            'kode' => $j['kode_mata_pelajaran'] ?? null,
                            'kelompok' => 'A',
                            'aktif' => true,
                        ]
                    );
                }

                $guruId = null;
                if (! empty($j['ptk_id'])) {
                    $ptk = Ptk::where('dapodik_id', $j['ptk_id'])->first();
                    $guruId = User::forSchool()->where(function ($q) use ($j, $ptk) {
                        $q->where('dapodik_id', $j['ptk_id']);
                        if ($ptk?->nip) {
                            $q->orWhere('nama_pengguna', $ptk->nip);
                        }
                        if ($ptk?->nuptk) {
                            $q->orWhere('nama_pengguna', $ptk->nuptk);
                        }
                    })->value('id');
                }
                $rawGuru = $j['ptk_id_str'] ?? $j['nama_guru'] ?? $j['nama_ptk'] ?? null;
                if (! $guruId && ! empty($rawGuru)) {
                    $namaGuru = trim((string) $rawGuru);
                    $guruId = User::forSchool()->whereRaw('LOWER(nama_lengkap) = ?', [strtolower($namaGuru)])->value('id');
                }
                if (! $guruId && ! empty($j['guru_id'])) {
                    $guruId = User::forSchool()->whereKey($j['guru_id'])->value('id');
                }
                if (! $guruId) {
                    $guruId = auth()->id() ?? User::forSchool()->where('peran', 'admin')->value('id') ?? User::forSchool()->value('id');
                }

                $hari = trim($j['hari'] ?? '');
                $validHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                if (! in_array($hari, $validHari, true)) {
                    $hari = 'Senin';
                }
                $jamMulai = ! empty($j['jam_mulai']) ? $j['jam_mulai'] : '07:00:00';
                $jamSelesai = ! empty($j['jam_selesai']) ? $j['jam_selesai'] : '08:00:00';

                $existing = null;
                if ($dapodikId) {
                    $existing = JadwalPelajaran::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }
                if (! $existing && ! empty($namaRombel) && ! empty($namaMapel)) {
                    $existingQuery = JadwalPelajaran::where('semester_id', $semester->id)
                        ->where('kelas', $namaRombel)
                        ->where('mata_pelajaran', $namaMapel);

                    if (! empty($hari)) {
                        $existingQuery->where('hari', $hari);
                    }
                    $existing = $existingQuery->first();
                }

                $jadwalData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'rombel_id' => $rombelObj?->id,
                    'kelas' => $namaRombel,
                    'mata_pelajaran' => $namaMapel,
                    'hari' => $hari,
                    'jam_mulai' => $jamMulai,
                    'jam_selesai' => $jamSelesai,
                    'ruangan' => $j['ruangan'] ?? null,
                    'guru_id' => $guruId,
                ];

                if ($existing) {
                    $existing->update($jadwalData);

                    return 'diperbarui';
                }

                JadwalPelajaran::create($jadwalData);

                return 'berhasil';
            },
            function ($j, \Throwable $e): void {
                Log::error('Gagal sync jadwal/pembelajaran: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine());
            }
        );
    }

    public function export(Request $request, string $modul): JsonResponse
    {
        $semesterId = $request->query('semester_id');
        $semester = $semesterId ? Semester::where('semester_id', $semesterId)->first() : null;

        return match ($modul) {
            'nilai-rapor' => $this->exportNilaiRapor($semester),
            'kehadiran' => $this->exportKehadiran($semester),
            'sekolah' => $this->exportSekolah(),
            'peserta-didik' => $this->exportPesertaDidik($semester),
            default => response()->json([
                'success' => false,
                'message' => "Modul export '{$modul}' tidak didukung. Pilihan: nilai-rapor, kehadiran, sekolah, peserta-didik.",
            ], 404),
        };
    }

    private function exportNilaiRapor(?Semester $semester): JsonResponse
    {
        $query = NilaiErapot::with('siswa');
        if ($semester) {
            $siswaIds = Siswa::where('semester_id', $semester->id)->pluck('id');
            $query->whereIn('siswa_id', $siswaIds)
                ->where('tahun_ajaran', $semester->tahun_ajaran);
        }

        $items = $query->get()->map(fn ($nilai) => [
            'peserta_didik_id' => $nilai->siswa?->dapodik_id,
            'nisn' => $nilai->siswa?->nisn,
            'nama' => $nilai->siswa?->nama_peserta_didik,
            'semester_id' => $semester?->semester_id ?? $nilai->semester,
            'mata_pelajaran' => $nilai->mata_pelajaran,
            'kelas' => $nilai->kelas,
            'nilai_akhir' => (float) $nilai->nilai_akhir,
            'predikat' => $nilai->predikat,
            'deskripsi' => $nilai->deskripsi_capaian,
        ]);

        return response()->json([
            'success' => true,
            'modul' => 'nilai-rapor',
            'total' => $items->count(),
            'data' => $items,
        ]);
    }

    private function exportKehadiran(?Semester $semester): JsonResponse
    {
        $query = Kehadiran::with('siswa');
        if ($semester) {
            $siswaIds = Siswa::where('semester_id', $semester->id)->pluck('id');
            $query->whereIn('siswa_id', $siswaIds);
        }

        $items = $query->get()->map(fn ($k) => [
            'peserta_didik_id' => $k->siswa?->dapodik_id,
            'nisn' => $k->siswa?->nisn,
            'nama' => $k->siswa?->nama_peserta_didik,
            'semester_id' => $semester?->semester_id ?? '',
            'tanggal' => $k->tanggal?->format('Y-m-d'),
            'status' => $k->status,
            'keterangan' => $k->keterangan,
        ]);

        return response()->json([
            'success' => true,
            'modul' => 'kehadiran',
            'total' => $items->count(),
            'data' => $items,
        ]);
    }

    private function exportSekolah(): JsonResponse
    {
        $sekolah = SekolahSettings::first();
        if (! $sekolah) {
            return response()->json([
                'success' => false,
                'message' => 'Data sekolah belum dikonfigurasi.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'modul' => 'sekolah',
            'total' => 1,
            'data' => [
                'npsn' => $sekolah->npsn,
                'nama' => $sekolah->nama_sekolah,
                'alamat' => $sekolah->alamat,
                'telepon' => $sekolah->telepon,
                'email' => $sekolah->email,
                'kepala_sekolah' => $sekolah->kepala_sekolah,
            ],
        ]);
    }

    private function exportPesertaDidik(?Semester $semester): JsonResponse
    {
        $query = Siswa::where('aktif', true)->whereNull('archived_at');
        if ($semester) {
            $query->where('semester_id', $semester->id);
        }

        $items = $query->get()->map(fn ($s) => [
            'peserta_didik_id' => $s->dapodik_id,
            'nisn' => $s->nisn,
            'nipd' => $s->nis,
            'nama' => $s->nama_peserta_didik,
            'jenis_kelamin' => $s->jenis_kelamin,
            'kelas' => $s->kelas,
            'tempat_lahir' => $s->tempat_lahir,
            'tanggal_lahir' => $s->tanggal_lahir?->format('Y-m-d'),
            'agama' => $s->agama,
        ]);

        return response()->json([
            'success' => true,
            'modul' => 'peserta-didik',
            'total' => $items->count(),
            'data' => $items,
        ]);
    }
}
