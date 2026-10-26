<?php

namespace App\Http\Requests\Admin\Tryout;

use App\Models\Tryout\Session;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TryoutSessionAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects these routes.
    }

    public function rules(): array
    {
        $session = $this->route('session');
        $sessionId = $session instanceof Session ? $session->id : $session;

        return [
            'kode_sesi' => ['required', 'string', 'max:50', Rule::unique('tryout_sessions', 'kode_sesi')->ignore($sessionId)],
            'nama_sesi' => ['required', 'string', 'max:255'],
            'package_id' => ['required', 'exists:tryout_packages,id'],
            'semester_id' => ['nullable', 'exists:semesters,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after:tanggal_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:1'],
            'instruksi' => ['nullable', 'string'],
        ];
    }
}
