<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StoreCbtIntegrityViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $result = $this->route('result');

        return $result !== null && (bool) $this->user()?->can('recordViolation', $result);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:tab_hidden,fullscreen_exit'],
        ];
    }
}
