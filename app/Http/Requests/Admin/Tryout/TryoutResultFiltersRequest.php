<?php

namespace App\Http\Requests\Admin\Tryout;

use Illuminate\Foundation\Http\FormRequest;

class TryoutResultFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects this route.
    }

    public function rules(): array
    {
        return [
            'lulus' => ['nullable', 'in:true,false'],
            'package_id' => ['nullable', 'integer', 'exists:tryout_packages,id'],
            'session_id' => ['nullable', 'integer', 'exists:tryout_sessions,id'],
            'tanggal_from' => ['nullable', 'date'],
            'tanggal_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:150'],
        ];
    }
}
