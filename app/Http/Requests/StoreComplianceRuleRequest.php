<?php

namespace App\Http\Requests;

use App\Enums\ComplianceRuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplianceRuleRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:1000'],
            'rule_type' => ['required', 'string', Rule::in(ComplianceRuleType::values())],
            'conditions' => ['required_unless:rule_type,all_employees', 'array'],
            'conditions.department_ids' => ['nullable', 'array'],
            'conditions.department_ids.*' => ['integer', 'exists:departments,id'],
            'conditions.position_ids' => ['nullable', 'array'],
            'conditions.position_ids.*' => ['integer', 'exists:positions,id'],
            'conditions.job_levels' => ['nullable', 'array'],
            'conditions.job_levels.*' => ['string'],
            'conditions.work_location_ids' => ['nullable', 'array'],
            'conditions.work_location_ids.*' => ['integer', 'exists:work_locations,id'],
            'conditions.employment_types' => ['nullable', 'array'],
            'conditions.employment_types.*' => ['string'],
            'days_to_complete_override' => ['nullable', 'integer', 'min:1', 'max:365'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'apply_to_new_hires' => ['boolean'],
            'apply_to_existing' => ['boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
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
            'name.required' => 'The rule name is required.',
            'rule_type.required' => 'The rule type is required.',
            'rule_type.in' => 'The selected rule type is invalid.',
            'conditions.required_unless' => 'Conditions are required for this rule type.',
            'effective_until.after' => 'The effective until date must be after the effective from date.',
        ];
    }
}
