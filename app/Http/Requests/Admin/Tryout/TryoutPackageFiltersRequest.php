<?php

namespace App\Http\Requests\Admin\Tryout;

use Illuminate\Foundation\Http\FormRequest;

class TryoutPackageFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects this route.
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:tryout_categories,id'],
            'jenjang' => ['nullable', 'in:SD,SMP,SMA,SMK'],
            'tipe_ujian' => ['nullable', 'in:USBN,UNBK,AKM'],
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'tahun' => ['nullable', 'integer', 'min:2020', 'max:2030'],
            'aktif' => ['nullable', 'in:true,false'],
            'search' => ['nullable', 'string', 'max:150'],
        ];
    }
}
