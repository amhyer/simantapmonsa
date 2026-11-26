<?php

namespace App\Services\Admin;

use App\Models\NilaiErapot;
use App\Models\PengaturanGuru;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use App\Services\AktivitasService;
use App\Support\InitialPassword;
use App\Support\SessionSecurity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public const ROLE_LABELS = [
        'guru' => 'Guru',
        'siswa' => 'Siswa',
        'ortu' => 'Orang Tua',
        'kepsek' => 'Kepala Sekolah',
        'tendik' => 'Tenaga Kependidikan',
        'pengawas' => 'Pengawas Sekolah',
    ];

    public function accountsQuery(): Builder
    {
        $tenantId = TenantContext::id();
        abort_if(
            $tenantId === null && (config('simantap.multi_tenant_enabled', false) || Tenant::hasAnyTenant()),
            403,
            'Akun admin belum terhubung ke sekolah.'
        );

        return User::query()->where('tenant_id', $tenantId)->where('peran', '!=', 'super_admin');
    }

    public function ensureAccountAccessible(User $user): void
    {
        abort_unless($this->accountsQuery()->whereKey($user->id)->exists(), 404);
    }

    /**
     * @param  array<int, int|string>  $studentIds
     */
    public function validateStudentLinks(array $studentIds): void
    {
        if ($studentIds === []) {
            return;
        }

        $ids = array_unique($studentIds);
        if (Siswa::whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'terhubung_dengan' => 'Siswa yang dipilih tidak tersedia untuk sekolah ini.',
            ]);
        }
    }

    public function normalizeLegacyEmails(): void
    {
        $this->accountsQuery()
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', 'like', '%@erapor.id');
            })
            ->get()
            ->each(function (User $u) {
                $candidate = $u->email
                    ? str_replace('@erapor.id', '@simantap.sch.id', $u->email)
                    : (strtolower($u->nama_pengguna).'@simantap.sch.id');
                if (! User::withoutGlobalScopes()->withTrashed()->where('email', $candidate)->where('id', '!=', $u->id)->exists()) {
                    $u->email = $candidate;
                    $u->saveQuietly();
                }
            });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, ?string $rawIsWaliKelas): User
    {
        $terhubungDengan = in_array($validated['peran'], ['ortu', 'siswa'], true)
            ? array_values(array_map('intval', $validated['terhubung_dengan'] ?? []))
            : [];
        $this->validateStudentLinks($terhubungDengan);

        $isWaliKelas = null;
        if ($validated['peran'] === 'guru' && $rawIsWaliKelas !== null && $rawIsWaliKelas !== '') {
            $isWaliKelas = $rawIsWaliKelas === '1';
        }

        $user = User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => $validated['nama_lengkap'],
            'nama_pengguna' => $validated['nama_pengguna'],
            'email' => $validated['email'] ?? null,
            'kata_sandi' => Hash::make($validated['kata_sandi']),
            'peran' => $validated['peran'],
            'terhubung_dengan' => $terhubungDengan,
            'kelas_mata_pelajaran' => $validated['kelas_mata_pelajaran'] ?? null,
            'is_wali_kelas' => $isWaliKelas,
            'aktif' => $validated['aktif'] ?? true,
            'force_password_change' => true,
        ]);

        if ($validated['peran'] === 'guru') {
            PengaturanGuru::create([
                'guru_id' => $user->id,
                'nama_guru' => $user->nama_lengkap,
                'kelas' => $validated['kelas_mata_pelajaran'] ?? null,
                'kkm' => getKKM(),
                'semester' => 'Ganjil',
                'tahun_pelajaran' => date('Y').'/'.(date('Y') + 1),
                'pembaruan' => now(),
            ]);
        }

        AktivitasService::tambah(
            'Buat Akun: '.$validated['nama_lengkap'],
            'Akun '.$validated['peran'].' berhasil dibuat',
            'users',
            $user->id,
            ['nama_lengkap' => $validated['nama_lengkap'], 'peran' => $validated['peran']]
        );

        return $user;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function update(User $user, array $validated, bool $hasIsWaliKelas, ?string $rawIsWaliKelas, ?int $actorId): User
    {
        $terhubungDengan = in_array($validated['peran'], ['ortu', 'siswa'], true)
            ? array_values(array_map('intval', $validated['terhubung_dengan'] ?? []))
            : [];
        $this->validateStudentLinks($terhubungDengan);

        $data = [
            'nama_lengkap' => $validated['nama_lengkap'],
            'nama_pengguna' => $validated['nama_pengguna'],
            'email' => $validated['email'] ?? null,
            'peran' => $validated['peran'],
            'terhubung_dengan' => $terhubungDengan,
            'kelas_mata_pelajaran' => $validated['kelas_mata_pelajaran'] ?? null,
            'aktif' => $validated['aktif'] ?? true,
        ];

        if ($validated['peran'] === 'guru') {
            if ($hasIsWaliKelas) {
                $data['is_wali_kelas'] = ($rawIsWaliKelas === '' || $rawIsWaliKelas === null)
                    ? null
                    : ($rawIsWaliKelas === '1');
            }
        } else {
            $data['is_wali_kelas'] = null;
        }

        $passwordChanged = ! empty($validated['kata_sandi']);
        $deactivated = ! (bool) $data['aktif'];

        if ($passwordChanged) {
            $data['kata_sandi'] = Hash::make($validated['kata_sandi']);

            if ($user->id !== $actorId) {
                $data['force_password_change'] = true;
            }
        }

        $user->update($data);

        if ($passwordChanged || $deactivated) {
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
            $exceptSession = ($passwordChanged && ! $deactivated && $user->id === $actorId && session()->isStarted())
                ? session()->getId()
                : null;
            SessionSecurity::invalidateUserSessions((int) $user->id, $exceptSession);
        }

        AktivitasService::ubah(
            'Ubah Akun: '.$user->nama_lengkap,
            'Data akun '.$user->peran.' diperbarui',
            'users',
            $user->id,
            null,
            ['nama_lengkap' => $user->nama_lengkap, 'peran' => $user->peran]
        );

        if (! empty($validated['kata_sandi'])) {
            AktivitasService::ubah(
                'Reset Sandi: '.$user->nama_lengkap,
                'Sandi akun '.$user->peran.' direset oleh administrator',
                'users',
                $user->id
            );
        }

        return $user;
    }

    public function delete(User $user): void
    {
        $nama = $user->nama_lengkap;
        $peran = $user->peran;
        $userId = (int) $user->id;

        DB::transaction(function () use ($user) {
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
            $this->deleteUserRelations($user);
            $user->delete();
        });

        SessionSecurity::invalidateUserSessions($userId);

        AktivitasService::hapus(
            'Hapus Akun: '.$nama,
            'Akun '.$peran.' dihapus',
            'users',
            null,
            ['nama_lengkap' => $nama, 'peran' => $peran]
        );
    }

    public function deleteAllByRole(string $peran): int
    {
        $users = $this->accountsQuery()
            ->where('peran', $peran)
            ->where('nama_pengguna', '!=', 'admin')
            ->get();
        $count = $users->count();

        if ($count === 0) {
            return 0;
        }

        $deletedIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
                $this->deleteUserRelations($user);
                $user->delete();
            }
        });

        foreach ($deletedIds as $deletedId) {
            SessionSecurity::invalidateUserSessions($deletedId);
        }

        $label = self::ROLE_LABELS[$peran] ?? $peran;
        AktivitasService::hapus(
            'Hapus Semua Akun: '.$label,
            "{$count} akun {$label} berhasil dihapus",
            'users'
        );

        return $count;
    }

    /**
     * @return array{password_baru: string, message: string}
     */
    public function resetPasswordInstan(User $user, ?int $actorId): array
    {
        $passwordBaru = InitialPassword::generate();
        $forceChange = $user->id !== $actorId
            ? ($user->peran === 'siswa' ? InitialPassword::shouldForceChangeSiswa() : true)
            : false;

        $user->forceFill([
            'kata_sandi' => Hash::make($passwordBaru),
            'force_password_change' => $forceChange,
            'sandi_awal_diterbitkan_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $exceptSession = ($user->id === $actorId && session()->isStarted())
            ? session()->getId()
            : null;
        SessionSecurity::invalidateUserSessions((int) $user->id, $exceptSession);

        AktivitasService::ubah(
            'Reset Sandi Instan: '.$user->nama_lengkap,
            'Sandi akun '.$user->peran.' direset instan oleh administrator',
            'users',
            $user->id
        );

        return [
            'password_baru' => $passwordBaru,
            'message' => "Proses Ubah Password Berhasil, Password untuk {$user->nama_lengkap} saat ini adalah :: {$passwordBaru} :: Silahkan dicatat sebelum ditutup",
        ];
    }

    /**
     * @return array{aktif: bool, message: string}
     */
    public function toggleAktif(User $user): array
    {
        $statusBaru = ! $user->aktif;
        $user->update(['aktif' => $statusBaru]);

        if (! $statusBaru) {
            $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
            SessionSecurity::invalidateUserSessions((int) $user->id);
        }

        $labelAksi = $statusBaru ? 'Aktifkan' : 'Non-aktifkan';
        AktivitasService::ubah(
            "{$labelAksi} Akun: {$user->nama_lengkap}",
            "Status akun {$user->peran} diubah menjadi ".($statusBaru ? 'Aktif' : 'Tidak Aktif'),
            'users',
            $user->id
        );

        return [
            'aktif' => $statusBaru,
            'message' => $statusBaru
                ? 'Proses Aktifkan Pengguna Berhasil !'
                : 'Proses Non-aktifkan Pengguna Berhasil !',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateDataPelengkapSiswa(Siswa $siswa, array $validated): void
    {
        $rekaman = is_array($siswa->rekaman) ? $siswa->rekaman : [];
        $pelengkap = is_array($rekaman['pelengkap'] ?? null) ? $rekaman['pelengkap'] : [];

        foreach ([
            'status_dalam_keluarga', 'asal_sekolah', 'diterima_di_kelas', 'tanggal_diterima',
            'nama_ayah', 'pekerjaan_ayah', 'nama_ibu', 'pekerjaan_ibu',
            'alamat_ortu', 'telepon_ortu', 'nama_wali', 'pekerjaan_wali',
            'alamat_wali', 'telepon_wali',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $pelengkap[$field] = $validated[$field];
            }
        }
        $rekaman['pelengkap'] = $pelengkap;

        $namaOrtuGabung = trim(implode(' / ', array_filter([
            $validated['nama_ayah'] ?? ($pelengkap['nama_ayah'] ?? null),
            $validated['nama_ibu'] ?? ($pelengkap['nama_ibu'] ?? null),
        ])));

        $siswa->update([
            'tempat_lahir' => $validated['tempat_lahir'] ?? $siswa->tempat_lahir,
            'tanggal_lahir' => $validated['tanggal_lahir'] ?? $siswa->tanggal_lahir,
            'agama' => $validated['agama'] ?? $siswa->agama,
            'alamat' => $validated['alamat'] ?? $siswa->alamat,
            'telepon' => $validated['telepon'] ?? ($validated['telepon_ortu'] ?? $siswa->telepon),
            'anak_ke' => $validated['anak_ke'] ?? $siswa->anak_ke,
            'nama_orang_tua' => $namaOrtuGabung !== '' ? $namaOrtuGabung : $siswa->nama_orang_tua,
            'rekaman' => $rekaman,
        ]);

        AktivitasService::ubah(
            'Update Data Pelengkap Siswa: '.$siswa->nama_peserta_didik,
            'Biodata & data pelengkap rapor siswa diperbarui oleh administrator',
            'siswa',
            $siswa->id
        );
    }

    private function deleteUserRelations(User $user): void
    {
        if ($user->peran === 'guru') {
            $user->pengaturanGuru()->delete();
            $user->ringkasanGuru()->delete();
            // Lepaskan relasi wali kelas tanpa menghapus data siswa maupun histori akademik (nilai/kehadiran/rapor) satu kelas.
            Siswa::where('guru_id', $user->id)->update([
                'guru_id' => null,
                'nama_guru' => null,
            ]);
            $user->materi()->delete();
            $user->kuis()->each(function ($kuis) {
                $kuis->hasilKuis()->delete();
                $kuis->delete();
            });
        }

        // Menghapus akun login siswa (User) TIDAK boleh menghapus data pokok Siswa
        // maupun arsip nilai, kehadiran, ujian CBT, dan rapor siswa tersebut.
    }
}
