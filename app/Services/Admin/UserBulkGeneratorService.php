<?php

namespace App\Services\Admin;

use App\Models\PengaturanGuru;
use App\Models\Ptk;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AktivitasService;
use App\Support\InitialPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserBulkGeneratorService
{
    public function __construct(
        private readonly InitialPasswordExportService $exportService,
    ) {}

    /**
     * @param  array<string, mixed>  $takenLowercaseKeys
     */
    public function resolveUniqueUsername(string $base, array $takenLowercaseKeys): string
    {
        $candidate = $base;
        $counter = 1;
        while (isset($takenLowercaseKeys[strtolower($candidate)])) {
            $candidate = $base.'.'.$counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $takenLowercaseKeys
     */
    public function resolveUniqueEmail(string $username, array $takenLowercaseKeys): string
    {
        $candidate = strtolower($username).'@simantap.sch.id';
        $counter = 1;
        while (isset($takenLowercaseKeys[strtolower($candidate)])) {
            $candidate = strtolower($username).'.'.$counter.'@simantap.sch.id';
            $counter++;
        }

        return $candidate;
    }

    public function resolvePtkRole(Ptk $ptk): string
    {
        if ($ptk->isKepsek()) {
            return 'kepsek';
        }

        if (! $ptk->isGuru() && ! empty($ptk->jenis_ptk)) {
            $jenisLower = strtolower((string) $ptk->jenis_ptk);
            if (
                str_contains($jenisLower, 'tendik')
                || str_contains($jenisLower, 'tenaga')
                || $ptk->isKebersihan()
                || $ptk->isSecurity()
            ) {
                return 'tendik';
            }
        }

        return 'guru';
    }

    public function formatPtkDisplayName(Ptk $ptk): string
    {
        return trim(
            ($ptk->gelar_depan ? $ptk->gelar_depan.' ' : '')
            .$ptk->nama
            .($ptk->gelar_belakang ? ', '.$ptk->gelar_belakang : '')
        ) ?: (string) $ptk->nama;
    }

    /**
     * Generate semua akun pengguna otomatis dari data PTK dan Siswa aktif.
     *
     * @return array{
     *     guru_dibuat: int,
     *     siswa_dibuat: int,
     *     dipulihkan: int,
     *     ditautkan: int,
     *     ekspor_url: ?string,
     *     peringatan_sandi: ?string,
     *     message: string
     * }
     */
    public function generate(?int $tenantId, ?int $actorUserId): array
    {
        $guruDibuat = 0;
        $siswaDibuat = 0;
        $dipulihkan = 0;
        $ditautkan = 0;
        $terbatasBatas = false;
        $batasMassal = max(1, (int) config('simantap.reset_massal_max', 100));
        $generatedCredentials = [];

        $semesterAktif = Semester::orderByDesc('semester_id')->first()
            ?? Semester::orderByDesc('id')->first();
        $namaSemesterDefault = $semesterAktif?->nama_semester ? ucfirst($semesterAktif->nama_semester) : 'Ganjil';
        $tahunAjaranDefault = $semesterAktif?->tahun_ajaran ?? (date('Y').'/'.(date('Y') + 1));
        $kkmDefault = function_exists('getKKM') ? getKKM() : 75;

        DB::transaction(function () use (
            $tenantId,
            $batasMassal,
            $namaSemesterDefault,
            $tahunAjaranDefault,
            $kkmDefault,
            &$guruDibuat,
            &$siswaDibuat,
            &$dipulihkan,
            &$ditautkan,
            &$terbatasBatas,
            &$generatedCredentials
        ) {
            $allUsers = User::withoutGlobalScopes()->withTrashed()->get();

            $allUsernames = [];
            $allEmails = [];
            $schoolByUsername = [];
            $schoolByDapodik = [];
            $schoolByNip = [];
            $schoolBySiswaId = [];

            foreach ($allUsers as $u) {
                $uNameKey = strtolower(trim((string) $u->nama_pengguna));
                if ($uNameKey !== '') {
                    $allUsernames[$uNameKey] = $u;
                }
                $uEmailKey = strtolower(trim((string) $u->email));
                if ($uEmailKey !== '') {
                    $allEmails[$uEmailKey] = $u;
                }

                $sameTenant = ($tenantId === null && $u->tenant_id === null)
                    || ($tenantId !== null && (int) $u->tenant_id === (int) $tenantId);

                if ($sameTenant) {
                    if ($uNameKey !== '') {
                        $schoolByUsername[$uNameKey] = $u;
                    }
                    if (! empty($u->dapodik_id)) {
                        $schoolByDapodik[(string) $u->dapodik_id] = $u;
                    }
                    if (! empty($u->nip)) {
                        $schoolByNip[strtolower(trim((string) $u->nip))] = $u;
                    }
                    if ($u->peran === 'siswa' && is_array($u->terhubung_dengan)) {
                        foreach ($u->terhubung_dengan as $sid) {
                            $schoolBySiswaId[(int) $sid] = $u;
                        }
                    }
                }
            }

            // 1. Generate akun Guru / Kepsek / Tendik dari tabel PTK
            $claimedPtksUserIds = [];
            $ptkRows = Ptk::orderBy('id')->get();
            foreach ($ptkRows as $ptk) {
                $rawCandidate = trim((string) ($ptk->nip ?: ($ptk->nuptk ?: Str::slug($ptk->nama, ''))));
                if ($rawCandidate === '') {
                    $rawCandidate = 'guru'.$ptk->id;
                }

                $existing = null;
                if (! empty($ptk->dapodik_id) && isset($schoolByDapodik[(string) $ptk->dapodik_id])) {
                    $cand = $schoolByDapodik[(string) $ptk->dapodik_id];
                    if (! isset($claimedPtksUserIds[$cand->id])) {
                        $existing = $cand;
                    }
                }
                if (! $existing && ! empty($ptk->nip) && isset($schoolByNip[strtolower(trim((string) $ptk->nip))])) {
                    $cand = $schoolByNip[strtolower(trim((string) $ptk->nip))];
                    if (! isset($claimedPtksUserIds[$cand->id])) {
                        $existing = $cand;
                    }
                }
                if (! $existing) {
                    $probe = strtolower($rawCandidate);
                    $probeIdx = 0;
                    while (isset($schoolByUsername[$probe])) {
                        $cand = $schoolByUsername[$probe];
                        if (! isset($claimedPtksUserIds[$cand->id])) {
                            $existing = $cand;
                            break;
                        }
                        $probeIdx++;
                        $probe = strtolower($rawCandidate).'.'.$probeIdx;
                    }
                }

                $namaLengkap = $this->formatPtkDisplayName($ptk);
                $peran = $this->resolvePtkRole($ptk);

                if ($existing) {
                    $claimedPtksUserIds[$existing->id] = true;
                    $syncPayload = [];
                    if ($existing->trashed()) {
                        $existing->restore();
                        $syncPayload['aktif'] = true;
                        $syncPayload['archived_at'] = null;
                        $dipulihkan++;
                        $guruDibuat++;
                    }
                    if ($existing->nama_lengkap !== $namaLengkap && $namaLengkap !== '') {
                        $syncPayload['nama_lengkap'] = $namaLengkap;
                    }
                    if ($existing->peran !== $peran && in_array($existing->peran, ['guru', 'tendik', 'kepsek'], true)) {
                        $syncPayload['peran'] = $peran;
                    }
                    if (! empty($ptk->nip) && $existing->nip !== $ptk->nip) {
                        $syncPayload['nip'] = $ptk->nip;
                    }
                    if (! empty($ptk->dapodik_id) && $existing->dapodik_id !== $ptk->dapodik_id) {
                        $syncPayload['dapodik_id'] = $ptk->dapodik_id;
                    }
                    if (! empty($syncPayload)) {
                        $existing->update($syncPayload);
                    }
                    if ($existing->peran === 'guru') {
                        PengaturanGuru::firstOrCreate(
                            ['guru_id' => $existing->id],
                            [
                                'nama_guru' => $existing->nama_lengkap,
                                'kkm' => $kkmDefault,
                                'semester' => $namaSemesterDefault,
                                'tahun_pelajaran' => $tahunAjaranDefault,
                                'pembaruan' => now(),
                            ]
                        );
                    }

                    continue;
                }

                if (count($generatedCredentials) >= $batasMassal) {
                    $terbatasBatas = true;
                    break;
                }

                $username = $this->resolveUniqueUsername($rawCandidate, $allUsernames);
                $email = $this->resolveUniqueEmail($username, $allEmails);
                $plainPassword = InitialPassword::generate();

                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'nama_lengkap' => $namaLengkap,
                    'nama_pengguna' => $username,
                    'email' => $email,
                    'kata_sandi' => Hash::make($plainPassword),
                    'peran' => $peran,
                    'aktif' => true,
                    'force_password_change' => true,
                    'sandi_awal_diterbitkan_at' => now(),
                    'dapodik_id' => $ptk->dapodik_id,
                    'nip' => $ptk->nip,
                ]);

                $generatedCredentials[] = [
                    'nama' => $namaLengkap,
                    'kelas' => $ptk->jabatan ?? '-',
                    'username' => $username,
                    'password' => $plainPassword,
                ];

                if ($peran === 'guru') {
                    PengaturanGuru::firstOrCreate(
                        ['guru_id' => $user->id],
                        [
                            'nama_guru' => $user->nama_lengkap,
                            'kkm' => $kkmDefault,
                            'semester' => $namaSemesterDefault,
                            'tahun_pelajaran' => $tahunAjaranDefault,
                            'pembaruan' => now(),
                        ]
                    );
                }

                $claimedPtksUserIds[$user->id] = true;
                $allUsernames[strtolower($username)] = $user;
                $allEmails[strtolower($email)] = $user;
                $schoolByUsername[strtolower($username)] = $user;
                if (! empty($ptk->dapodik_id)) {
                    $schoolByDapodik[(string) $ptk->dapodik_id] = $user;
                }
                if (! empty($ptk->nip)) {
                    $schoolByNip[strtolower(trim((string) $ptk->nip))] = $user;
                }
                $guruDibuat++;
            }

            // 2. Generate akun Siswa dari tabel Siswa
            $siswaRows = Siswa::where(function ($q) {
                $q->where('aktif', true)->orWhereNull('aktif');
            })->orderBy('nama_peserta_didik')->get();

            foreach ($siswaRows as $s) {
                $rawNisn = trim((string) $s->nisn);
                $rawNis = trim((string) $s->nis);
                $rawCandidate = $rawNisn !== '' ? $rawNisn : ($rawNis !== '' ? $rawNis : 'siswa'.$s->id);

                $existing = $schoolBySiswaId[(int) $s->id] ?? null;
                if (! $existing && ! empty($s->dapodik_id) && isset($schoolByDapodik[(string) $s->dapodik_id])) {
                    $existing = $schoolByDapodik[(string) $s->dapodik_id];
                }
                if (! $existing && $rawNisn !== '' && isset($schoolByUsername[strtolower($rawNisn)])) {
                    $existing = $schoolByUsername[strtolower($rawNisn)];
                }
                if (! $existing && $rawNis !== '' && isset($schoolByUsername[strtolower($rawNis)])) {
                    $existing = $schoolByUsername[strtolower($rawNis)];
                }
                if (! $existing && $rawNis !== '' && isset($schoolByUsername[strtolower('siswa'.$rawNis)])) {
                    $existing = $schoolByUsername[strtolower('siswa'.$rawNis)];
                }

                if ($existing) {
                    $terhubung = array_values(array_unique(array_merge(
                        array_map('intval', (array) ($existing->terhubung_dengan ?? [])),
                        [(int) $s->id]
                    )));
                    $perluUpdate = false;
                    $updatePayload = [];

                    if ($existing->trashed()) {
                        $existing->restore();
                        $updatePayload['aktif'] = true;
                        $updatePayload['archived_at'] = null;
                        $perluUpdate = true;
                        $dipulihkan++;
                        $siswaDibuat++;
                    }
                    if (! in_array((int) $s->id, array_map('intval', (array) ($existing->terhubung_dengan ?? [])), true)) {
                        $updatePayload['terhubung_dengan'] = $terhubung;
                        $perluUpdate = true;
                        $ditautkan++;
                    }
                    if (empty($existing->kelas_mata_pelajaran) && ! empty($s->kelas)) {
                        $updatePayload['kelas_mata_pelajaran'] = $s->kelas;
                        $perluUpdate = true;
                    }
                    if ($perluUpdate) {
                        $existing->update($updatePayload);
                    }
                    $schoolBySiswaId[(int) $s->id] = $existing;

                    continue;
                }

                if (count($generatedCredentials) >= $batasMassal) {
                    $terbatasBatas = true;
                    break;
                }

                $username = $this->resolveUniqueUsername($rawCandidate, $allUsernames);
                $email = $this->resolveUniqueEmail($username, $allEmails);
                $plainPassword = InitialPassword::generate();

                $newSiswaUser = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'nama_lengkap' => $s->nama_peserta_didik,
                    'nama_pengguna' => $username,
                    'email' => $email,
                    'kata_sandi' => Hash::make($plainPassword),
                    'peran' => 'siswa',
                    'terhubung_dengan' => [(int) $s->id],
                    'kelas_mata_pelajaran' => $s->kelas,
                    'aktif' => true,
                    'force_password_change' => InitialPassword::shouldForceChangeSiswa(),
                    'sandi_awal_diterbitkan_at' => now(),
                    'dapodik_id' => $s->dapodik_id,
                ]);

                $generatedCredentials[] = [
                    'nama' => $s->nama_peserta_didik,
                    'kelas' => $s->kelas ?? '-',
                    'username' => $username,
                    'password' => $plainPassword,
                ];

                $allUsernames[strtolower($username)] = $newSiswaUser;
                $allEmails[strtolower($email)] = $newSiswaUser;
                $schoolByUsername[strtolower($username)] = $newSiswaUser;
                $schoolBySiswaId[(int) $s->id] = $newSiswaUser;
                if (! empty($s->dapodik_id)) {
                    $schoolByDapodik[(string) $s->dapodik_id] = $newSiswaUser;
                }
                $siswaDibuat++;
            }
        });

        AktivitasService::tambah(
            'Generate Semua Pengguna',
            "Generate otomatis selesai: {$guruDibuat} akun Guru/PTK dan {$siswaDibuat} akun Siswa dibuat (dipulihkan: {$dipulihkan}, ditautkan: {$ditautkan}).",
            'users'
        );

        $detailTambahan = [];
        if ($dipulihkan > 0) {
            $detailTambahan[] = "{$dipulihkan} akun dipulihkan";
        }
        if ($ditautkan > 0) {
            $detailTambahan[] = "{$ditautkan} akun siswa ditautkan ulang";
        }
        $infoExtra = $detailTambahan ? ' ('.implode(', ', $detailTambahan).')' : '';

        if ($guruDibuat === 0 && $siswaDibuat === 0 && $ditautkan === 0) {
            $pesan = 'Semua Guru (PTK) dan Siswa aktif di database sudah memiliki akun pengguna.';
        } else {
            $pesan = "Generate Semua Pengguna berhasil: {$guruDibuat} akun Guru/PTK dan {$siswaDibuat} akun Siswa baru dibuat{$infoExtra}.";
            if ($terbatasBatas) {
                $pesan .= " Dibatasi {$batasMassal} akun per permintaan agar tidak timeout — klik lagi untuk melanjutkan sisa akun.";
            }
        }

        $eksporUrl = $generatedCredentials !== []
            ? $this->exportService->store((int) $actorUserId, $tenantId, $generatedCredentials)
            : null;

        return [
            'guru_dibuat' => $guruDibuat,
            'siswa_dibuat' => $siswaDibuat,
            'dipulihkan' => $dipulihkan,
            'ditautkan' => $ditautkan,
            'ekspor_url' => $eksporUrl,
            'peringatan_sandi' => $eksporUrl ? 'Simpan dan bagikan dengan aman; password tidak dapat dilihat lagi.' : null,
            'message' => $pesan,
        ];
    }
}
