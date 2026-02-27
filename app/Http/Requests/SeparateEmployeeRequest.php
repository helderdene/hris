<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SeparateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $separationStatuses = collect(EmploymentStatus::cases())
            ->filter(fn (EmploymentStatus $status) => $status !== EmploymentStatus::Active)
            ->pluck('value')
            ->all();

        return [
            'employment_status' => ['required', 'string', Rule::in($separationStatuses)],
            'termination_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employment_status.required' => 'The employment status is required.',
            'employment_status.in' => 'The selected employment status is invalid.',
            'termination_date.required' => 'The termination date is required.',
            'termination_date.date' => 'Please enter a valid termination date.',
            'remarks.max' => 'The remarks may not exceed 500 characters.',
        ];
    }
}
