<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        $status = $request->get('status');
        $paket = $request->get('paket');
        $langganan = $request->get('langganan');

        $query = Tenant::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_sekolah', 'like', "%{$search}%")
                  ->orWhere('npsn', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%")
                  ->orWhere('domain_kustom', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('aktif', $status === '1' || $status === 'aktif');
        }

        if ($paket !== null && $paket !== '') {
            $query->where('paket', $paket);
        }

        if ($langganan === 'expired') {
            $query->whereNotNull('tgl_kedaluwarsa')->where('tgl_kedaluwarsa', '<', now()->toDateString());
        } elseif ($langganan === 'warning') {
            $query->whereNotNull('tgl_kedaluwarsa')
                  ->where('tgl_kedaluwarsa', '>=', now()->toDateString())
                  ->where('tgl_kedaluwarsa', '<=', now()->addDays(30)->toDateString());
        } elseif ($langganan === 'aktif') {
            $query->where(function ($q) {
                $q->whereNull('tgl_kedaluwarsa')
                  ->orWhere('tgl_kedaluwarsa', '>=', now()->toDateString());
            });
        }

        $tenants = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        // Tambahkan metadata jumlah siswa dan guru
        $tenantIds = $tenants->pluck('id')->all();
        $adminUsers = User::whereIn('tenant_id', $tenantIds)
            ->where('peran', 'admin')
            ->get()
            ->keyBy('tenant_id');

        $siswaCounts = Siswa::whereIn('tenant_id', $tenantIds)
            ->select('tenant_id', DB::raw('count(*) as aggregate'))
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $guruCounts = User::whereIn('tenant_id', $tenantIds)
            ->whereIn('peran', ['guru', 'kepsek'])
            ->select('tenant_id', DB::raw('count(*) as aggregate'))
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        return view('superadmin.tenants.index', compact(
            'tenants',
            'adminUsers',
            'siswaCounts',
            'guruCounts',
            'search',
            'status',
            'paket',
            'langganan'
        ));
    }

    public function show(Tenant $tenant)
    {
        $admin = User::where('tenant_id', $tenant->id)->where('peran', 'admin')->first();
        $totalSiswa = Siswa::where('tenant_id', $tenant->id)->count();
        $totalGuru = User::where('tenant_id', $tenant->id)->where('peran', 'guru')->count();
        $totalKepsek = User::where('tenant_id', $tenant->id)->where('peran', 'kepsek')->count();
        $totalTendik = User::where('tenant_id', $tenant->id)->where('peran', 'tendik')->count();

        $cbtBankCount = \App\Models\CbtQuestionBank::where('tenant_id', $tenant->id)->count();
        $cbtSessionCount = \App\Models\CbtTestSession::where('tenant_id', $tenant->id)->count();
        $cbtResultCount = \App\Models\CbtResult::where('tenant_id', $tenant->id)->count();

        $recentUsers = User::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        return view('superadmin.tenants.show', compact(
            'tenant',
            'admin',
            'totalSiswa',
            'totalGuru',
            'totalKepsek',
            'totalTendik',
            'cbtBankCount',
            'cbtSessionCount',
            'cbtResultCount',
            'recentUsers'
        ));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'nama_sekolah' => 'required|string|max:255',
            'wilayah' => 'nullable|string|max:100',
            'domain_kustom' => 'nullable|string|max:191|unique:tenants,domain_kustom,' . $tenant->id,
            'paket' => 'required|string|in:standar,pro,enterprise',
            'kuota_siswa' => 'required|integer|min:10|max:100000',
            'kuota_storage_mb' => 'required|integer|min:100|max:1048576',
            'tgl_kedaluwarsa' => 'nullable|date',
            'catatan_langganan' => 'nullable|string|max:1000',
        ], [
            'domain_kustom.unique' => 'Domain kustom ini sudah dipakai oleh sekolah lain.',
        ]);

        if (! empty($validated['domain_kustom'])) {
            $validated['domain_kustom'] = strtolower(trim($validated['domain_kustom']));
        } else {
            $validated['domain_kustom'] = null;
        }

        $tenant->update($validated);

        return back()->with('success', "Informasi paket dan langganan sekolah '{$tenant->nama_sekolah}' berhasil diperbarui.");
    }

    public function toggleActive(Tenant $tenant)
    {
        $tenant->aktif = !$tenant->aktif;
        $tenant->save();

        $statusStr = $tenant->aktif ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Sekolah '{$tenant->nama_sekolah}' berhasil {$statusStr}.");
    }

    public function destroy(Request $request, Tenant $tenant)
    {
        $today = now()->toDateString();
        $isExpired = $tenant->tgl_kedaluwarsa && $tenant->tgl_kedaluwarsa < $today;
        $isForced = $request->boolean('force') || $request->input('confirm') === '1';

        // Sekolah aktif yang belum kedaluwarsa dilindungi kecuali ada konfirmasi paksa (force=1)
        if ($tenant->aktif && ! $isExpired && ! $isForced) {
            return redirect()->route('superadmin.tenants.index')
                ->with('error', 'Penghapusan sekolah sementara dinonaktifkan. Nonaktifkan sekolah sampai prosedur penghapusan data per tenant tersedia dan diuji.');
        }

        $nama = $tenant->nama_sekolah;
        $this->deleteTenantData($tenant);

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Sekolah '{$nama}' dan seluruh data akun/nilainya berhasil dihapus permanen.");
    }

    public function destroyExpiredTenants(Request $request)
    {
        $today = now()->toDateString();
        $expiredTenants = Tenant::where(function ($q) use ($today) {
            $q->whereNotNull('tgl_kedaluwarsa')
              ->where('tgl_kedaluwarsa', '<', $today);
        })->orWhere('aktif', false)->get();

        if ($expiredTenants->isEmpty()) {
            return back()->with('info', 'Tidak ditemukan sekolah dengan status hosting kedaluwarsa atau nonaktif.');
        }

        $count = 0;
        foreach ($expiredTenants as $tenant) {
            $this->deleteTenantData($tenant);
            $count++;
        }

        return back()->with('success', "Sebanyak {$count} sekolah yang belum memperpanjang hosting / nonaktif berhasil dihapus permanen.");
    }

    protected function deleteTenantData(Tenant $tenant): void
    {
        $tenantId = $tenant->id;
        Schema::disableForeignKeyConstraints();
        DB::beginTransaction();
        try {
            // 1. Bersihkan tabel-tabel anak siswa_id
            if (Schema::hasTable('siswa')) {
                $siswaIds = DB::table('siswa')->where('tenant_id', $tenantId)->pluck('id');
                if ($siswaIds->isNotEmpty()) {
                    foreach (Schema::getTableListing() as $tbl) {
                        if ($tbl === 'siswa') continue;
                        if (Schema::hasColumn($tbl, 'siswa_id')) {
                            DB::table($tbl)->whereIn('siswa_id', $siswaIds)->delete();
                        }
                    }
                }
            }

            // 2. Bersihkan tabel-tabel anak rombel_id
            if (Schema::hasTable('rombel')) {
                $rombelIds = DB::table('rombel')->where('tenant_id', $tenantId)->pluck('id');
                if ($rombelIds->isNotEmpty()) {
                    foreach (['jadwal_pelajaran', 'pembelajaran', 'anggota_rombel', 'penilaian', 'nilai', 'presensi'] as $tbl) {
                        if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'rombel_id')) {
                            DB::table($tbl)->whereIn('rombel_id', $rombelIds)->delete();
                        }
                    }
                }
            }

            // 3. Bersihkan presensi pegawai dan aktivitas sebelum hapus users
            if (Schema::hasTable('users')) {
                $userIds = DB::table('users')->where('tenant_id', $tenantId)->pluck('id');
                if ($userIds->isNotEmpty()) {
                    if (Schema::hasTable('presensi_pegawai')) {
                        DB::table('presensi_pegawai')->whereIn('user_id', $userIds)->delete();
                    }
                    if (Schema::hasTable('aktivitas')) {
                        DB::table('aktivitas')->whereIn('user_id', $userIds)->delete();
                    }
                }
            }

            // 4. Hapus seluruh data ber-tenant_id dari tabel-tabel terdaftar
            foreach (Schema::getTableListing() as $tbl) {
                if ($tbl === 'tenants') continue;
                if (Schema::hasColumn($tbl, 'tenant_id')) {
                    DB::table($tbl)->where('tenant_id', $tenantId)->delete();
                }
            }

            // 5. Hapus record tenant itu sendiri
            $tenant->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'npsn' => 'required|string|max:16|unique:tenants,npsn',
            'nama_sekolah' => 'required|string|max:255',
            'wilayah' => 'nullable|string|max:100',
            'subdomain' => 'required|string|max:64|alpha_dash|unique:tenants,subdomain',
            'domain_kustom' => 'nullable|string|max:191|unique:tenants,domain_kustom',
            'paket' => 'nullable|string|in:standar,pro,enterprise',
            'kuota_siswa' => 'nullable|integer|min:10|max:100000',
            'kuota_storage_mb' => 'nullable|integer|min:100|max:1048576',
            'tgl_kedaluwarsa' => 'nullable|date',
            'catatan_langganan' => 'nullable|string|max:1000',
            'admin_nama' => 'required|string|max:255',
            'admin_user' => 'required|string|max:64|unique:users,nama_pengguna',
            'admin_email' => 'nullable|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ], [
            'npsn.unique' => 'NPSN ini sudah terdaftar.',
            'subdomain.unique' => 'Subdomain ini sudah digunakan.',
            'domain_kustom.unique' => 'Domain kustom ini sudah terdaftar pada sekolah lain.',
            'admin_user.unique' => 'Nama pengguna admin sudah dipakai.',
        ]);

        DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'npsn' => $validated['npsn'],
                'nama_sekolah' => $validated['nama_sekolah'],
                'wilayah' => $validated['wilayah'] ?? null,
                'subdomain' => strtolower($validated['subdomain']),
                'domain_kustom' => ! empty($validated['domain_kustom']) ? strtolower(trim($validated['domain_kustom'])) : null,
                'paket' => $validated['paket'] ?? 'standar',
                'kuota_siswa' => $validated['kuota_siswa'] ?? 500,
                'kuota_storage_mb' => $validated['kuota_storage_mb'] ?? 2048,
                'tgl_kedaluwarsa' => $validated['tgl_kedaluwarsa'] ?? null,
                'catatan_langganan' => $validated['catatan_langganan'] ?? null,
                'aktif' => true,
            ]);

            User::create([
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => $validated['admin_nama'],
                'nama_pengguna' => $validated['admin_user'],
                'email' => $validated['admin_email'] ?? null,
                'kata_sandi' => Hash::make($validated['admin_password']),
                'peran' => 'admin',
                'tenant_id' => $tenant->id,
                'aktif' => true,
                'force_password_change' => false,
            ]);
        });

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Sekolah '{$validated['nama_sekolah']}' dan akun administrator berhasil didaftarkan.");
    }

    public function users(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        $peran = $request->get('peran');
        $wilayah = $request->get('wilayah');
        $tenantId = $request->get('tenant_id');
        $tab = $request->get('tab', 'semua'); // 'semua', 'per_sekolah', 'per_wilayah'

        $query = User::with('tenant');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nama_pengguna', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($peran !== null && $peran !== '') {
            $query->where('peran', $peran);
        }

        if ($tenantId === 'unassigned') {
            $query->whereNull('tenant_id');
        } elseif ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        if ($wilayah !== null && $wilayah !== '') {
            $query->whereHas('tenant', fn($q) => $q->where('wilayah', $wilayah));
        }

        $users = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Daftar wilayah unik untuk filter & rekap
        $wilayahList = Tenant::whereNotNull('wilayah')
            ->where('wilayah', '!=', '')
            ->distinct()
            ->orderBy('wilayah')
            ->pluck('wilayah');

        // Daftar sekolah untuk filter
        $tenantsList = Tenant::query()
            ->when($wilayah, fn($q) => $q->where('wilayah', $wilayah))
            ->orderBy('nama_sekolah')
            ->get(['id', 'nama_sekolah', 'wilayah', 'npsn', 'subdomain']);

        // Rekap per-sekolah
        $rekapSekolah = Tenant::withCount([
            'users as total_akun',
            'users as guru_count' => fn($q) => $q->where('peran', 'guru'),
            'users as siswa_count' => fn($q) => $q->where('peran', 'siswa'),
            'users as admin_count' => fn($q) => $q->where('peran', 'admin'),
            'users as tendik_count' => fn($q) => $q->whereIn('peran', ['tendik', 'kepsek']),
        ])
        ->when($wilayah, fn($q) => $q->where('wilayah', $wilayah))
        ->orderBy('nama_sekolah')
        ->get();

        // Rekap per-wilayah
        $rekapWilayah = Tenant::query()
            ->whereNotNull('wilayah')
            ->where('wilayah', '!=', '')
            ->select('wilayah', DB::raw('count(id) as total_sekolah'))
            ->groupBy('wilayah')
            ->orderBy('wilayah')
            ->get()
            ->map(function ($rw) {
                $tenantIds = Tenant::where('wilayah', $rw->wilayah)->pluck('id');
                $rw->total_akun = User::whereIn('tenant_id', $tenantIds)->count();
                $rw->total_siswa = User::whereIn('tenant_id', $tenantIds)->where('peran', 'siswa')->count();
                $rw->total_guru = User::whereIn('tenant_id', $tenantIds)->where('peran', 'guru')->count();
                $rw->total_admin = User::whereIn('tenant_id', $tenantIds)->where('peran', 'admin')->count();
                return $rw;
            });

        $totalUsersPlatform = User::count();
        $totalUnassigned = User::whereNull('tenant_id')->where('peran', '!=', 'super_admin')->count();

        return view('superadmin.users.index', compact(
            'users',
            'search',
            'peran',
            'wilayah',
            'tenantId',
            'tab',
            'wilayahList',
            'tenantsList',
            'rekapSekolah',
            'rekapWilayah',
            'totalUsersPlatform',
            'totalUnassigned'
        ));
    }

    public function assignTenant(Request $request, User $user)
    {
        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);
        if ($user->tenant_id !== null && (int) $user->tenant_id !== (int) $tenant->id) {
            return back()->with('error', 'Pemindahan akun antar sekolah dinonaktifkan sampai riwayat datanya dapat dipindahkan dengan aman.');
        }
        $user->tenant_id = $tenant->id;
        $user->save();

        // NISN tidak cukup menjadi bukti untuk memindahkan data siswa lama.

        return back()->with('success', "Akun '{$user->nama_lengkap}' berhasil ditautkan kembali ke sekolah {$tenant->nama_sekolah}.");
    }

    public function impersonate(Tenant $tenant)
    {
        $admin = User::where('tenant_id', $tenant->id)
            ->where('peran', 'admin')
            ->first();

        if (! $admin) {
            return back()->with('error', "Gagal: Sekolah '{$tenant->nama_sekolah}' belum memiliki akun administrator.");
        }

        $superAdminId = auth()->id();
        session([
            'impersonator_id' => $superAdminId,
            'impersonator_tenant_uuid' => $tenant->uuid,
        ]);

        auth()->login($admin);

        return redirect()->route('admin.dashboard')
            ->with('success', "Berhasil masuk ke dasbor sekolah '{$tenant->nama_sekolah}'. Anda sedang dalam mode supervisi.");
    }

    public function leaveImpersonate()
    {
        $superAdminId = session('impersonator_id');
        $tenantUuid = session('impersonator_tenant_uuid');

        if (! $superAdminId) {
            return redirect()->route('home');
        }

        $superAdmin = User::find($superAdminId);
        if (! $superAdmin || $superAdmin->peran !== 'super_admin') {
            session()->forget(['impersonator_id', 'impersonator_tenant_uuid']);
            auth()->logout();
            return redirect()->route('login');
        }

        session()->forget(['impersonator_id', 'impersonator_tenant_uuid']);
        auth()->login($superAdmin);

        if ($tenantUuid) {
            return redirect()->route('superadmin.tenants.show', $tenantUuid)
                ->with('success', 'Kembali ke sesi Super Administrator.');
        }

        return redirect()->route('superadmin.dashboard')
            ->with('success', 'Kembali ke sesi Super Administrator.');
    }

    public function resetAdminPassword(Request $request, Tenant $tenant)
    {
        $admin = User::where('tenant_id', $tenant->id)
            ->where('peran', 'admin')
            ->first();

        if (! $admin) {
            return back()->with('error', "Gagal: Sekolah '{$tenant->nama_sekolah}' belum memiliki akun administrator.");
        }

        $validated = $request->validate([
            'password_baru' => 'required|string|min:8',
        ]);

        $admin->kata_sandi = Hash::make($validated['password_baru']);
        $admin->force_password_change = false;
        $admin->save();

        return back()->with('success', "Kata sandi admin sekolah '{$admin->nama_lengkap}' ({$admin->nama_pengguna}) berhasil direset!");
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
        }

        if ($user->peran === 'super_admin' && User::where('peran', 'super_admin')->count() <= 1) {
            return back()->with('error', 'Tidak dapat menghapus Super Admin terakhir dalam sistem.');
        }

        $nama = $user->nama_lengkap ?? $user->nama_pengguna;

        Schema::disableForeignKeyConstraints();
        try {
            if (Schema::hasTable('presensi_pegawai')) {
                DB::table('presensi_pegawai')->where('user_id', $user->id)->delete();
            }
            if (Schema::hasTable('aktivitas')) {
                DB::table('aktivitas')->where('user_id', $user->id)->delete();
            }
            $user->forceDelete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return back()->with('success', "Akun pengguna '{$nama}' berhasil dihapus permanen dari sistem.");
    }

    public function bulkDestroyUsers(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ], [
            'user_ids.required' => 'Pilih minimal satu akun pengguna yang ingin dihapus.',
        ]);

        $currentId = auth()->id();
        $targetIds = array_diff($request->input('user_ids', []), [$currentId]);

        if (empty($targetIds)) {
            return back()->with('error', 'Tidak ada akun valid yang dapat dihapus.');
        }

        Schema::disableForeignKeyConstraints();
        try {
            if (Schema::hasTable('presensi_pegawai')) {
                DB::table('presensi_pegawai')->whereIn('user_id', $targetIds)->delete();
            }
            if (Schema::hasTable('aktivitas')) {
                DB::table('aktivitas')->whereIn('user_id', $targetIds)->delete();
            }
            $count = User::whereIn('id', $targetIds)
                ->where('peran', '!=', 'super_admin')
                ->forceDelete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return back()->with('success', "Sebanyak {$count} akun pengguna berhasil dihapus permanen.");
    }
}
