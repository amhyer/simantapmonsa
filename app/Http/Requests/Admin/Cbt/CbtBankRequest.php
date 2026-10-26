<?php

namespace App\Http\Requests\Admin\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class CbtBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects these routes.
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:100'],
            'guru_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
