<?php

namespace App\Http\Requests\Admin\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class CbtSessionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin role middleware protects this route.
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:published,running,completed'],
        ];
    }
}
