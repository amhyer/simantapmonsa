<?php

namespace App\Http\Requests\Admin\Tryout;

use App\Models\Tryout\Session;
use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TryoutSessionAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects these routes.
    }

    public function rules(): array
    {
        $session = $this->route('session');
        $sessionId = $session instanceof Session ? $session->id : $session;
        $tenantId = TenantContext::id() ?? $this->user()?->tenant_id;
        $tenantScope = function ($q) use ($tenantId): void {
            $tenantId !== null
                ? $q->where('tenant_id', $tenantId)
                : $q->whereNull('tenant_id');
        };

        return [
            'kode_sesi' => ['required', 'string', 'max:50', Rule::unique('tryout_sessions', 'kode_sesi')->ignore($sessionId)->where($tenantScope)],
            'nama_sesi' => ['required', 'string', 'max:255'],
            'package_id' => ['required', Rule::exists('tryout_packages', 'id')->where($tenantScope)],
            'semester_id' => ['nullable', Rule::exists('semesters', 'id')->where($tenantScope)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:1'],
            'instruksi' => ['nullable', 'string'],
        ];
    }
}
