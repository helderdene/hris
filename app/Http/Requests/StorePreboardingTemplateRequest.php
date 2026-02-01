<?php

namespace App\Http\Requests;

use App\Enums\PreboardingItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreboardingTemplateRequest extends FormRequest
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
            'items.*.type' => ['required', Rule::enum(PreboardingItemType::class)],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.is_required' => ['boolean'],
            'items.*.sort_order' => ['integer', 'min:0'],
            'items.*.document_category_id' => ['nullable', 'integer', 'exists:document_categories,id'],
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
            'items.*.type.required' => 'Each item must have a type.',
            'items.*.name.required' => 'Each item must have a name.',
        ];
    }
}
