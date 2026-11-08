<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\DapodikImportLog;
use App\Models\Siswa;
use App\Modules\Tenant\Contexts\TenantContext;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    private function tenantId(): ?int
    {
        $tenantId = TenantContext::id();
        abort_if($tenantId === null && (config('simantap.multi_tenant_enabled', false) || Tenant::query()->exists()), 403, 'Akun admin belum terhubung ke sekolah.');

        return $tenantId;
    }

    private function ensureKeyAccessible(ApiKey $apiKey): void
    {
        $tenantId = $this->tenantId();
        $ownerTenantId = $apiKey->user?->tenant_id;
        abort_unless($apiKey->user && ($ownerTenantId === null ? null : (int) $ownerTenantId) === $tenantId, 404);
    }

    public function index()
    {
        $tenantId = $this->tenantId();
        $keys = ApiKey::with('user')->whereHas('user', fn ($query) => $query->where('tenant_id', $tenantId))->orderBy('created_at', 'desc')->get();
        // Log lama belum mempunyai tenant_id; pemilik user saat ini tidak
        // cukup untuk membuktikan sekolah pemilik log historis.
        $logs = $tenantId === null
            ? DapodikImportLog::with('user')->orderBy('created_at', 'desc')->limit(20)->get()
            : collect();
        $siswaDapodik = Siswa::whereNotNull('rekaman->dapodik_imported_at')->count();

        return view('admin.api-keys.index', compact('keys', 'logs', 'siswaDapodik'));
    }

    public function store(Request $request)
    {
        $this->tenantId();
        $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $result = ApiKey::generate(auth()->id(), $request->name);

        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API Key berhasil dibuat.')
            ->with('new_key', $result['plaintext_key']);
    }

    public function destroy(ApiKey $apiKey)
    {
        $this->ensureKeyAccessible($apiKey);
        $apiKey->delete();
        return redirect()->route('admin.api-keys.index')
            ->with('success', 'API Key dihapus.');
    }

    public function toggle(ApiKey $apiKey)
    {
        $this->ensureKeyAccessible($apiKey);
        $apiKey->update(['active' => !$apiKey->active]);
        return redirect()->route('admin.api-keys.index')
            ->with('success', $apiKey->active ? 'API Key diaktifkan.' : 'API Key dinonaktifkan.');
    }
}
