<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DapodikSyncLog;
use App\Models\JadwalPelajaran;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\SekolahSettings;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DapodikSyncController extends Controller
{
    public function store(Request $request, string $modul)
    {
        if (TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Sinkronisasi Bridge lama dinonaktifkan untuk multi-sekolah sampai seluruh data terisolasi.',
            ], 503);
        }

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
            'jadwal' => 'jadwal',
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

    /**
     * Eksekusi batch sinkronisasi secara atomik per-kelompok (default 50 baris)
     * menggunakan transaksi luar untuk atomisitas batch dan savepoint per-baris
     * (`DB::transaction` bersarang) agar 1 baris korup tidak membatalkan baris
     * valid lain (mencegah error PostgreSQL `25P02` aborted transaction).
     *
     * @param  array<int, mixed>  $items
     * @param  callable(mixed): string  $rowHandler  Mengembalikan 'berhasil'|'diperbarui'|'dilewati'
     * @param  callable(mixed, \Throwable): void|null  $onError
     * @return array{berhasil: int, gagal: int, diperbarui: int, dilewati: int}
     */
    private function processInBatches(array $items, callable $rowHandler, ?callable $onError = null): array
    {
        $stats = [
            'berhasil' => 0,
            'gagal' => 0,
            'diperbarui' => 0,
            'dilewati' => 0,
        ];

        $batchSize = max(1, (int) config('dapodik.batch_size', 50));

        foreach (array_chunk($items, $batchSize) as $chunk) {
            DB::transaction(function () use ($chunk, $rowHandler, $onError, &$stats) {
                foreach ($chunk as $item) {
                    try {
                        $outcome = DB::transaction(fn () => $rowHandler($item));
                        if (isset($stats[$outcome])) {
                            $stats[$outcome]++;
                        }
                    } catch (\Throwable $e) {
                        $stats['gagal']++;
                        if ($onError !== null) {
                            $onError($item, $e);
                        }
                    }
                }
            }, 3);
        }

        return $stats;
    }

    private function syncSekolah(array $data): array
    {
        $currentUserId = auth()->id() ?? 1;

        return $this->processInBatches(
            $data,
            function (array $s) use ($currentUserId): string {
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

                $existing = SekolahSettings::where('npsn', $s['npsn'] ?? null)
                    ->orWhere('guru_id', $currentUserId)
                    ->first();
                if ($existing) {
                    $existing->update($mapped);

                    return 'diperbarui';
                }

                SekolahSettings::create($mapped);

                return 'berhasil';
            },
            function ($s, \Throwable $e): void {
                Log::warning('Gagal sync sekolah settings: ' . $e->getMessage());
            }
        );
    }

    private function syncPesertaDidik(array $data, Semester $semester): array
    {
        return $this->processInBatches(
            $data,
            function (array $pd) use ($semester): string {
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

                    return 'diperbarui';
                }

                $siswaData['uuid'] = Str::uuid();
                Siswa::create($siswaData);

                return 'berhasil';
            },
            function ($pd, \Throwable $e): void {
                $id = is_array($pd) ? ($pd['peserta_didik_id'] ?? $pd['dapodik_id'] ?? 'unknown') : 'unknown';
                Log::warning("Gagal sync peserta didik dapodik_id: {$id}: " . $e->getMessage());
            }
        );
    }

    private function syncPtk(array $data, Semester $semester): array
    {
        return $this->processInBatches(
            $data,
            function (array $p) use ($semester): string {
                $dapodikId = $p['ptk_id'] ?? $p['dapodik_id'] ?? null;
                $nama = trim($p['nama'] ?? '');
                $nip = trim($p['nip'] ?? '');
                $nuptk = trim($p['nuptk'] ?? '');
                $jenisPtk = strtolower(trim($p['jenis_ptk'] ?? $p['jenis_ptk_id'] ?? ''));
                $jkRaw = strtoupper(substr(trim($p['jenis_kelamin'] ?? ''), 0, 1));
                $jk = in_array($jkRaw, ['L', 'P'], true) ? $jkRaw : null;

                if (empty($nama) || (empty($nip) && empty($nuptk))) {
                    return 'dilewati';
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
                    'jk' => $jk,
                    'gelar_depan' => isset($p['gelar_depan']) ? substr(trim($p['gelar_depan']), 0, 50) ?: null : null,
                    'gelar_belakang' => isset($p['gelar_belakang']) ? substr(trim($p['gelar_belakang']), 0, 100) ?: null : null,
                    'jenis_ptk' => $p['jenis_ptk'] ?? 'Guru',
                    'jabatan' => $p['jabatan_ptk'] ?? $p['jabatan'] ?? null,
                ];

                $outcome = 'berhasil';
                if ($existing) {
                    $existing->update($ptkData);
                    $outcome = 'diperbarui';
                } else {
                    Ptk::create($ptkData);
                }

                $username = $nip ?: $nuptk;
                $isActive = strtolower($p['status_aktif'] ?? $p['status'] ?? 'aktif') === 'aktif';
                $isTendik = in_array($jenisPtk, ['3', '4', 'tendik', 'tenaga kependidikan', 'tenaga'], true)
                    || str_contains($jenisPtk, 'tendik') || str_contains($jenisPtk, 'tenaga');
                $peran = $isTendik ? 'admin' : 'guru';

                $existingUser = null;
                if ($dapodikId) {
                    $existingUser = User::withTrashed()->where('dapodik_id', $dapodikId)->first();
                }
                if (!$existingUser && $username) {
                    $existingUser = User::withTrashed()->where('nama_pengguna', $username)->first();
                }

                if ($existingUser) {
                    if ($existingUser->trashed()) {
                        $existingUser->restore();
                    }
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
                    while (User::withTrashed()->where('nama_pengguna', $finalUsername)->exists()) {
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

                return $outcome;
            },
            function ($p, \Throwable $e): void {
                $id = is_array($p) ? ($p['ptk_id'] ?? $p['dapodik_id'] ?? 'unknown') : 'unknown';
                Log::warning("Gagal sync PTK dapodik_id {$id}: " . $e->getMessage());
            }
        );
    }

    private function syncRombel(array $data, Semester $semester): array
    {
        return $this->processInBatches(
            $data,
            function (array $r) use ($semester): string {
                $dapodikId = $r['rombongan_belajar_id'] ?? $r['dapodik_id'] ?? null;

                $existing = null;
                if ($dapodikId) {
                    $existing = Rombel::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }
                if (!$existing && !empty($r['nama'] ?? $r['nama_rombel'])) {
                    $namaRombel = $r['nama'] ?? $r['nama_rombel'];
                    $existing = Rombel::where('nama_rombel', $namaRombel)
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
                    'kurikulum' => $r['kurikulum'] ?? $r['nama_kurikulum'] ?? null,
                    'jenis_rombel' => $r['jenis_rombel'] ?? $r['jenis_rombel_id'] ?? null,
                    'guru_id' => $guruId,
                ];

                if ($existing) {
                    $existing->update($rombelData);

                    return 'diperbarui';
                }

                Rombel::create($rombelData);

                return 'berhasil';
            }
        );
    }

    private function syncJadwal(array $data, Semester $semester): array
    {
        return $this->processInBatches(
            $data,
            function (array $j) use ($semester): string {
                $dapodikId = $j['jadwal_id'] ?? $j['dapodik_id'] ?? null;

                $existing = null;
                if ($dapodikId) {
                    $existing = JadwalPelajaran::where('dapodik_id', $dapodikId)
                        ->where('semester_id', $semester->id)
                        ->first();
                }
                if (!$existing && !empty($j['nama_rombel'] ?? $j['kelas']) && !empty($j['hari']) && !empty($j['jam_mulai'])) {
                    $existing = JadwalPelajaran::where('semester_id', $semester->id)
                        ->where('kelas', $j['nama_rombel'] ?? $j['kelas'])
                        ->where('hari', $j['hari'])
                        ->where('jam_mulai', $j['jam_mulai'])
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

                    return 'diperbarui';
                }

                JadwalPelajaran::create($jadwalData);

                return 'berhasil';
            }
        );
    }

    public function status(Request $request)
    {
        $isTenant = TenantContext::check() || config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists();
        $semesters = $isTenant ? collect() : Semester::orderByDesc('semester_id')->get();
        $stats = [
            'semesters' => $semesters->count(),
            'siswa' => Siswa::count(),
            'ptk' => $isTenant ? null : Ptk::count(),
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
