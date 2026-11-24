<?php

namespace App\Http\Requests\Admin\Tryout;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TryoutSessionFiltersRequest extends FormRequest
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
            'status' => ['nullable', 'in:draft,published,ongoing,completed'],
            'package_id' => ['nullable', 'integer', Rule::exists('tryout_packages', 'id')->where($tenantScope)],
            'tanggal_mulai_from' => ['nullable', 'date'],
            'tanggal_mulai_to' => ['nullable', 'date'],
        ];
    }
}
