<?php

namespace App\Http\Requests;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRegistrationRequest extends FormRequest
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
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(Tenant::class, 'slug'),
            ],
            'business_info' => ['required', 'array'],
            'business_info.company_name' => ['required', 'string', 'max:255'],
            'business_info.address' => ['nullable', 'string', 'max:500'],
            'business_info.tin' => [
                'nullable',
                'string',
                'regex:/^\d{3}-\d{3}-\d{3}-\d{3}$/',
            ],
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
            'slug.regex' => 'The subdomain must contain only lowercase letters, numbers, and hyphens. It cannot start or end with a hyphen.',
            'slug.unique' => 'This subdomain is already taken. Please choose another one.',
            'business_info.tin.regex' => 'The TIN must be in the format XXX-XXX-XXX-XXX (Philippine TIN format).',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => 'subdomain',
            'business_info.company_name' => 'company name',
            'business_info.address' => 'company address',
            'business_info.tin' => 'TIN (Tax Identification Number)',
        ];
    }
}
