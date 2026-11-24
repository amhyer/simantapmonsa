<?php

namespace App\Http\Requests\Tryout;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTryoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route role middleware controls who can create sessions.
    }

    public function rules(): array
    {
        $tenantId = TenantContext::id() ?? $this->user()?->tenant_id;
        $tenantScope = function ($q) use ($tenantId): void {
            if ($tenantId !== null) {
                $q->where('tenant_id', $tenantId);
            }
        };

        return [
            'package_id' => ['required', Rule::exists('tryout_packages', 'id')->where($tenantScope)],
            'semester_id' => ['required', Rule::exists('semesters', 'id')->where($tenantScope)],
            'nama_sesi' => ['required', 'string', 'max:255'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'rombel_id' => ['nullable', Rule::exists('rombel', 'id')->where($tenantScope)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ];
    }
}
