<?php

namespace App\Http\Requests\Admin;

use App\Enums\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCustomPlanRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'modules' => ['sometimes', 'required', 'array', 'min:1'],
            'modules.*' => ['required', 'string', Rule::in(Module::values())],
            'limits' => ['sometimes', 'required', 'array'],
            'limits.max_employees' => ['required_with:limits', 'integer', 'min:-1'],
            'limits.max_admin_users' => ['required_with:limits', 'integer', 'min:-1'],
            'limits.max_departments' => ['required_with:limits', 'integer', 'min:-1'],
            'limits.max_biometric_devices' => ['required_with:limits', 'integer', 'min:-1'],
            'limits.storage_gb' => ['required_with:limits', 'integer', 'min:-1'],
            'limits.api_access' => ['required_with:limits', 'boolean'],
            'prices' => ['sometimes', 'required', 'array', 'min:1'],
            'prices.*.billing_interval' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'prices.*.price_per_unit' => ['required', 'numeric', 'min:0'],
        ];
    }
}
