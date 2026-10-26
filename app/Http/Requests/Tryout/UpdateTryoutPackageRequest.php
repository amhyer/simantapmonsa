<?php

namespace App\Http\Requests\Tryout;

use App\Models\Tryout\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTryoutPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route role middleware controls package management.
    }

    public function rules(): array
    {
        $package = $this->route('package');
        $packageId = $package instanceof Package ? $package->id : $package;

        return [
            'category_id' => ['required', 'exists:tryout_categories,id'],
            'kode_paket' => ['required', 'string', 'max:50', Rule::unique('tryout_packages', 'kode_paket')->ignore($packageId)],
            'nama_paket' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'jenjang' => ['required', 'in:SD,SMP,SMA,SMK,UMUM'],
            'tahun_paket' => ['required', 'integer', 'min:2020', 'max:2030'],
            'durasi_total_menit' => ['required', 'integer', 'min:1'],
            'passing_grade' => ['required', 'numeric', 'min:0', 'max:100'],
            'standar_nilai' => ['nullable', 'array'],
            'aktif' => ['boolean'],
        ];
    }
}
