<?php

namespace App\Http\Requests\Tryout;

use Illuminate\Foundation\Http\FormRequest;

class StoreTryoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route role middleware controls who can create sessions.
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'exists:tryout_packages,id'],
            'semester_id' => ['required', 'exists:semesters,id'],
            'nama_sesi' => ['required', 'string', 'max:255'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'rombel_id' => ['nullable', 'exists:rombel,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
        ];
    }
}
