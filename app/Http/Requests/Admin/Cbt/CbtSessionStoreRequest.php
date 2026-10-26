<?php

namespace App\Http\Requests\Admin\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class CbtSessionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects this route.
    }

    public function rules(): array
    {
        return [
            'guru_id' => ['required', 'integer', 'exists:users,id'],
            'bank_id' => ['required', 'integer', 'exists:cbt_question_banks,id'],
            'title' => ['required', 'string', 'max:200'],
            'kelas' => ['required', 'string', 'max:50'],
            'opens_at' => ['required', 'date'],
            'closes_at' => ['required', 'date', 'after:opens_at'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
        ];
    }
}
