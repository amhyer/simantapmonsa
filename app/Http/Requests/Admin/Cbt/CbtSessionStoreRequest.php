<?php

namespace App\Http\Requests\Admin\Cbt;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CbtSessionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects this route.
    }

    public function rules(): array
    {
        $tenantId = TenantContext::id() ?? $this->user()?->tenant_id;
        $tenantScope = function ($query) use ($tenantId): void {
            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            }
        };

        return [
            'guru_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($tenantId): void {
                    $query->where('peran', 'guru');
                    if ($tenantId !== null) {
                        $query->where('tenant_id', $tenantId);
                    }
                }),
            ],
            'bank_id' => ['required', 'integer', Rule::exists('cbt_question_banks', 'id')->where($tenantScope)],
            'title' => ['required', 'string', 'max:200'],
            'kelas' => ['required', 'string', 'max:50'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
        ];
    }
}
