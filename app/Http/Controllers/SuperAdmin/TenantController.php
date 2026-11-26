<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Auth\RegisterSekolahController;
use App\Http\Controllers\Controller;
use App\Mail\SchoolRegistrationStatusMail;
use App\Models\CbtQuestionBank;
use App\Models\CbtResult;
use App\Models\CbtTestSession;
use App\Models\SekolahSettings;
use App\Models\Siswa;
use App\Models\User;
use App\Modules\Tenant\Models\Tenant;
use App\Services\SuperAdmin\TenantDataDeletionService;
use App\Services\SuperAdmin\TenantSubscriptionService;
use App\Services\Tenant\TenantQuotaService;
use App\Support\SchoolLevel;
use App\Support\SessionSecurity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            if ($status === 'pending') {
                $query->where('aktif', false)->where('meta->approval_status', 'pending');
            } else {
                $query->where('aktif', $status === '1' || $status === 'aktif');
            }
        }

        $pendingApprovalCount = Tenant::query()
            ->where('aktif', false)
            ->where('meta->approval_status', 'pending')
            ->count();

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

        $perPageParam = $request->get('per_page', '10');
        $perPage = $perPageParam === 'all' ? max(1, (clone $query)->count()) : (int) $perPageParam;
        if (! in_array($perPage, [10, 20, 50, 100], true) && $perPageParam !== 'all') {
            $perPage = 10;
            $perPageParam = '10';
        }

        $tenants = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        // Tambahkan metadata jumlah siswa, guru, dan tendik
        $tenantIds = $tenants->pluck('id')->all();
        $adminUsers = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('tenant_id', $tenantIds)
            ->where('peran', 'admin')
            ->get()
            ->keyBy('tenant_id');

        $siswaCounts = Siswa::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->select('tenant_id', DB::raw('count(*) as aggregate'))
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $guruCounts = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('peran', ['guru', 'kepsek'])
            ->select('tenant_id', DB::raw('count(*) as aggregate'))
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $tendikCounts = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('tenant_id', $tenantIds)
            ->where('peran', 'tendik')
            ->select('tenant_id', DB::raw('count(*) as aggregate'))
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        return view('superadmin.tenants.index', compact(
            'tenants',
            'adminUsers',
            'siswaCounts',
            'guruCounts',
            'tendikCounts',
            'search',
            'status',
            'paket',
            'langganan',
            'perPageParam',
            'pendingApprovalCount'
        ));
    }

    public function show(Tenant $tenant)
    {
        $admin = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenant->id)
            ->where('peran', 'admin')
            ->first();
        $totalSiswa = Siswa::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        if ($totalSiswa === 0) {
            $totalSiswa = User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('tenant_id', $tenant->id)
                ->where('peran', 'siswa')
                ->count();
        }
        $totalGuru = User::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('peran', 'guru')->count();
        $totalKepsek = User::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('peran', 'kepsek')->count();
        $totalTendik = User::withoutGlobalScopes()->whereNull('deleted_at')->where('tenant_id', $tenant->id)->where('peran', 'tendik')->count();

        $cbtBankCount = CbtQuestionBank::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $cbtSessionCount = CbtTestSession::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $cbtResultCount = CbtResult::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();

        $recentUsers = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        $quotaService = app(TenantQuotaService::class);
        $storageStatus = $quotaService->storageQuotaStatus($tenant->id, $tenant);
        $aiQuotaStatus = $quotaService->aiQuotaSummary(
            $tenant->id,
            TenantQuotaService::defaultAiQuotaForPaket($tenant->paket),
            false
        );

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
            'recentUsers',
            'storageStatus',
            'aiQuotaStatus'
        ));
    }

    public function update(Request $request, Tenant $tenant, TenantSubscriptionService $subscriptions)
    {
        $validated = $request->validate([
            'nama_sekolah' => 'required|string|max:255',
            'wilayah' => 'nullable|string|max:100',
            'domain_kustom' => 'nullable|string|max:191|unique:tenants,domain_kustom,'.$tenant->id,
            'paket' => 'required|string|in:standar,pro,enterprise',
            'kuota_siswa' => 'required|integer|min:10|max:100000',
            'kuota_storage_mb' => 'required|integer|min:100|max:1048576',
            'kuota_ai_harian' => 'nullable|integer|min:10|max:50000',
            'tgl_kedaluwarsa' => 'nullable|date',
            'catatan_langganan' => 'nullable|string|max:1000',
        ], [
            'domain_kustom.unique' => 'Domain kustom ini sudah dipakai oleh sekolah lain.',
        ]);

        $subscriptions->updateSubscription($tenant, $validated);

        return back()->with('success', "Informasi paket dan langganan sekolah '{$tenant->nama_sekolah}' berhasil diperbarui.");
    }

    public function extend(Request $request, Tenant $tenant, TenantSubscriptionService $subscriptions)
    {
        $validated = $request->validate([
            'months' => 'required|integer|in:1,3,6,12,24',
            'catatan_langganan' => 'nullable|string|max:1000',
        ]);

        $updated = $subscriptions->extendSubscription(
            $tenant,
            (int) $validated['months'],
            $validated['catatan_langganan'] ?? null
        );

        $jatuhTempo = $updated->tgl_kedaluwarsa?->format('d-m-Y') ?? '-';

        return back()->with(
            'success',
            "Masa aktif langganan '{$updated->nama_sekolah}' diperpanjang +{$validated['months']} bulan (sampai {$jatuhTempo})."
        );
    }

    public function toggleActive(Tenant $tenant)
    {
        $tenant->aktif = ! $tenant->aktif;
        $meta = is_array($tenant->meta) ? $tenant->meta : [];
        if ($tenant->aktif && ($meta['approval_status'] ?? null) === 'pending') {
            $meta['approval_status'] = 'approved';
            $meta['approved_at'] = now()->toIso8601String();
            $meta['approved_by'] = auth()->id();
            $tenant->meta = $meta;
            User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('tenant_id', $tenant->id)
                ->where('peran', 'admin')
                ->update(['aktif' => true]);
        }
        $tenant->save();

        $statusStr = $tenant->aktif ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Sekolah '{$tenant->nama_sekolah}' berhasil {$statusStr}.");
    }

    public function approve(Request $request, Tenant $tenant)
    {
        if (! $tenant->isEmailVerified() && ! $request->boolean('force_approve')) {
            return back()->with('error', "Email operator sekolah '{$tenant->nama_sekolah}' belum diverifikasi. Minta operator memverifikasi email terlebih dahulu atau gunakan persetujuan manual (force_approve).");
        }

        $meta = is_array($tenant->meta) ? $tenant->meta : [];
        $meta['approval_status'] = 'approved';
        $meta['approved_at'] = now()->toIso8601String();
        $meta['approved_by'] = auth()->id();
        $meta['npsn_verified'] = true;

        DB::transaction(function () use ($tenant, $meta) {
            $tenant->update([
                'aktif' => true,
                'meta' => $meta,
            ]);

            User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('tenant_id', $tenant->id)
                ->where('peran', 'admin')
                ->update(['aktif' => true]);
        });

        $this->kirimNotifikasiKeputusanSekolah($tenant, 'approved');

        return back()->with('success', "Pendaftaran sekolah '{$tenant->nama_sekolah}' (NPSN: {$tenant->npsn}) telah disetujui dan akun administrator telah diaktifkan.");
    }

    public function reject(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'alasan' => 'nullable|string|max:500',
        ]);

        $meta = is_array($tenant->meta) ? $tenant->meta : [];
        $meta['approval_status'] = 'rejected';
        $meta['rejected_at'] = now()->toIso8601String();
        $meta['rejected_by'] = auth()->id();
        $meta['rejection_reason'] = $validated['alasan'] ?? 'Pendaftaran ditolak oleh Super Administrator.';

        DB::transaction(function () use ($tenant, $meta) {
            $tenant->update([
                'aktif' => false,
                'meta' => $meta,
            ]);

            User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('tenant_id', $tenant->id)
                ->update(['aktif' => false]);
        });

        $this->kirimNotifikasiKeputusanSekolah($tenant, 'rejected', $meta['rejection_reason']);

        return back()->with('success', "Pendaftaran sekolah '{$tenant->nama_sekolah}' telah ditolak dan aksesnya dinonaktifkan.");
    }

    private function kirimNotifikasiKeputusanSekolah(Tenant $tenant, string $status, ?string $reason = null): void
    {
        try {
            $admin = User::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('tenant_id', $tenant->id)
                ->where('peran', 'admin')
                ->whereNotNull('email')
                ->first();

            if ($admin && filled($admin->email)) {
                Mail::to($admin->email)->send(
                    new SchoolRegistrationStatusMail($tenant, $status, $reason)
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }
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
        app(TenantDataDeletionService::class)->deleteTenantData($tenant);
    }

    /**
     * @param  array<int, int>  $userIds
     */
    protected function detachAndDeleteUserDependents(array $userIds): void
    {
        app(TenantDataDeletionService::class)->detachAndDeleteUserDependents($userIds);
    }

    public function store(Request $request)
    {
        $request->merge([
            'subdomain' => strtolower(trim((string) $request->input('subdomain', ''))),
            'admin_email' => $request->filled('admin_email') ? strtolower(trim((string) $request->input('admin_email'))) : null,
        ]);

        $validated = $request->validate([
            'npsn' => 'required|string|max:16|unique:tenants,npsn',
            'nama_sekolah' => 'required|string|max:255',
            'wilayah' => 'nullable|string|max:100',
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:64',
                'regex:/^[a-z0-9](?:[a-z0-9\-]{1,62}[a-z0-9])?$/',
                Rule::notIn(RegisterSekolahController::RESERVED_SUBDOMAINS),
                'unique:tenants,subdomain',
            ],
            'domain_kustom' => 'nullable|string|max:191|unique:tenants,domain_kustom',
            'paket' => 'nullable|string|in:standar,pro,enterprise',
            'kuota_siswa' => 'nullable|integer|min:10|max:100000',
            'kuota_storage_mb' => 'nullable|integer|min:100|max:1048576',
            'tgl_kedaluwarsa' => 'nullable|date',
            'catatan_langganan' => 'nullable|string|max:1000',
            'admin_nama' => 'required|string|max:255',
            'admin_user' => ['required', 'string', 'max:64', Rule::unique('users', 'nama_pengguna')->whereNull('tenant_id')],
            'admin_email' => 'nullable|email|max:255|unique:users,email',
            'admin_password' => ['required', 'string', Password::defaults()],
        ], [
            'npsn.unique' => 'NPSN ini sudah terdaftar.',
            'subdomain.unique' => 'Subdomain ini sudah digunakan.',
            'domain_kustom.unique' => 'Domain kustom ini sudah terdaftar pada sekolah lain.',
            'admin_user.unique' => 'Nama pengguna admin sudah dipakai.',
        ]);

        $subdomain = strtolower($validated['subdomain']);
        $adminEmail = ! empty($validated['admin_email'])
            ? strtolower(trim($validated['admin_email']))
            : strtolower(trim($validated['admin_user'])).'@'.$subdomain.'.simantap.sch.id';

        if (User::withoutGlobalScopes()->where('email', $adminEmail)->exists()) {
            return back()->withErrors([
                'admin_email' => 'Email admin sekolah ('.$adminEmail.') sudah digunakan. Masukkan alamat email lain yang unik.',
            ])->withInput();
        }

        DB::transaction(function () use ($validated, $subdomain, $adminEmail) {
            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'npsn' => $validated['npsn'],
                'nama_sekolah' => $validated['nama_sekolah'],
                'wilayah' => $validated['wilayah'] ?? null,
                'subdomain' => $subdomain,
                'domain_kustom' => ! empty($validated['domain_kustom']) ? strtolower(trim($validated['domain_kustom'])) : null,
                'paket' => $validated['paket'] ?? 'standar',
                'kuota_siswa' => $validated['kuota_siswa'] ?? 500,
                'kuota_storage_mb' => $validated['kuota_storage_mb'] ?? 2048,
                'tgl_kedaluwarsa' => $validated['tgl_kedaluwarsa'] ?? null,
                'catatan_langganan' => $validated['catatan_langganan'] ?? null,
                'aktif' => true,
                'meta' => [
                    'jenjang' => SchoolLevel::normalize($validated['jenjang'] ?? 'SD', 'SD'),
                ],
            ]);

            $adminUser = User::create([
                'uuid' => (string) Str::uuid(),
                'nama_lengkap' => $validated['admin_nama'],
                'nama_pengguna' => $validated['admin_user'],
                'email' => $adminEmail,
                'kata_sandi' => Hash::make($validated['admin_password']),
                'peran' => 'admin',
                'tenant_id' => $tenant->id,
                'aktif' => true,
                'force_password_change' => false,
            ]);

            // Auto-provision pengaturan dasar sekolah agar siap pakai saat login pertama
            if (Schema::hasTable('sekolah_settings')) {
                SekolahSettings::withoutGlobalScopes()->firstOrCreate(
                    ['tenant_id' => $tenant->id],
                    [
                        'guru_id' => $adminUser->id,
                        'nama_sekolah' => $validated['nama_sekolah'],
                        'npsn' => $validated['npsn'],
                        'jenjang' => $validated['jenjang'] ?? 'SD',
                        'kepala_sekolah' => $validated['kepala_sekolah'] ?? null,
                        'alamat' => $validated['alamat'] ?? null,
                        'kota' => $validated['kota'] ?? null,
                        'email' => $adminEmail,
                        'telepon' => $validated['telepon'] ?? null,
                    ]
                );
            }
        });

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Sekolah '{$validated['nama_sekolah']}', pengaturan awal, dan akun administrator berhasil didaftarkan.");
    }

    public function users(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        $peran = $request->get('peran');
        $wilayah = $request->get('wilayah');
        $tenantId = $request->get('tenant_id');
        $tab = $request->get('tab', 'semua'); // 'semua', 'per_sekolah', 'per_wilayah'
        $perPageParam = $request->get('per_page', '10');

        $query = User::withoutGlobalScopes()->whereNull('deleted_at')->with('tenant');

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
            $query->whereHas('tenant', fn ($q) => $q->where('wilayah', $wilayah));
        }

        $perPage = $perPageParam === 'all' ? max(1, (clone $query)->count()) : (int) $perPageParam;
        if (! in_array($perPage, [10, 20, 50, 100], true) && $perPageParam !== 'all') {
            $perPage = 10;
            $perPageParam = '10';
        }

        $users = $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();

        // Daftar wilayah unik untuk filter & rekap
        $wilayahList = Tenant::whereNotNull('wilayah')
            ->where('wilayah', '!=', '')
            ->distinct()
            ->orderBy('wilayah')
            ->pluck('wilayah');

        // Daftar sekolah untuk filter
        $tenantsList = Tenant::query()
            ->when($wilayah, fn ($q) => $q->where('wilayah', $wilayah))
            ->orderBy('nama_sekolah')
            ->get(['id', 'nama_sekolah', 'wilayah', 'npsn', 'subdomain']);

        // Rekap per-sekolah
        $rekapSekolah = Tenant::withCount([
            'users as total_akun',
            'users as guru_count' => fn ($q) => $q->where('peran', 'guru'),
            'users as siswa_count' => fn ($q) => $q->where('peran', 'siswa'),
            'users as admin_count' => fn ($q) => $q->where('peran', 'admin'),
            'users as tendik_count' => fn ($q) => $q->whereIn('peran', ['tendik', 'kepsek']),
        ])
            ->when($wilayah, fn ($q) => $q->where('wilayah', $wilayah))
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
                $rw->total_akun = User::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('tenant_id', $tenantIds)->count();
                $rw->total_siswa = User::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('tenant_id', $tenantIds)->where('peran', 'siswa')->count();
                $rw->total_guru = User::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('tenant_id', $tenantIds)->where('peran', 'guru')->count();
                $rw->total_admin = User::withoutGlobalScopes()->whereNull('deleted_at')->whereIn('tenant_id', $tenantIds)->where('peran', 'admin')->count();

                return $rw;
            });

        $totalUsersPlatform = User::withoutGlobalScopes()->whereNull('deleted_at')->count();
        $totalUnassigned = User::withoutGlobalScopes()->whereNull('deleted_at')->whereNull('tenant_id')->where('peran', '!=', 'super_admin')->count();

        return view('superadmin.users.index', compact(
            'users',
            'search',
            'peran',
            'wilayah',
            'tenantId',
            'tab',
            'perPageParam',
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

    public function impersonate(Request $request, Tenant $tenant)
    {
        $admin = User::where('tenant_id', $tenant->id)
            ->where('peran', 'admin')
            ->first();

        if (! $admin) {
            return back()->with('error', "Gagal: Sekolah '{$tenant->nama_sekolah}' belum memiliki akun administrator.");
        }

        if (! $admin->aktif || $admin->archived_at !== null) {
            return back()->with('error', "Gagal: Akun administrator sekolah '{$tenant->nama_sekolah}' sedang nonaktif.");
        }

        $superAdminId = auth()->id();
        session([
            'impersonator_id' => $superAdminId,
            'impersonator_tenant_uuid' => $tenant->uuid,
        ]);

        auth()->login($admin);
        // Perpindahan hak akses wajib meregenerasi sesi (anti session fixation),
        // sama seperti login normal di LoginController.
        $request->session()->regenerate();

        Log::info('Super admin impersonate admin sekolah', [
            'super_admin_id' => $superAdminId,
            'target_admin_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', "Berhasil masuk ke dasbor sekolah '{$tenant->nama_sekolah}'. Anda sedang dalam mode supervisi.");
    }

    public function leaveImpersonate(Request $request)
    {
        $superAdminId = session('impersonator_id');
        $tenantUuid = session('impersonator_tenant_uuid');

        if (! $superAdminId) {
            return redirect()->route('home');
        }

        $superAdmin = User::withoutGlobalScopes()->find($superAdminId);
        if (! $superAdmin || ! $superAdmin->aktif || $superAdmin->archived_at !== null || $superAdmin->peran !== 'super_admin') {
            session()->forget(['impersonator_id', 'impersonator_tenant_uuid']);
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        session()->forget(['impersonator_id', 'impersonator_tenant_uuid']);
        auth()->login($superAdmin);
        $request->session()->regenerate();

        Log::info('Super admin keluar dari mode impersonate', [
            'super_admin_id' => $superAdmin->id,
            'ip' => $request->ip(),
        ]);

        if ($tenantUuid) {
            return redirect()->route('superadmin.tenants.show', $tenantUuid)
                ->with('success', 'Kembali ke sesi Super Administrator.');
        }

        return redirect()->route('superadmin.dashboard')
            ->with('success', 'Kembali ke sesi Super Administrator.');
    }

    public function resetAdminMfa(Request $request, Tenant $tenant)
    {
        $admin = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenant->id)
            ->where('peran', 'admin')
            ->first();

        if (! $admin) {
            return back()->with('error', "Sekolah '{$tenant->nama_sekolah}' belum memiliki akun administrator.");
        }

        $admin->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_confirmed_at' => null,
            'mfa_last_used_window' => null,
            'remember_token' => Str::random(60),
        ])->save();

        SessionSecurity::invalidateUserSessions((int) $admin->id);

        Log::warning('Super admin mereset MFA admin sekolah', [
            'super_admin_id' => auth()->id(),
            'target_admin_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'ip' => $request->ip(),
        ]);

        return back()->with('success', "MFA untuk administrator sekolah '{$tenant->nama_sekolah}' ({$admin->nama_pengguna}) berhasil direset.");
    }

    public function resetAdminPassword(Request $request, Tenant $tenant)
    {
        $admin = User::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('tenant_id', $tenant->id)
            ->where('peran', 'admin')
            ->first();

        if (! $admin) {
            return back()->with('error', "Gagal: Sekolah '{$tenant->nama_sekolah}' belum memiliki akun administrator.");
        }

        $validated = $request->validate([
            'password_baru' => ['required', 'string', Password::defaults()],
        ]);

        $admin->forceFill([
            'kata_sandi' => Hash::make($validated['password_baru']),
            'force_password_change' => true,
            'remember_token' => Str::random(60),
        ])->save();

        SessionSecurity::invalidateUserSessions((int) $admin->id);

        Log::info('Super admin mereset kata sandi admin sekolah', [
            'super_admin_id' => auth()->id(),
            'target_admin_id' => $admin->id,
            'tenant_id' => $tenant->id,
            'ip' => $request->ip(),
        ]);

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

        $driver = DB::getDriverName();
        // Di PostgreSQL, `Schema::disableForeignKeyConstraints()` menjalankan
        // `SET session_replication_role = 'replica'` yang butuh hak superuser;
        // `detachAndDeleteUserDependents` sudah menghapus child secara berurutan.
        if ($driver !== 'pgsql') {
            Schema::disableForeignKeyConstraints();
        }
        DB::beginTransaction();
        try {
            $this->detachAndDeleteUserDependents([(int) $user->id]);
            $user->forceDelete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            if ($driver !== 'pgsql') {
                Schema::enableForeignKeyConstraints();
            }
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

        $currentId = (int) auth()->id();
        $rawIds = array_map('intval', $request->input('user_ids', []));

        // Saring keluar diri sendiri & akun super_admin SEBELUM menghapus relasi presensi/aktivitas
        $targetIds = User::withoutGlobalScopes()
            ->whereIn('id', $rawIds)
            ->where('id', '!=', $currentId)
            ->where('peran', '!=', 'super_admin')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($targetIds)) {
            return back()->with('error', 'Tidak ada akun valid yang dapat dihapus.');
        }

        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            Schema::disableForeignKeyConstraints();
        }
        DB::beginTransaction();
        try {
            $this->detachAndDeleteUserDependents($targetIds);
            $count = User::withoutGlobalScopes()
                ->whereIn('id', $targetIds)
                ->forceDelete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            if ($driver !== 'pgsql') {
                Schema::enableForeignKeyConstraints();
            }
        }

        return back()->with('success', "Sebanyak {$count} akun pengguna berhasil dihapus permanen.");
    }
}
