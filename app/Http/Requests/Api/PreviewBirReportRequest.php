<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBirReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string'],
            'year' => ['required', 'integer'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer'],
            'schedule' => ['nullable', 'string', 'in:7.1,7.2,7.3'],
        ];
    }
}
