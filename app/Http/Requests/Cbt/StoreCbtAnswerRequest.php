<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StoreCbtAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $result = $this->route('result');

        return $result !== null && (bool) $this->user()?->can('answer', $result);
    }

    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer', 'min:1'],
            'answer' => ['required', 'in:A,B,C,D'],
        ];
    }
}
