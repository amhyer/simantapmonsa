<?php

namespace App\Http\Requests\Tryout;

use App\Modules\Tenant\Contexts\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTryoutPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route role middleware controls package management.
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
            'category_id' => ['required', 'exists:tryout_categories,id'],
            'kode_paket' => ['required', 'string', 'max:50', Rule::unique('tryout_packages', 'kode_paket')->where($tenantScope)],
            'nama_paket' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'jenjang' => ['required', 'in:SD,SMP,SMA,SMK,UMUM'],
            'tahun_paket' => ['required', 'integer', 'min:2020', 'max:2030'],
            'durasi_total_menit' => ['required', 'integer', 'min:1'],
            'passing_grade' => ['required', 'numeric', 'min:0', 'max:100'],
            'standar_nilai' => ['nullable', 'array'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.mata_pelajaran_id' => ['required', Rule::exists('mata_pelajaran', 'id')->where($tenantScope)],
            'subjects.*.bobot' => ['required', 'numeric', 'min:0', 'max:10'],
            'subjects.*.jumlah_soal' => ['required', 'integer', 'min:1'],
            'subjects.*.durasi_menit' => ['required', 'integer', 'min:1'],
            'subjects.*.passing_grade_mapel' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
