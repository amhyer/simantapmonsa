<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'npsn' => 'required|string|max:16|unique:tenants,npsn',
            'nama_sekolah' => 'required|string|max:255',
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

        $query = User::with('tenant')->whereNotNull('tenant_id');

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

        $users = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('superadmin.users.index', compact('users', 'search', 'peran'));
    }
}
