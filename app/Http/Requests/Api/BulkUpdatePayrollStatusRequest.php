<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdatePayrollStatusRequest extends FormRequest
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
            'entry_ids' => ['required', 'array'],
            'entry_ids.*' => ['integer', 'exists:payroll_entries,id'],
            'status' => ['required', 'string'],
        ];
    }
}
