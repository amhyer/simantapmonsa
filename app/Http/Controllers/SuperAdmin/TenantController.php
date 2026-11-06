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

        $query = Tenant::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_sekolah', 'like', "%{$search}%")
                  ->orWhere('npsn', 'like', "%{$search}%")
                  ->orWhere('subdomain', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('aktif', $status === '1' || $status === 'aktif');
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
            'status'
        ));
    }

    public function show(Tenant $tenant)
    {
        $admin = User::where('tenant_id', $tenant->id)->where('peran', 'admin')->first();
        $totalSiswa = Siswa::where('tenant_id', $tenant->id)->count();
        $totalGuru = User::where('tenant_id', $tenant->id)->where('peran', 'guru')->count();
        $totalKepsek = User::where('tenant_id', $tenant->id)->where('peran', 'kepsek')->count();
        $totalTendik = User::where('tenant_id', $tenant->id)->where('peran', 'tendik')->count();

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
            'recentUsers'
        ));
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
            'admin_nama' => 'required|string|max:255',
            'admin_user' => 'required|string|max:64|unique:users,nama_pengguna',
            'admin_email' => 'nullable|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ], [
            'npsn.unique' => 'NPSN ini sudah terdaftar.',
            'subdomain.unique' => 'Subdomain ini sudah digunakan.',
            'admin_user.unique' => 'Nama pengguna admin sudah dipakai.',
        ]);

        DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'npsn' => $validated['npsn'],
                'nama_sekolah' => $validated['nama_sekolah'],
                'subdomain' => strtolower($validated['subdomain']),
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
