<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DownloadBulkPayslipRequest extends FormRequest
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
            'entry_ids' => ['nullable', 'array'],
            'entry_ids.*' => ['integer', 'exists:payroll_entries,id'],
            'format' => ['nullable', 'string', 'in:pdf,zip'],
        ];
    }
}
