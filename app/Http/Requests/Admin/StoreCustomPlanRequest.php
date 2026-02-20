<?php

namespace App\Http\Requests\Admin;

use App\Enums\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCustomPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('super-admin');
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
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['required', 'string', Rule::in(Module::values())],
            'limits' => ['required', 'array'],
            'limits.max_employees' => ['required', 'integer', 'min:-1'],
            'limits.max_admin_users' => ['required', 'integer', 'min:-1'],
            'limits.max_departments' => ['required', 'integer', 'min:-1'],
            'limits.max_biometric_devices' => ['required', 'integer', 'min:-1'],
            'limits.storage_gb' => ['required', 'integer', 'min:-1'],
            'limits.api_access' => ['required', 'boolean'],
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.billing_interval' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'prices.*.price_per_unit' => ['required', 'numeric', 'min:0'],
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
            'name.required' => 'Please enter a plan name.',
            'modules.required' => 'Please select at least one module.',
            'modules.min' => 'Please select at least one module.',
            'prices.required' => 'Please add at least one price.',
            'prices.min' => 'Please add at least one price.',
        ];
    }
}
