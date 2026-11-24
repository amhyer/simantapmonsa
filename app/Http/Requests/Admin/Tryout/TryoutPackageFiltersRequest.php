<?php

namespace App\Http\Requests\Admin\Tryout;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TryoutPackageFiltersRequest extends FormRequest
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
            'category_id' => ['nullable', 'integer', 'exists:tryout_categories,id'],
            'jenjang' => ['nullable', 'in:SD,SMP,SMA,SMK'],
            'tipe_ujian' => ['nullable', 'in:USBN,UNBK,AKM'],
            'mata_pelajaran_id' => ['nullable', 'integer', Rule::exists('mata_pelajaran', 'id')->where($tenantScope)],
            'tahun' => ['nullable', 'integer', 'min:2020', 'max:2030'],
            'aktif' => ['nullable', 'in:true,false'],
            'search' => ['nullable', 'string', 'max:150'],
        ];
    }
}
