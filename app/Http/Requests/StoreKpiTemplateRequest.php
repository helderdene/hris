<?php

namespace App\Http\Requests;

use App\Models\KpiTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKpiTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique(KpiTemplate::class, 'code')],
            'description' => ['nullable', 'string'],
            'metric_unit' => ['required', 'string', 'max:50'],
            'default_target' => ['nullable', 'numeric', 'min:0'],
            'default_weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
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
            'name.required' => 'The KPI template name is required.',
            'name.max' => 'The KPI template name must not exceed 255 characters.',
            'code.required' => 'The KPI code is required.',
            'code.unique' => 'This KPI code is already in use.',
            'code.max' => 'The KPI code must not exceed 50 characters.',
            'metric_unit.required' => 'The metric unit is required.',
            'default_target.min' => 'The default target must be a positive number.',
            'default_weight.min' => 'The default weight must be a positive number.',
            'default_weight.max' => 'The default weight must not exceed 10.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('default_weight')) {
            $this->merge(['default_weight' => 1.00]);
        }

        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
