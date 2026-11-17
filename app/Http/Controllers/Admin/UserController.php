<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Siswa;
use App\Models\NilaiErapot;
use App\Services\AktivitasService;
use App\Jobs\ProsesAkunMassalJob;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    private function accounts(): Builder
    {
        $tenantId = TenantContext::id();
        abort_if($tenantId === null && (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()), 403, 'Akun admin belum terhubung ke sekolah.');

        return User::query()->where('tenant_id', $tenantId)->where('peran', '!=', 'super_admin');
    }

    private function ensureAccountAccessible(User $user): void
    {
        abort_unless($this->accounts()->whereKey($user->id)->exists(), 404);
    }

    private function validateStudentLinks(array $studentIds): void
    {
        if ($studentIds === []) {
            return;
        }
        $ids = array_unique($studentIds);
        if (Siswa::whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['terhubung_dengan' => 'Siswa yang dipilih tidak tersedia untuk sekolah ini.']);
        }
    }

    public function index()
    {
        $this->accounts()
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', 'like', '%@erapor.id');
            })
            ->get()
            ->each(function (User $u) {
                $candidate = $u->email
                    ? str_replace('@erapor.id', '@simantap.sch.id', $u->email)
                    : (strtolower($u->nama_pengguna) . '@simantap.sch.id');
                if (!User::withoutGlobalScopes()->withTrashed()->where('email', $candidate)->where('id', '!=', $u->id)->exists()) {
                    $u->email = $candidate;
                    $u->saveQuietly();
                }
            });

        $users = $this->accounts()->select('id', 'uuid', 'nama_lengkap', 'nama_pengguna', 'email', 'peran', 'terhubung_dengan', 'kelas_mata_pelajaran', 'aktif', 'terakhir_masuk', 'dapodik_id', 'created_at')
            ->orderBy('peran')
            ->orderBy('nama_lengkap')
            ->get();
        $siswa = Siswa::select('id', 'nama_peserta_didik', 'nis', 'nisn', 'kelas', 'guru_id')
            ->with('guru:id,nama_lengkap')
            ->orderBy('nama_peserta_didik')
            ->get();
        $ptkList = \App\Models\Ptk::select('id', 'nama', 'nip', 'nuptk', 'gelar_depan', 'gelar_belakang')
            ->orderBy('nama')
            ->get();

        return view('admin.users.index', compact('users', 'siswa', 'ptkList'));
    }

    public function create()
    {
        $this->accounts();
        $siswa = Siswa::all();
        return view('admin.users.create', compact('siswa'));
    }

    public function store(Request $request)
    {
        $this->accounts();
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => 'required|string|max:100|unique:users',
            'email' => 'nullable|email|max:255|unique:users,email',
            'kata_sandi' => 'required|string|min:8',
            'peran' => 'required|in:admin,guru,siswa,ortu,kepsek,tendik',
            'terhubung_dengan' => 'nullable|array',
            'terhubung_dengan.*' => 'integer|distinct',
            'kelas_mata_pelajaran' => 'nullable|string|max:255',
            'is_wali_kelas' => 'nullable|in:0,1',
            'aktif' => 'boolean',
        ]);

        $this->validateStudentLinks($validated['terhubung_dengan'] ?? []);

        $isWaliKelas = null;
        if ($validated['peran'] === 'guru' && $request->filled('is_wali_kelas')) {
            $isWaliKelas = $request->input('is_wali_kelas') === '1';
        }

        $user = User::create([
            'uuid' => Str::uuid(),
            'nama_lengkap' => $validated['nama_lengkap'],
            'nama_pengguna' => $validated['nama_pengguna'],
            'email' => $validated['email'] ?? null,
            'kata_sandi' => Hash::make($validated['kata_sandi']),
            'peran' => $validated['peran'],
            'terhubung_dengan' => $validated['terhubung_dengan'] ?? [],
            'kelas_mata_pelajaran' => $validated['kelas_mata_pelajaran'] ?? null,
            'is_wali_kelas' => $isWaliKelas,
            'aktif' => $validated['aktif'] ?? true,
            // PERBAIKAN KEAMANAN (kesiapan launching): sandi diketik oleh admin,
            // bukan oleh pemilik akun. Tanpa penanda ini sandi sementara berlaku
            // selamanya. Middleware `force.password.change` akan mengarahkan
            // pemilik akun ke halaman ganti sandi pada login berikutnya.
            'force_password_change' => true,
        ]);

        if ($validated['peran'] === 'guru') {
            \App\Models\PengaturanGuru::create([
                'guru_id' => $user->id,
                'nama_guru' => $user->nama_lengkap,
                'kelas' => $validated['kelas_mata_pelajaran'] ?? null,
                'kkm' => getKKM(),
                'semester' => 'Ganjil',
                'tahun_pelajaran' => date('Y') . '/' . (date('Y') + 1),
                'pembaruan' => now(),
            ]);
        }

        AktivitasService::tambah(
            'Buat Akun: ' . $validated['nama_lengkap'],
            'Akun ' . $validated['peran'] . ' berhasil dibuat',
            'users',
            $user->id,
            ['nama_lengkap' => $validated['nama_lengkap'], 'peran' => $validated['peran']]
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $user)
    {
        $this->ensureAccountAccessible($user);
        $siswa = Siswa::all();
        return view('admin.users.edit', compact('user', 'siswa'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAccountAccessible($user);
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => 'required|string|max:100|unique:users,nama_pengguna,' . $user->id,
            'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'kata_sandi' => 'nullable|string|min:8',
            'peran' => 'required|in:admin,guru,siswa,ortu,kepsek,tendik',
            'terhubung_dengan' => 'nullable|array',
            'terhubung_dengan.*' => 'integer|distinct',
            'kelas_mata_pelajaran' => 'nullable|string|max:255',
            'is_wali_kelas' => 'nullable|in:0,1',
            'aktif' => 'boolean',
        ]);

        $this->validateStudentLinks($validated['terhubung_dengan'] ?? []);

        $data = [
            'nama_lengkap' => $validated['nama_lengkap'],
            'nama_pengguna' => $validated['nama_pengguna'],
            'email' => $validated['email'] ?? null,
            'peran' => $validated['peran'],
            'terhubung_dengan' => $validated['terhubung_dengan'] ?? [],
            'kelas_mata_pelajaran' => $validated['kelas_mata_pelajaran'] ?? null,
            'aktif' => $validated['aktif'] ?? true,
        ];

        if ($validated['peran'] === 'guru') {
            if ($request->has('is_wali_kelas')) {
                $val = $request->input('is_wali_kelas');
                $data['is_wali_kelas'] = ($val === '' || $val === null) ? null : ($val === '1');
            }
        } else {
            $data['is_wali_kelas'] = null;
        }

        if (!empty($validated['kata_sandi'])) {
            $data['kata_sandi'] = Hash::make($validated['kata_sandi']);

            // PERBAIKAN KEAMANAN (kesiapan launching): sebelumnya sandi yang
            // direset admin berlaku selamanya karena `force_password_change`
            // tidak pernah ikut di-set. Sekarang pemilik akun dipaksa mengganti
            // sandi sementara itu pada login berikutnya — kecuali admin
            // mengubah sandinya sendiri (ia sudah tahu sandi barunya).
            if ($user->id !== $request->user()?->id) {
                $data['force_password_change'] = true;
            }
        }

        $user->update($data);

        AktivitasService::ubah(
            'Ubah Akun: ' . $user->nama_lengkap,
            'Data akun ' . $user->peran . ' diperbarui',
            'users',
            $user->id,
            null,
            ['nama_lengkap' => $user->nama_lengkap, 'peran' => $user->peran]
        );

        if (!empty($validated['kata_sandi'])) {
            // Dicatat terpisah dan TANPA nilai sandi, agar jejak audit menunjukkan
            // siapa mereset sandi siapa tanpa membocorkan rahasia.
            AktivitasService::ubah(
                'Reset Sandi: ' . $user->nama_lengkap,
                'Sandi akun ' . $user->peran . ' direset oleh administrator',
                'users',
                $user->id
            );
        }

        return redirect()->route('admin.users.index')
            ->with('success', !empty($validated['kata_sandi']) && $user->force_password_change
                ? 'Akun berhasil diperbarui. Pengguna wajib mengganti sandi saat login berikutnya.'
                : 'Akun berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $this->ensureAccountAccessible($user);
        if ($user->nama_pengguna === 'admin') {
            return redirect()->back()->with('error', 'Akun admin utama tidak dapat dihapus.');
        }

        $nama = $user->nama_lengkap;
        $peran = $user->peran;

        DB::transaction(function () use ($user) {
            $this->deleteUserRelations($user);
            $user->delete();
        });

        AktivitasService::hapus(
            'Hapus Akun: ' . $nama,
            'Akun ' . $peran . ' dihapus',
            'users',
            null,
            ['nama_lengkap' => $nama, 'peran' => $peran]
        );

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun berhasil dihapus.');
    }

    public function destroyAll(Request $request, string $peran)
    {
        $accounts = $this->accounts();
        if (TenantContext::check()) {
            return response()->json(['success' => false, 'message' => 'Hapus massal dinonaktifkan untuk multi-sekolah sampai seluruh relasi data terisolasi.'], 503);
        }
        $allowed = ['guru', 'siswa', 'ortu', 'tendik'];
        if (!in_array($peran, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Peran tidak valid.'], 400);
        }

        // Konfirmasi bertingkat: frasa diketik ulang di peramban sebelum
        // penghapusan massal dijalankan (mengikuti pola `admin.backup.wipe`).
        $frasaKonfirmasi = 'HAPUS ' . strtoupper($peran);
        if (strtoupper(trim((string) $request->input('konfirmasi'))) !== $frasaKonfirmasi) {
            return response()->json([
                'success' => false,
                'message' => 'Teks konfirmasi tidak cocok. Ketik persis: ' . $frasaKonfirmasi,
            ], 422);
        }

        $users = $accounts->where('peran', $peran)->where('nama_pengguna', '!=', 'admin')->get();
        $count = $users->count();

        if ($count === 0) {
            return response()->json(['success' => true, 'message' => 'Tidak ada akun ' . $peran . ' yang perlu dihapus.']);
        }

        $labels = ['guru' => 'Guru', 'siswa' => 'Siswa', 'ortu' => 'Orang Tua', 'kepsek' => 'Kepala Sekolah', 'tendik' => 'Tenaga Kependidikan'];

        DB::transaction(function () use ($users) {
            foreach ($users as $user) {
                $this->deleteUserRelations($user);
                $user->delete();
            }
        });

        AktivitasService::hapus(
            'Hapus Semua Akun: ' . ($labels[$peran] ?? $peran),
            "{$count} akun " . ($labels[$peran] ?? $peran) . " berhasil dihapus",
            'users'
        );

        return response()->json([
            'success' => true,
            'message' => "{$count} akun " . ($labels[$peran] ?? $peran) . " berhasil dihapus.",
        ]);
    }

    private function deleteUserRelations(User $user): void
    {
        if ($user->peran === 'guru') {
            $user->pengaturanGuru()->delete();
            $user->ringkasanGuru()->delete();
            $user->siswa()->each(function ($siswa) {
                $siswa->nilai()->delete();
                $siswa->kehadiran()->delete();
                $siswa->hasilKuis()->delete();
                $siswa->catatan()->delete();
                $siswa->dimensi()->delete();
                $siswa->kebiasaan()->delete();
                $siswa->nilaiErapot()->delete();
                $siswa->delete();
            });
            $user->materi()->delete();
            $user->kuis()->each(function ($kuis) {
                $kuis->hasilKuis()->delete();
                $kuis->delete();
            });
            NilaiErapot::where('guru_id', $user->id)->delete();
        }

        if ($user->peran === 'siswa') {
            $siswa = Siswa::where('nis', $user->nama_pengguna)->first();
            if ($siswa) {
                $siswa->nilai()->delete();
                $siswa->kehadiran()->delete();
                $siswa->hasilKuis()->delete();
                $siswa->catatan()->delete();
                $siswa->dimensi()->delete();
                $siswa->kebiasaan()->delete();
                $siswa->nilaiErapot()->delete();
                $siswa->delete();
            }
        }
    }

    public function akunMassal()
    {
        $this->accounts();
        $siswa = Siswa::with('guru')->get();
        $users = $this->accounts()->get();
        return view('admin.users.massal', compact('siswa', 'users'));
    }

    public function prosesAkunMassal(Request $request)
    {
        $this->accounts();
        if (TenantContext::check()) {
            return response()->json(['success' => false, 'message' => 'Proses akun massal belum tersedia untuk multi-sekolah.'], 503);
        }
        $request->validate([
            'siswa_ids' => 'required|array',
            'siswa_ids.*' => 'exists:siswa,id',
            'buat_ortu' => 'boolean',
            'buat_siswa' => 'boolean',
        ]);

        // Dispatch job async
        ProsesAkunMassalJob::dispatch($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Proses akun massal dimulai secara asynchronous. Cek status dalam waktu singkat.',
        ]);
    }

    public function siswaIndex()
    {
        $users = $this->accounts()->get();
        $siswa = Siswa::with('guru')->orderBy('kelas')->orderBy('nama_peserta_didik')->get();
        return view('admin.users.siswa', compact('users', 'siswa'));
    }

    /**
     * Update Data Siswa & Data Pelengkap Rapor (setara `POST /update_datasiswa` di e-Rapor SD `/data_siswa`).
     */
    public function updateDataSiswa(Request $request, Siswa $siswa)
    {
        $this->accounts();
        $validated = $request->validate([
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:500',
            'telepon' => 'nullable|string|max:50',
            'anak_ke' => 'nullable|integer|min:1|max:30',
            'status_dalam_keluarga' => 'nullable|string|max:100',
            'asal_sekolah' => 'nullable|string|max:200',
            'diterima_di_kelas' => 'nullable|string|max:50',
            'tanggal_diterima' => 'nullable|string|max:50',
            'nama_ayah' => 'nullable|string|max:150',
            'pekerjaan_ayah' => 'nullable|string|max:150',
            'nama_ibu' => 'nullable|string|max:150',
            'pekerjaan_ibu' => 'nullable|string|max:150',
            'alamat_ortu' => 'nullable|string|max:500',
            'telepon_ortu' => 'nullable|string|max:50',
            'nama_wali' => 'nullable|string|max:150',
            'pekerjaan_wali' => 'nullable|string|max:150',
            'alamat_wali' => 'nullable|string|max:500',
            'telepon_wali' => 'nullable|string|max:50',
        ]);

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
            'Update Data Pelengkap Siswa: ' . $siswa->nama_peserta_didik,
            'Biodata & data pelengkap rapor siswa diperbarui oleh administrator',
            'siswa',
            $siswa->id
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Data pelengkap siswa {$siswa->nama_peserta_didik} berhasil disimpan.",
            ]);
        }

        return back()->with('success', "Data pelengkap siswa {$siswa->nama_peserta_didik} berhasil disimpan.");
    }

    public function impersonateGuru(User $user)
    {
        $admin = auth()->user();
        abort_unless($admin && in_array($admin->peran, ['admin', 'super_admin'], true), 403);
        abort_unless($user->peran === 'guru' && $user->aktif, 403, 'Hanya akun Guru aktif yang dapat diakses.');

        if (TenantContext::check() && (int) $user->tenant_id !== (int) TenantContext::id()) {
            abort(403, 'Lintas sekolah tidak diizinkan.');
        }

        session([
            'admin_impersonator_id' => $admin->id,
        ]);

        auth()->login($user);

        return redirect()->route('guru.dashboard')
            ->with('success', "Berhasil masuk sebagai Guru '{$user->nama_lengkap}'.");
    }

    public function leaveGuruImpersonate()
    {
        $adminId = session('admin_impersonator_id');
        if (! $adminId) {
            return redirect()->route('home');
        }

        $admin = User::withoutGlobalScopes()->find($adminId);
        session()->forget('admin_impersonator_id');

        if (! $admin || ! in_array($admin->peran, ['admin', 'super_admin'], true)) {
            auth()->logout();
            return redirect()->route('login');
        }

        auth()->login($admin);

        return redirect()->route('admin.users.index')
            ->with('success', 'Kembali ke sesi Administrator.');
    }

    /**
     * Reset Password instan ala e-Rapor SD (`/data_pengguna` -> `reset_passpengguna`).
     * Menghasilkan password acak baru dan mengembalikannya ke layar admin untuk dicatat.
     */
    public function resetPasswordInstan(Request $request, User $user)
    {
        $this->ensureAccountAccessible($user);

        // Gunakan 8 karakter alfanumerik agar tetap memenuhi kebijakan keamanan SIMANTAP
        $passwordBaru = Str::random(8);

        $user->update([
            'kata_sandi' => Hash::make($passwordBaru),
            'force_password_change' => $user->id !== $request->user()?->id,
        ]);

        AktivitasService::ubah(
            'Reset Sandi Instan: ' . $user->nama_lengkap,
            'Sandi akun ' . $user->peran . ' direset instan oleh administrator',
            'users',
            $user->id
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'nama' => $user->nama_lengkap,
                'username' => $user->nama_pengguna,
                'password_baru' => $passwordBaru,
                'message' => "Proses Ubah Password Berhasil, Password untuk {$user->nama_lengkap} saat ini adalah :: {$passwordBaru} :: Silahkan dicatat sebelum ditutup",
            ]);
        }

        return back()->with('success', "Proses Ubah Password Berhasil, Password untuk {$user->nama_lengkap} saat ini adalah :: {$passwordBaru} :: Silahkan dicatat sebelum ditutup");
    }

    /**
     * Toggle Aktif / Non-Aktifkan (Blokir) pengguna ala e-Rapor SD (`/data_pengguna` -> `reset_pengguna`).
     */
    public function toggleAktif(Request $request, User $user)
    {
        $this->ensureAccountAccessible($user);

        if ($user->nama_pengguna === 'admin' || $user->id === $request->user()?->id) {
            $msg = 'Akun administrator utama / sedang login tidak dapat dinonaktifkan.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        $statusBaru = ! $user->aktif;
        $user->update(['aktif' => $statusBaru]);

        $labelAksi = $statusBaru ? 'Aktifkan' : 'Non-aktifkan';
        AktivitasService::ubah(
            "{$labelAksi} Akun: {$user->nama_lengkap}",
            "Status akun {$user->peran} diubah menjadi " . ($statusBaru ? 'Aktif' : 'Tidak Aktif'),
            'users',
            $user->id
        );

        $pesan = $statusBaru
            ? 'Proses Aktifkan Pengguna Berhasil !'
            : 'Proses Non-aktifkan Pengguna Berhasil !';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'aktif' => $statusBaru,
                'message' => $pesan,
            ]);
        }

        return back()->with('success', $pesan);
    }

    /**
     * Generate Semua Pengguna otomatis dari data Guru (PTK) dan Siswa di database
     * sesuai alur `Generate Semua Pengguna` di e-Rapor SD (`/data_pengguna`).
     */
    public function generateSemuaPengguna(Request $request)
    {
        $this->accounts();
        $tenantId = TenantContext::id();

        $guruDibuat = 0;
        $siswaDibuat = 0;
        $dipulihkan = 0;
        $ditautkan = 0;

        // Pre-hash sekali di luar loop agar pembuatan ratusan akun selesai < 100ms tanpa timeout
        // Selaras dengan DapodikSyncService & DataReferensiController (guru123 / siswa123)
        $defaultGuruHash = Hash::make('guru123');
        $defaultSiswaHash = Hash::make('siswa123');
        $semesterAktif = \App\Models\Semester::orderByDesc('semester_id')->first()
            ?? \App\Models\Semester::orderByDesc('id')->first();
        $namaSemesterDefault = $semesterAktif?->nama_semester ? ucfirst($semesterAktif->nama_semester) : 'Ganjil';
        $tahunAjaranDefault = $semesterAktif?->tahun_ajaran ?? (date('Y') . '/' . (date('Y') + 1));
        $kkmDefault = function_exists('getKKM') ? getKKM() : 75;

        DB::transaction(function () use (
            $tenantId,
            $defaultGuruHash,
            $defaultSiswaHash,
            $namaSemesterDefault,
            $tahunAjaranDefault,
            $kkmDefault,
            &$guruDibuat,
            &$siswaDibuat,
            &$dipulihkan,
            &$ditautkan
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
                    if (!empty($u->dapodik_id)) {
                        $schoolByDapodik[(string) $u->dapodik_id] = $u;
                    }
                    if (!empty($u->nip)) {
                        $schoolByNip[strtolower(trim((string) $u->nip))] = $u;
                    }
                    if ($u->peran === 'siswa' && is_array($u->terhubung_dengan)) {
                        foreach ($u->terhubung_dengan as $sid) {
                            $schoolBySiswaId[(int) $sid] = $u;
                        }
                    }
                }
            }

            $resolveUniqueUsername = function (string $base) use (&$allUsernames): string {
                $candidate = $base;
                $counter = 1;
                while (isset($allUsernames[strtolower($candidate)])) {
                    $candidate = $base . '.' . $counter;
                    $counter++;
                }
                return $candidate;
            };

            $resolveUniqueEmail = function (string $username) use (&$allEmails): string {
                $baseEmail = strtolower($username) . '@simantap.sch.id';
                $candidate = $baseEmail;
                $counter = 1;
                while (isset($allEmails[strtolower($candidate)])) {
                    $candidate = strtolower($username) . '.' . $counter . '@simantap.sch.id';
                    $counter++;
                }
                return $candidate;
            };

            // 1. Generate akun Guru / Kepsek / Tendik dari tabel PTK
            $claimedPtksUserIds = [];
            $ptkRows = \App\Models\Ptk::orderBy('id')->get();
            foreach ($ptkRows as $ptk) {
                $rawCandidate = trim((string) ($ptk->nip ?: ($ptk->nuptk ?: Str::slug($ptk->nama, ''))));
                if ($rawCandidate === '') {
                    $rawCandidate = 'guru' . $ptk->id;
                }

                $existing = null;
                if (!empty($ptk->dapodik_id) && isset($schoolByDapodik[(string) $ptk->dapodik_id])) {
                    $cand = $schoolByDapodik[(string) $ptk->dapodik_id];
                    if (!isset($claimedPtksUserIds[$cand->id])) {
                        $existing = $cand;
                    }
                }
                if (!$existing && !empty($ptk->nip) && isset($schoolByNip[strtolower(trim((string) $ptk->nip))])) {
                    $cand = $schoolByNip[strtolower(trim((string) $ptk->nip))];
                    if (!isset($claimedPtksUserIds[$cand->id])) {
                        $existing = $cand;
                    }
                }
                if (!$existing) {
                    // Cari berdasarkan kandidat username (termasuk sufiks .1, .2 untuk nama kembar tanpa NIP)
                    $probe = strtolower($rawCandidate);
                    $probeIdx = 0;
                    while (isset($schoolByUsername[$probe])) {
                        $cand = $schoolByUsername[$probe];
                        if (!isset($claimedPtksUserIds[$cand->id])) {
                            $existing = $cand;
                            break;
                        }
                        $probeIdx++;
                        $probe = strtolower($rawCandidate) . '.' . $probeIdx;
                    }
                }

                $namaLengkap = trim(($ptk->gelar_depan ? $ptk->gelar_depan . ' ' : '') . $ptk->nama . ($ptk->gelar_belakang ? ', ' . $ptk->gelar_belakang : '')) ?: $ptk->nama;

                $peran = 'guru';
                if ($ptk->isKepsek()) {
                    $peran = 'kepsek';
                } elseif (!$ptk->isGuru() && !empty($ptk->jenis_ptk)) {
                    $jenisLower = strtolower((string) $ptk->jenis_ptk);
                    if (
                        str_contains($jenisLower, 'tendik')
                        || str_contains($jenisLower, 'tenaga')
                        || $ptk->isKebersihan()
                        || $ptk->isSecurity()
                    ) {
                        $peran = 'tendik';
                    }
                }

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
                    if (!empty($ptk->nip) && $existing->nip !== $ptk->nip) {
                        $syncPayload['nip'] = $ptk->nip;
                    }
                    if (!empty($ptk->dapodik_id) && $existing->dapodik_id !== $ptk->dapodik_id) {
                        $syncPayload['dapodik_id'] = $ptk->dapodik_id;
                    }
                    if (!empty($syncPayload)) {
                        $existing->update($syncPayload);
                    }
                    if ($existing->peran === 'guru') {
                        \App\Models\PengaturanGuru::firstOrCreate(
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

                $username = $resolveUniqueUsername($rawCandidate);
                $email = $resolveUniqueEmail($username);

                $user = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'nama_lengkap' => $namaLengkap,
                    'nama_pengguna' => $username,
                    'email' => $email,
                    'kata_sandi' => $defaultGuruHash,
                    'peran' => $peran,
                    'aktif' => true,
                    'force_password_change' => false,
                    'dapodik_id' => $ptk->dapodik_id,
                    'nip' => $ptk->nip,
                ]);

                if ($peran === 'guru') {
                    \App\Models\PengaturanGuru::firstOrCreate(
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
                if (!empty($ptk->dapodik_id)) {
                    $schoolByDapodik[(string) $ptk->dapodik_id] = $user;
                }
                if (!empty($ptk->nip)) {
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
                $rawCandidate = $rawNisn !== '' ? $rawNisn : ($rawNis !== '' ? $rawNis : 'siswa' . $s->id);

                $existing = $schoolBySiswaId[(int) $s->id] ?? null;
                if (!$existing && !empty($s->dapodik_id) && isset($schoolByDapodik[(string) $s->dapodik_id])) {
                    $existing = $schoolByDapodik[(string) $s->dapodik_id];
                }
                if (!$existing && $rawNisn !== '' && isset($schoolByUsername[strtolower($rawNisn)])) {
                    $existing = $schoolByUsername[strtolower($rawNisn)];
                }
                if (!$existing && $rawNis !== '' && isset($schoolByUsername[strtolower($rawNis)])) {
                    $existing = $schoolByUsername[strtolower($rawNis)];
                }
                if (!$existing && $rawNis !== '' && isset($schoolByUsername[strtolower('siswa' . $rawNis)])) {
                    $existing = $schoolByUsername[strtolower('siswa' . $rawNis)];
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
                    if (!in_array((int) $s->id, array_map('intval', (array) ($existing->terhubung_dengan ?? [])), true)) {
                        $updatePayload['terhubung_dengan'] = $terhubung;
                        $perluUpdate = true;
                        $ditautkan++;
                    }
                    if (empty($existing->kelas_mata_pelajaran) && !empty($s->kelas)) {
                        $updatePayload['kelas_mata_pelajaran'] = $s->kelas;
                        $perluUpdate = true;
                    }
                    if ($perluUpdate) {
                        $existing->update($updatePayload);
                    }
                    $schoolBySiswaId[(int) $s->id] = $existing;
                    continue;
                }

                $username = $resolveUniqueUsername($rawCandidate);
                $email = $resolveUniqueEmail($username);

                $newSiswaUser = User::create([
                    'uuid' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'nama_lengkap' => $s->nama_peserta_didik,
                    'nama_pengguna' => $username,
                    'email' => $email,
                    'kata_sandi' => $defaultSiswaHash,
                    'peran' => 'siswa',
                    'terhubung_dengan' => [(int) $s->id],
                    'kelas_mata_pelajaran' => $s->kelas,
                    'aktif' => true,
                    'force_password_change' => false,
                    'dapodik_id' => $s->dapodik_id,
                ]);

                $allUsernames[strtolower($username)] = $newSiswaUser;
                $allEmails[strtolower($email)] = $newSiswaUser;
                $schoolByUsername[strtolower($username)] = $newSiswaUser;
                $schoolBySiswaId[(int) $s->id] = $newSiswaUser;
                if (!empty($s->dapodik_id)) {
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
        $infoExtra = $detailTambahan ? ' (' . implode(', ', $detailTambahan) . ')' : '';

        if ($guruDibuat === 0 && $siswaDibuat === 0 && $ditautkan === 0) {
            $pesan = 'Semua Guru (PTK) dan Siswa aktif di database sudah memiliki akun pengguna.';
        } else {
            $pesan = "Generate Semua Pengguna berhasil: {$guruDibuat} akun Guru/PTK dan {$siswaDibuat} akun Siswa baru dibuat{$infoExtra}.";
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'guru_dibuat' => $guruDibuat,
                'siswa_dibuat' => $siswaDibuat,
                'dipulihkan' => $dipulihkan,
                'ditautkan' => $ditautkan,
                'message' => $pesan,
            ]);
        }

        return redirect()->route('admin.users.index')->with('success', $pesan);
    }
}
