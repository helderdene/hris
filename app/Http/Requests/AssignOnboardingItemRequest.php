<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignOnboardingItemRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
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
            'assigned_to.required' => 'Please select a user to assign this item to.',
            'assigned_to.exists' => 'The selected user does not exist.',
        ];
    }
}
