<?php

namespace App\Http\Requests;

use App\Enums\PerformanceCycleInstanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePerformanceCycleInstanceRequest extends FormRequest
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
            'performance_cycle_id' => ['required', 'integer', 'exists:tenant.performance_cycles,id'],
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'instance_number' => ['required', 'integer', 'min:1', 'max:12'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
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
            'performance_cycle_id.required' => 'A performance cycle must be selected.',
            'performance_cycle_id.exists' => 'The selected performance cycle does not exist.',
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

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge(['status' => PerformanceCycleInstanceStatus::Draft->value]);
        }
    }
}
