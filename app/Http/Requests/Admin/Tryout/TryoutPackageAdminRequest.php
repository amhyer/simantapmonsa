<?php

namespace App\Http\Requests\Admin\Tryout;

use App\Models\Tryout\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TryoutPackageAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects these routes.
    }

    public function rules(): array
    {
        $package = $this->route('package');
        $packageId = $package instanceof Package ? $package->id : $package;

        return [
            'category_id' => ['nullable', 'exists:tryout_categories,id'],
            'kode_paket' => ['required', 'string', 'max:50', Rule::unique('tryout_packages', 'kode_paket')->ignore($packageId)],
            'nama_paket' => ['required', 'string', 'max:255'],
            'jenjang' => ['required', 'in:SD,SMP,SMA,SMK'],
            'tipe_ujian' => ['required', 'in:USBN,UNBK,AKM'],
            'tahun_paket' => ['required', 'integer', 'min:2020', 'max:2030'],
            'mata_pelajaran_id' => ['nullable', 'exists:mata_pelajaran,id'],
            'total_soal' => ['required', 'integer', 'min:1'],
            'waktu_menit' => ['required', 'integer', 'min:1'],
            'kkm' => ['required', 'numeric', 'min:0', 'max:100'],
            'deskripsi' => ['nullable', 'string'],
        ];
    }
}
