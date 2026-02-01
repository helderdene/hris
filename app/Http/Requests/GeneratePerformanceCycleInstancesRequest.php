<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePerformanceCycleInstancesRequest extends FormRequest
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
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'overwrite' => ['boolean'],
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
            'year.required' => 'The year is required.',
            'year.min' => 'The year must be 2020 or later.',
            'year.max' => 'The year must be 2100 or earlier.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('overwrite')) {
            $this->merge(['overwrite' => false]);
        }
    }
}
