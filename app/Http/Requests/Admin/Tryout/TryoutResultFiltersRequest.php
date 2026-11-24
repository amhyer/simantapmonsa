<?php

namespace App\Http\Requests\Admin\Tryout;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TryoutResultFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects this route.
    }

    public function rules(): array
    {
        $tenantId = TenantContext::id() ?? $this->user()?->tenant_id;
        $tenantScope = fn ($q) => $tenantId !== null
            ? $q->where('tenant_id', $tenantId)
            : $q->whereNull('tenant_id');

        return [
            'lulus' => ['nullable', 'in:true,false'],
            'package_id' => ['nullable', 'integer', Rule::exists('tryout_packages', 'id')->where($tenantScope)],
            'session_id' => ['nullable', 'integer', Rule::exists('tryout_sessions', 'id')->where($tenantScope)],
            'tanggal_from' => ['nullable', 'date'],
            'tanggal_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:150'],
        ];
    }
}
