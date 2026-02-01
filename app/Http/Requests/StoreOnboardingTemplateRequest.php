<?php

namespace App\Http\Requests;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingTemplateRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.category' => ['required', Rule::enum(OnboardingCategory::class)],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.assigned_role' => ['required', Rule::enum(OnboardingAssignedRole::class)],
            'items.*.is_required' => ['boolean'],
            'items.*.sort_order' => ['integer', 'min:0'],
            'items.*.due_days_offset' => ['integer', 'min:-30', 'max:90'],
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
            'name.required' => 'Please provide a template name.',
            'items.*.category.required' => 'Each item must have a category.',
            'items.*.name.required' => 'Each item must have a name.',
            'items.*.assigned_role.required' => 'Each item must be assigned to a role.',
        ];
    }
}
