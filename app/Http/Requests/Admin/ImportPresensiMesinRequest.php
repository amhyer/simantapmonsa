<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportPresensiMesinRequest extends FormRequest
{
    private const EKSTENSI_DIIZINKAN = ['xls', 'xlsx', 'csv', 'txt'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $ext = strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($ext, self::EKSTENSI_DIIZINKAN, true)) {
                        $fail('Format file harus XLS, XLSX, CSV, atau TXT.');
                    }
                },
            ],
        ];
    }
}
