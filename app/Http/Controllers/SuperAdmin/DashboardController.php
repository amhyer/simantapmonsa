<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CbtQuestionBank;
use App\Models\CbtTestSession;
use App\Models\Ptk;
use App\Models\Rombel;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;

class DashboardController extends Controller
{
    public function index()
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('aktif', true)->count();
        $inactiveTenants = $totalTenants - $activeTenants;
        $expiredTenants = Tenant::whereNotNull('tgl_kedaluwarsa')
            ->where('tgl_kedaluwarsa', '<', now()->toDateString())
            ->count();

        $totalUsers = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNotNull('tenant_id')
            ->count();
        $totalSiswa = Siswa::withoutGlobalScopes()->count();
        $totalKuotaSiswa = (int) Tenant::sum('kuota_siswa') ?: 500;
        $totalGuru = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('peran', ['guru', 'kepsek'])
            ->count();
        $totalTendik = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('peran', 'tendik')
            ->count();
        $totalPtk = Ptk::withoutGlobalScopes()->count();
        $totalRombel = Rombel::withoutGlobalScopes()->count();

        // Statistik CBT & kesehatan isolasi data
        $totalCbtBanks = CbtQuestionBank::withoutGlobalScopes()->count();
        $totalCbtSessions = CbtTestSession::withoutGlobalScopes()->count();
        $unassignedUsersCount = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNull('tenant_id')
            ->where('peran', '!=', 'super_admin')
            ->count();
        $orphanPtkCount = Ptk::withoutGlobalScopes()->whereNull('tenant_id')->count();
        $orphanSiswaCount = Siswa::withoutGlobalScopes()->whereNull('tenant_id')->count();

        // 5 Sekolah terbaru
        $recentTenants = Tenant::orderByDesc('created_at')->take(5)->get();

        // Hitung statistik lengkap per tenant untuk tabel ringkasan eksekutif
        $tenantsWithStats = Tenant::orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(function ($t) {
                $siswaCount = Siswa::withoutGlobalScopes()->where('tenant_id', $t->id)->count();
                if ($siswaCount === 0) {
                    $siswaCount = User::withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->where('tenant_id', $t->id)
                        ->where('peran', 'siswa')
                        ->count();
                }

                return [
                    'tenant' => $t,
                    'admin' => User::withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->where('tenant_id', $t->id)
                        ->where('peran', 'admin')
                        ->first(),
                    'kepsek' => User::withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->where('tenant_id', $t->id)
                        ->where('peran', 'kepsek')
                        ->first(),
                    'total_siswa' => $siswaCount,
                    'total_guru' => User::withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->where('tenant_id', $t->id)
                        ->where('peran', 'guru')
                        ->count(),
                    'total_tendik' => User::withoutGlobalScopes()
                        ->whereNull('deleted_at')
                        ->where('tenant_id', $t->id)
                        ->where('peran', 'tendik')
                        ->count(),
                    'total_rombel' => Rombel::withoutGlobalScopes()
                        ->where('tenant_id', $t->id)
                        ->count(),
                ];
            });

        // Aktivitas login terakhir lintas tenant
        $recentLogins = User::withoutGlobalScopes()
            ->with('tenant')
            ->whereNull('deleted_at')
            ->whereNotNull('terakhir_masuk')
            ->orderByDesc('terakhir_masuk')
            ->take(6)
            ->get();

        return view('superadmin.dashboard', compact(
            'totalTenants',
            'activeTenants',
            'inactiveTenants',
            'expiredTenants',
            'totalUsers',
            'totalSiswa',
            'totalKuotaSiswa',
            'totalGuru',
            'totalTendik',
            'totalPtk',
            'totalRombel',
            'totalCbtBanks',
            'totalCbtSessions',
            'unassignedUsersCount',
            'orphanPtkCount',
            'orphanSiswaCount',
            'recentTenants',
            'tenantsWithStats',
            'recentLogins'
        ));
    }
}
