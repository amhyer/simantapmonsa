<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCbtAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $result = $this->route('result');

        return $result !== null && (bool) $this->user()?->can('answer', $result);
    }

    public function rules(): array
    {
        return [
            'answers' => ['sometimes', 'array', 'max:200'],
            'answers.*' => ['required', 'in:A,B,C,D'],
        ];
    }
}
