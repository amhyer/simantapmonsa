<?php

namespace App\Http\Requests\Cbt;

use Illuminate\Foundation\Http\FormRequest;

class StoreCbtQuestionBankRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bank = $this->route('bank');

        return $bank === null || (bool) $this->user()?->can('update', $bank);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:100'],
        ];
    }
}
