<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetMassalPasswordRequest;
use App\Jobs\ProsesAkunMassalJob;
use App\Models\Ptk;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Services\Admin\InitialPasswordExportService;
use App\Services\Admin\MassPasswordResetService;
use App\Services\Admin\UserAccountService;
use App\Services\Admin\UserBulkGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(
        private readonly UserAccountService $accounts,
        private readonly UserBulkGeneratorService $bulkGenerator,
    ) {}

    public function index()
    {
        $this->accounts->normalizeLegacyEmails();

        $users = $this->accounts->accountsQuery()
            ->select('id', 'uuid', 'nama_lengkap', 'nama_pengguna', 'email', 'peran', 'terhubung_dengan', 'kelas_mata_pelajaran', 'aktif', 'terakhir_masuk', 'dapodik_id', 'created_at')
            ->orderBy('peran')
            ->orderBy('nama_lengkap')
            ->get();
        $siswa = Siswa::select('id', 'nama_peserta_didik', 'nis', 'nisn', 'kelas', 'guru_id')
            ->with('guru:id,nama_lengkap')
            ->orderBy('nama_peserta_didik')
            ->get();
        $ptkList = Ptk::select('id', 'nama', 'nip', 'nuptk', 'gelar_depan', 'gelar_belakang')
            ->orderBy('nama')
            ->get();

        $response = response()->view('admin.users.index', compact('users', 'siswa', 'ptkList'));
        if (session()->has('ekspor_sandi_url')) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        return $response;
    }

    public function create()
    {
        $this->accounts->accountsQuery();

        // Dulu di sini ada `Siswa::all()` untuk dropdown yang ternyata tidak
        // dipakai view. Query-nya dihapus total: form Tambah Akun tidak butuh
        // daftar siswa, jadi tidak ada lagi biaya memuat seluruh siswa.
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $this->accounts->accountsQuery();
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => 'required|string|max:100|unique:users',
            'email' => 'nullable|email|max:255|unique:users,email',
            'kata_sandi' => 'required|string|min:8',
            'peran' => 'required|in:admin,guru,siswa,ortu,kepsek,tendik,pengawas',
            'terhubung_dengan' => 'nullable|array',
            'terhubung_dengan.*' => 'integer|distinct',
            'kelas_mata_pelajaran' => 'nullable|string|max:255',
            'is_wali_kelas' => 'nullable|in:0,1',
            'aktif' => 'boolean',
        ]);

        $rawIsWaliKelas = $request->filled('is_wali_kelas') ? (string) $request->input('is_wali_kelas') : null;
        $this->accounts->create($validated, $rawIsWaliKelas);

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun berhasil dibuat.');
    }

    public function edit(User $user)
    {
        $this->accounts->ensureAccountAccessible($user);

        // Daftar siswa tidak dipakai form Ubah Akun (lihat create()).
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->accounts->ensureAccountAccessible($user);
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nama_pengguna' => 'required|string|max:100|unique:users,nama_pengguna,'.$user->id,
            'email' => 'nullable|email|max:255|unique:users,email,'.$user->id,
            'kata_sandi' => 'nullable|string|min:8',
            'peran' => 'required|in:admin,guru,siswa,ortu,kepsek,tendik,pengawas',
            'terhubung_dengan' => 'nullable|array',
            'terhubung_dengan.*' => 'integer|distinct',
            'kelas_mata_pelajaran' => 'nullable|string|max:255',
            'is_wali_kelas' => 'nullable|in:0,1',
            'aktif' => 'boolean',
        ]);

        $this->accounts->update(
            $user,
            $validated,
            $request->has('is_wali_kelas'),
            $request->input('is_wali_kelas') !== null ? (string) $request->input('is_wali_kelas') : null,
            $request->user()?->id,
        );

        return redirect()->route('admin.users.index')
            ->with('success', ! empty($validated['kata_sandi']) && $user->force_password_change
                ? 'Akun berhasil diperbarui. Pengguna wajib mengganti sandi saat login berikutnya.'
                : 'Akun berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $this->accounts->ensureAccountAccessible($user);
        if ($user->nama_pengguna === 'admin') {
            return redirect()->back()->with('error', 'Akun admin utama tidak dapat dihapus.');
        }

        $this->accounts->delete($user);

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun berhasil dihapus.');
    }

    public function destroyAll(Request $request, string $peran)
    {
        $this->accounts->accountsQuery();
        if (TenantContext::check()) {
            return response()->json(['success' => false, 'message' => 'Hapus massal dinonaktifkan untuk multi-sekolah sampai seluruh relasi data terisolasi.'], 503);
        }

        $allowed = ['guru', 'siswa', 'ortu', 'tendik'];
        if (! in_array($peran, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Peran tidak valid.'], 400);
        }

        $frasaKonfirmasi = 'HAPUS '.strtoupper($peran);
        if (strtoupper(trim((string) $request->input('konfirmasi'))) !== $frasaKonfirmasi) {
            return response()->json([
                'success' => false,
                'message' => 'Teks konfirmasi tidak cocok. Ketik persis: '.$frasaKonfirmasi,
            ], 422);
        }

        $count = $this->accounts->deleteAllByRole($peran);
        if ($count === 0) {
            return response()->json(['success' => true, 'message' => 'Tidak ada akun '.$peran.' yang perlu dihapus.']);
        }

        $label = UserAccountService::ROLE_LABELS[$peran] ?? $peran;

        return response()->json([
            'success' => true,
            'message' => "{$count} akun {$label} berhasil dihapus.",
        ]);
    }

    public function akunMassal()
    {
        $this->accounts->accountsQuery();
        $siswa = Siswa::with('guru')->get();
        $users = $this->accounts->accountsQuery()->get();

        return view('admin.users.massal', compact('siswa', 'users'));
    }

    public function prosesAkunMassal(Request $request)
    {
        $this->accounts->accountsQuery();
        if (TenantContext::check()) {
            return response()->json(['success' => false, 'message' => 'Proses akun massal belum tersedia untuk multi-sekolah.'], 503);
        }

        $request->validate([
            'siswa_ids' => 'required|array',
            'siswa_ids.*' => 'exists:siswa,id',
            'buat_ortu' => 'boolean',
            'buat_siswa' => 'boolean',
        ]);

        ProsesAkunMassalJob::dispatch($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Proses akun massal dimulai secara asynchronous. Cek status dalam waktu singkat.',
        ]);
    }

    public function siswaIndex()
    {
        $users = $this->accounts->accountsQuery()->get();
        $siswa = Siswa::with('guru')->orderBy('kelas')->orderBy('nama_peserta_didik')->get();

        return view('admin.users.siswa', compact('users', 'siswa'));
    }

    /**
     * Update Data Siswa & Data Pelengkap Rapor (setara `POST /update_datasiswa` di e-Rapor SD `/data_siswa`).
     */
    public function updateDataSiswa(Request $request, Siswa $siswa)
    {
        $this->accounts->accountsQuery();
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

        $this->accounts->updateDataPelengkapSiswa($siswa, $validated);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Data pelengkap siswa {$siswa->nama_peserta_didik} berhasil disimpan.",
            ]);
        }

        return back()->with('success', "Data pelengkap siswa {$siswa->nama_peserta_didik} berhasil disimpan.");
    }

    public function impersonateGuru(Request $request, User $user)
    {
        $admin = auth()->user();
        abort_unless($admin && in_array($admin->peran, ['admin', 'super_admin'], true), 403);
        abort_unless($user->peran === 'guru' && $user->aktif && $user->archived_at === null, 403, 'Hanya akun Guru aktif yang dapat diakses.');

        if (! User::forSchool()->whereKey($user->id)->exists()) {
            abort(403, 'Lintas sekolah tidak diizinkan.');
        }

        session(['admin_impersonator_id' => $admin->id]);
        auth()->login($user);
        // Perpindahan hak akses wajib meregenerasi sesi (anti session fixation).
        $request->session()->regenerate();

        Log::info('Admin impersonate guru', [
            'admin_id' => $admin->id,
            'target_guru_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('guru.dashboard')
            ->with('success', "Berhasil masuk sebagai Guru '{$user->nama_lengkap}'.");
    }

    public function leaveGuruImpersonate(Request $request)
    {
        $adminId = session('admin_impersonator_id');
        if (! $adminId) {
            return redirect()->route('home');
        }

        $admin = User::withoutGlobalScopes()->find($adminId);
        session()->forget('admin_impersonator_id');

        if (! $admin || ! $admin->aktif || $admin->archived_at !== null || ! in_array($admin->peran, ['admin', 'super_admin'], true)) {
            auth()->logout();

            return redirect()->route('login');
        }

        auth()->login($admin);
        $request->session()->regenerate();

        Log::info('Admin keluar dari mode impersonate guru', [
            'admin_id' => $admin->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Kembali ke sesi Administrator.');
    }

    /**
     * Reset Password instan ala e-Rapor SD (`/data_pengguna` -> `reset_passpengguna`).
     */
    public function resetPasswordInstan(Request $request, User $user)
    {
        $this->accounts->ensureAccountAccessible($user);
        $hasil = $this->accounts->resetPasswordInstan($user, $request->user()?->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'nama' => $user->nama_lengkap,
                'username' => $user->nama_pengguna,
                'password_baru' => $hasil['password_baru'],
                'message' => $hasil['message'],
            ]);
        }

        return back()->with('success', $hasil['message']);
    }

    /**
     * Toggle Aktif / Non-Aktifkan (Blokir) pengguna ala e-Rapor SD (`/data_pengguna` -> `reset_pengguna`).
     */
    public function toggleAktif(Request $request, User $user)
    {
        $this->accounts->ensureAccountAccessible($user);

        if ($user->nama_pengguna === 'admin' || $user->id === $request->user()?->id) {
            $msg = 'Akun administrator utama / sedang login tidak dapat dinonaktifkan.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->with('error', $msg);
        }

        $hasil = $this->accounts->toggleAktif($user);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'aktif' => $hasil['aktif'],
                'message' => $hasil['message'],
            ]);
        }

        return back()->with('success', $hasil['message']);
    }

    /**
     * Generate Semua Pengguna otomatis dari data Guru (PTK) dan Siswa di database.
     */
    public function generateSemuaPengguna(Request $request)
    {
        $this->accounts->accountsQuery();
        $hasil = $this->bulkGenerator->generate(TenantContext::id(), $request->user()?->id);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true] + $hasil);
        }

        $redirect = redirect()->route('admin.users.index')->with('success', $hasil['message']);
        if ($hasil['ekspor_url']) {
            $redirect->with('ekspor_sandi_url', $hasil['ekspor_url']);
        }

        return $redirect;
    }

    public function eksporSandiAwal(Request $request, string $token)
    {
        return app(InitialPasswordExportService::class)->streamOnce($request, $token);
    }

    public function pratinjauResetMassal(ResetMassalPasswordRequest $request)
    {
        $preview = app(MassPasswordResetService::class)
            ->preview($request->validated(), (int) $request->user()?->id);

        return response()->json(['success' => true] + $preview);
    }

    public function resetMassalPassword(ResetMassalPasswordRequest $request)
    {
        $result = app(MassPasswordResetService::class)
            ->execute($request->validated(), $request->user());

        if ($result['locked']) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false] + $result, 423);
            }

            return redirect()->route('admin.users.index')->with('error', $result['message']);
        }

        $warning = $result['ekspor_url']
            ? 'Password tidak dapat dilihat lagi; unduh dan bagikan dengan aman. Jika unduhan gagal, jalankan reset ulang untuk akun yang sama dengan opsi sertakan.'
            : null;

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'peringatan_sandi' => $warning] + $result);
        }

        $redirect = redirect()->route('admin.users.index')->with('success', $result['message']);
        if ($result['ekspor_url']) {
            $redirect->with('ekspor_sandi_url', $result['ekspor_url']);
        }

        return $redirect;
    }
}
