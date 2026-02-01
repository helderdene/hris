<?php

namespace App\Http\Requests;

use App\Enums\PerformanceCycleInstanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePerformanceCycleInstanceRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'year' => ['sometimes', 'required', 'integer', 'min:2020', 'max:2100'],
            'instance_number' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after:start_date'],
            'status' => ['sometimes', new Enum(PerformanceCycleInstanceStatus::class)],
            'notes' => ['nullable', 'string'],
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
            'name.required' => 'The instance name is required.',
            'year.required' => 'The year is required.',
            'year.min' => 'The year must be 2020 or later.',
            'year.max' => 'The year must be 2100 or earlier.',
            'instance_number.required' => 'The instance number is required.',
            'instance_number.min' => 'The instance number must be at least 1.',
            'start_date.required' => 'The start date is required.',
            'end_date.required' => 'The end date is required.',
            'end_date.after' => 'The end date must be after the start date.',
        ];
    }
}
