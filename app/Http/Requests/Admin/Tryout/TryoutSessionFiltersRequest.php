<?php

namespace App\Http\Requests\Admin\Tryout;

use Illuminate\Foundation\Http\FormRequest;

class TryoutSessionFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects this route.
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:draft,published,ongoing,completed'],
            'package_id' => ['nullable', 'integer', 'exists:tryout_packages,id'],
            'tanggal_mulai_from' => ['nullable', 'date'],
            'tanggal_mulai_to' => ['nullable', 'date'],
        ];
    }
}
