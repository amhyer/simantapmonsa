<?php

namespace App\Http\Requests\Tryout;

use Illuminate\Foundation\Http\FormRequest;

class StoreTryoutPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route role middleware controls package management.
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:tryout_categories,id'],
            'kode_paket' => ['required', 'string', 'max:50', 'unique:tryout_packages,kode_paket'],
            'nama_paket' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'jenjang' => ['required', 'in:SD,SMP,SMA,SMK,UMUM'],
            'tahun_paket' => ['required', 'integer', 'min:2020', 'max:2030'],
            'durasi_total_menit' => ['required', 'integer', 'min:1'],
            'passing_grade' => ['required', 'numeric', 'min:0', 'max:100'],
            'standar_nilai' => ['nullable', 'array'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'subjects.*.bobot' => ['required', 'numeric', 'min:0', 'max:10'],
            'subjects.*.jumlah_soal' => ['required', 'integer', 'min:1'],
            'subjects.*.durasi_menit' => ['required', 'integer', 'min:1'],
            'subjects.*.passing_grade_mapel' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
