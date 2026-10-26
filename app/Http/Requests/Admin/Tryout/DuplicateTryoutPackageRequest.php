<?php

namespace App\Http\Requests\Admin\Tryout;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateTryoutPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects this route.
    }

    public function rules(): array
    {
        return [
            'tahun_paket' => ['required', 'integer', 'min:2020', 'max:2030'],
            'nama_paket' => ['required', 'string', 'max:255'],
        ];
    }
}
