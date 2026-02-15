<?php

namespace App\Http\Requests;

use App\Enums\TenantUserRole;
use App\Models\Employee;
use App\Models\TenantUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class InviteUserRequest extends FormRequest
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
        $tenant = tenant();

        return [
            'email' => [
                'required',
                'email',
                'max:255',
                // Check email is not already a member of this tenant
                function (string $attribute, mixed $value, \Closure $fail) use ($tenant) {
                    if ($tenant === null) {
                        return;
                    }

                    $existsInTenant = TenantUser::query()
                        ->where('tenant_id', $tenant->id)
                        ->whereHas('user', function ($query) use ($value) {
                            $query->where('email', $value);
                        })
                        ->exists();

                    if ($existsInTenant) {
                        $fail('This email address is already a member of this organization.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', new Enum(TenantUserRole::class)],
            'employee_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null) {
                        return;
                    }

                    $employee = Employee::find($value);

                    if ($employee === null) {
                        $fail('The selected employee is invalid.');

                        return;
                    }

                    if ($employee->user_id !== null) {
                        $fail('This employee is already linked to a user account.');
                    }
                },
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
            'email.required' => 'The email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'name.required' => 'The user name is required.',
            'role.required' => 'Please select a role for the user.',
            'role.Illuminate\Validation\Rules\Enum' => 'The selected role is invalid.',
        ];
    }
}
