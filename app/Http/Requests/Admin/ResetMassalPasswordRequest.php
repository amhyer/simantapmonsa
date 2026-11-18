<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResetMassalPasswordRequest extends FormRequest
{
    /** @var array<int, string> */
    public const ALLOWED_ROLES = ['siswa', 'ortu', 'guru', 'tendik', 'kepsek'];

    public function authorize(): bool
    {
        return $this->user()?->peran === 'admin';
    }

    public function rules(): array
    {
        $isExecute = $this->routeIs('admin.users.reset-massal');

        return [
            'peran' => ['required', 'array', 'min:1'],
            'peran.*' => ['required', 'string', Rule::in(self::ALLOWED_ROLES)],
            'kelas' => ['nullable', 'string', 'max:50'],
            'hanya_belum_login' => ['nullable', 'boolean'],
            'sertakan_sudah_diterbitkan' => ['nullable', 'boolean'],
            'password_admin' => $isExecute
                ? ['required', 'string', 'current_password']
                : ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'peran.required' => 'Pilih minimal satu peran target.',
            'peran.*.in' => 'Peran yang dipilih tidak diizinkan untuk reset massal.',
            'password_admin.required' => 'Masukkan ulang kata sandi administrator untuk konfirmasi.',
            'password_admin.current_password' => 'Kata sandi administrator tidak cocok.',
        ];
    }
}
