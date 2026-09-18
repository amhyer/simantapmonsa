<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\JadwalPelajaran;
use App\Models\SekolahSettings;
use App\Models\DapodikSyncLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DapodikSyncController extends Controller
{
    public function store(Request $request, string $modul)
    {
        $request->validate([
            'semester_id' => 'required|string',
            'tahun_ajaran' => 'required|string',
            'nama_semester' => 'required|in:ganjil,genap',
            'data' => 'required|array',
        ]);

        $semesterId = $request->input('semester_id');
        $semester = Semester::firstOrCreate(
            ['semester_id' => $semesterId],
            [
                'tahun_ajaran' => $request->input('tahun_ajaran'),
                'nama_semester' => $request->input('nama_semester'),
            ]
        );

        $data = $request->input('data', []);

        $result = match ($modul) {
            'sekolah' => $this->syncSekolah($data),
            'peserta-didik' => $this->syncPesertaDidik($data, $semester),
            'ptk' => $this->syncPtk($data, $semester),
            'rombongan-belajar' => $this->syncRombel($data, $semester),
            'jadwal' => $this->syncJadwal($data, $semester),
            default => null,
        };

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Modul tidak dikenal: ' . $modul], 404);
        }

        $tipe = match ($modul) {
            'sekolah' => 'semua',
            'peserta-didik' => 'siswa',
            'ptk' => 'gtk',
            'rombongan-belajar' => 'rombel',
            'jadwal' => 'mapel',
            default => $modul,
        };

        DapodikSyncLog::create([
            'uuid' => Str::uuid(),
            'user_id' => $request->user()?->id,
            'sekolah_npsn' => null,
            'nama_sekolah' => 'Dapodik Sync',
            'tipe' => $tipe,
            'tahun_ajaran' => $request->input('tahun_ajaran'),
            'semester' => $request->input('nama_semester'),
            'total_data' => count($data),
            'berhasil' => $result['berhasil'],
            'gagal' => $result['gagal'],
            'diperbarui' => $result['diperbarui'],
            'dilewati' => $result['dilewati'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'modul' => $modul,
            'jumlah' => count($data),
            'berhasil' => $result['berhasil'],
            'diperbarui' => $result['diperbarui'],
            'dilewati' => $result['dilewati'],
            'gagal' => $result['gagal'],
        ]);
    }

    private function syncSekolah(array $data): array
    {
        $berhasil = 0;
        $gagal = 0;
        $diperbarui = 0;
        $dilewati = 0;

        $currentUserId = auth()->id() ?? 1;

        foreach ($data as $s) {
            try {
                $mapped = [
                    'guru_id' => $currentUserId,
                    'npsn' => $s['npsn'] ?? '',
                    'nama_sekolah' => $s['nama'] ?? $s['nama_sekolah'] ?? '',
                    'alamat' => $s['alamat_jalan'] ?? $s['alamat'] ?? '',
                    'telepon' => $s['telepon'] ?? '',
                    'email' => $s['email'] ?? '',
                    'kepala_sekolah' => $s['nama_kepala_sekolah'] ?? $s['kepala_sekolah'] ?? null,
                    'kecamatan' => $s['nama_kecamatan'] ?? $s['kecamatan'] ?? null,
                    'kota' => $s['nama_kabupaten'] ?? $s['kota'] ?? null,
                    'provinsi' => $s['nama_propinsi'] ?? $s['provinsi'] ?? null,
                    'kode_pos' => $s['kode_pos'] ?? null,
                    'website' => $s['website'] ?? null,
                    'jenjang' => $s['jenjang'] ?? $s['tipe_sekolah'] ?? null,
                    'jenis_sekolah' => $s['status_sekolah'] ?? $s['jenis_sekolah'] ?? null,
                    'akreditasi' => $s['akreditasi'] ?? null,
                    'status' => 'aktif',
                ];

                $existing = SekolahSettings::where('npsn', $s['npsn'] ?? null)->first();
                if ($existing) {
                    $existing->update($mapped);
                    $diperbarui++;
                } else {
                    SekolahSettings::create($mapped);
                    $berhasil++;
                }
            } catch (\Exception $e) {
                $gagal++;
                Log::warning("Gagal sync sekolah settings: " . $e->getMessage());
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'diperbarui' => $diperbarui, 'dilewati' => $dilewati];
    }

    private function syncPesertaDidik(array $data, Semester $semester): array
    {
        $berhasil = 0;
        $gagal = 0;
        $diperbarui = 0;
        $dilewati = 0;

        foreach ($data as $pd) {
            try {
                $dapodikId = $pd['peserta_didik_id'] ?? $pd['dapodik_id'] ?? null;
                $nisn = $pd['nisn'] ?? null;

                $existing = null;
                if ($dapodikId) {
                    $existing = Siswa::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }
                if (!$existing && $nisn) {
                    $existing = Siswa::where('nisn', $nisn)
                        ->where('semester_id', $semester->id)
                        ->first();
                }

                $rombelId = null;
                $guruId = null;
                $rombelName = $pd['nama_rombel'] ?? $pd['rombel'] ?? $pd['kelas'] ?? null;
                if ($rombelName) {
                    $rombel = Rombel::where('nama_rombel', $rombelName)
                        ->where('semester_id', $semester->id)
                        ->first();
                    if (!$rombel) {
                        $rombel = Rombel::where('nama_rombel', 'like', '%' . $rombelName . '%')
                            ->where('semester_id', $semester->id)
                            ->first();
                    }
                    if ($rombel) {
                        $rombelId = $rombel->id;
                        $guruId = $rombel->guru_id;
                    }
                }

                $siswaData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'nis' => $pd['nis'] ?? $nisn ?? 'DAPODIK-' . Str::random(8),
                    'nisn' => $nisn,
                    'nama_peserta_didik' => $pd['nama'] ?? $pd['nama_peserta_didik'] ?? '',
                    'kelas' => $rombelName ?? '',
                    'jenis_kelamin' => strtoupper(substr($pd['jenis_kelamin'] ?? 'L', 0, 1)),
                    'nama_orang_tua' => $pd['nama_ayah'] ?? $pd['nama_ibu'] ?? null,
                    'telepon' => $pd['telepon'] ?? null,
                    'aktif' => ($pd['status'] ?? 'aktif') === 'aktif',
                    'status_siswa' => $pd['status'] ?? 'aktif',
                    'guru_id' => $guruId,
                    'rombel_id' => $rombelId,
                    'rekaman' => $pd,
                ];

                if ($existing) {
                    $existing->update($siswaData);
                    $diperbarui++;
                } else {
                    $siswaData['uuid'] = Str::uuid();
                    Siswa::create($siswaData);
                    $berhasil++;
                }
            } catch (\Exception $e) {
                $gagal++;
                $msg = "Gagal sync peserta didik dapodik_id: " . ($pd['peserta_didik_id'] ?? $pd['dapodik_id'] ?? 'unknown') . ": " . $e->getMessage();
                Log::warning($msg);
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'diperbarui' => $diperbarui, 'dilewati' => $dilewati];
    }

    private function syncPtk(array $data, Semester $semester): array
    {
        $berhasil = 0;
        $gagal = 0;
        $diperbarui = 0;
        $dilewati = 0;

        foreach ($data as $p) {
            try {
                $dapodikId = $p['ptk_id'] ?? $p['dapodik_id'] ?? null;
                $nama = trim($p['nama'] ?? '');
                $nip = trim($p['nip'] ?? '');
                $nuptk = trim($p['nuptk'] ?? '');
                $jenisPtk = strtolower(trim($p['jenis_ptk'] ?? $p['jenis_ptk_id'] ?? ''));

                if (empty($nama) || (empty($nip) && empty($nuptk))) {
                    $dilewati++;
                    continue;
                }

                $existing = null;
                if ($dapodikId) {
                    $existing = Ptk::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }

                $ptkData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'nama' => $nama,
                    'nip' => $nip ?: null,
                    'nuptk' => $nuptk ?: null,
                    'jenis_ptk' => $p['jenis_ptk'] ?? 'Guru',
                    'jabatan' => $p['jabatan_ptk'] ?? $p['jabatan'] ?? null,
                ];

                if ($existing) {
                    $existing->update($ptkData);
                    $diperbarui++;
                } else {
                    Ptk::create($ptkData);
                    $berhasil++;
                }

                $username = $nip ?: $nuptk;
                $isActive = strtolower($p['status_aktif'] ?? $p['status'] ?? 'aktif') === 'aktif';
                $isTendik = in_array($jenisPtk, ['3', '4', 'tendik', 'tenaga kependidikan', 'tenaga'], true)
                    || str_contains($jenisPtk, 'tendik') || str_contains($jenisPtk, 'tenaga');
                $peran = $isTendik ? 'admin' : 'guru';

                $existingUser = null;
                if ($dapodikId) {
                    $existingUser = User::where('dapodik_id', $dapodikId)->first();
                }
                if (!$existingUser && $username) {
                    $existingUser = User::where('nama_pengguna', $username)->first();
                }

                if ($existingUser) {
                    $existingUser->update([
                        'nama_lengkap' => $nama,
                        'nama_pengguna' => $username,
                        'peran' => $peran,
                        'aktif' => $isActive,
                        'archived_at' => null,
                    ]);
                } else {
                    $base = $username ?: ('gtk_' . $dapodikId);
                    $finalUsername = $base;
                    $counter = 1;
                    while (User::where('nama_pengguna', $finalUsername)->exists()) {
                        $finalUsername = $base . '.' . $counter;
                        $counter++;
                    }

                    User::create([
                        'uuid' => Str::uuid(),
                        'nama_lengkap' => $nama,
                        'nama_pengguna' => $finalUsername,
                        'kata_sandi' => bcrypt('guru123'),
                        'peran' => $peran,
                        'aktif' => $isActive,
                        'force_password_change' => true,
                        'dapodik_id' => $dapodikId,
                        'archived_at' => null,
                    ]);
                }
            } catch (\Exception $e) {
                $gagal++;
                Log::warning('Gagal sync PTK dapodik_id ' . ($dapodikId ?? 'unknown') . ': ' . $e->getMessage());
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'diperbarui' => $diperbarui, 'dilewati' => $dilewati];
    }

    private function syncRombel(array $data, Semester $semester): array
    {
        $berhasil = 0;
        $gagal = 0;
        $diperbarui = 0;
        $dilewati = 0;

        foreach ($data as $r) {
            try {
                $dapodikId = $r['rombongan_belajar_id'] ?? $r['dapodik_id'] ?? null;

                $existing = null;
                if ($dapodikId) {
                    $existing = Rombel::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }

                $guruId = null;
                $waliKelasPtkId = $r['ptk_id'] ?? $r['guru_id'] ?? $r['wali_kelas_ptk_id'] ?? null;
                if ($waliKelasPtkId) {
                    $waliUser = User::where('dapodik_id', $waliKelasPtkId)->first();
                    if ($waliUser) {
                        $guruId = $waliUser->id;
                    } else {
                        $ptk = Ptk::where('dapodik_id', $waliKelasPtkId)->first();
                        if ($ptk && $ptk->nip) {
                            $waliUser = User::where('nama_pengguna', $ptk->nip)->first();
                            if ($waliUser) {
                                $guruId = $waliUser->id;
                            }
                        }
                    }
                }

                $rombelData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'nama_rombel' => $r['nama'] ?? $r['nama_rombel'] ?? '',
                    'tingkat' => $r['tingkat_pendidikan_id'] ?? $r['tingkat'] ?? null,
                    'guru_id' => $guruId,
                ];

                if ($existing) {
                    $existing->update($rombelData);
                    $diperbarui++;
                } else {
                    Rombel::create($rombelData);
                    $berhasil++;
                }
            } catch (\Exception $e) {
                $gagal++;
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'diperbarui' => $diperbarui, 'dilewati' => $dilewati];
    }

    private function syncJadwal(array $data, Semester $semester): array
    {
        $berhasil = 0;
        $gagal = 0;
        $diperbarui = 0;
        $dilewati = 0;

        foreach ($data as $j) {
            try {
                $dapodikId = $j['jadwal_id'] ?? $j['dapodik_id'] ?? null;

                $existing = null;
                if ($dapodikId) {
                    $existing = JadwalPelajaran::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }

                $jadwalData = [
                    'dapodik_id' => $dapodikId,
                    'semester_id' => $semester->id,
                    'kelas' => $j['nama_rombel'] ?? $j['kelas'] ?? '',
                    'mata_pelajaran' => $j['nama_mapel'] ?? $j['mata_pelajaran'] ?? '',
                    'hari' => $j['hari'] ?? '',
                    'jam_mulai' => $j['jam_mulai'] ?? '',
                    'jam_selesai' => $j['jam_selesai'] ?? '',
                    'ruangan' => $j['ruangan'] ?? null,
                    'guru_id' => null,
                ];

                if ($existing) {
                    $existing->update($jadwalData);
                    $diperbarui++;
                } else {
                    JadwalPelajaran::create($jadwalData);
                    $berhasil++;
                }
            } catch (\Exception $e) {
                $gagal++;
            }
        }

        return ['berhasil' => $berhasil, 'gagal' => $gagal, 'diperbarui' => $diperbarui, 'dilewati' => $dilewati];
    }

    public function status(Request $request)
    {
        $semesters = Semester::orderByDesc('semester_id')->get();
        $stats = [
            'semesters' => $semesters->count(),
            'siswa' => Siswa::count(),
            'ptk' => Ptk::count(),
            'rombel' => Rombel::count(),
            'jadwal' => JadwalPelajaran::count(),
        ];

        $recentLogs = DapodikSyncLog::orderByDesc('created_at')->limit(10)->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'semesters' => $semesters,
            'recent_logs' => $recentLogs,
        ]);
    }
}
