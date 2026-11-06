<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('aktif', true)->count();
        $inactiveTenants = $totalTenants - $activeTenants;

        $totalUsers = User::whereNotNull('tenant_id')->count();
        $totalSiswa = Siswa::count();
        $totalGuru = User::whereIn('peran', ['guru', 'kepsek'])->count();

        // 5 Sekolah terbaru
        $recentTenants = Tenant::orderByDesc('created_at')->take(5)->get();

        // Hitung statistik per tenant untuk tabel ringkasan
        $tenantsWithStats = Tenant::orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(function ($t) {
                return [
                    'tenant' => $t,
                    'admin' => User::where('tenant_id', $t->id)->where('peran', 'admin')->first(),
                    'total_siswa' => Siswa::where('tenant_id', $t->id)->count(),
                    'total_guru' => User::where('tenant_id', $t->id)->where('peran', 'guru')->count(),
                ];
            });

        return view('superadmin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'inactiveTenants',
            'totalUsers',
            'totalSiswa',
            'totalGuru',
            'recentTenants',
            'tenantsWithStats'
        ));
    }
}
