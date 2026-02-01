<?php

namespace App\Http\Requests;

use App\Enums\CompetencyCategory;
use App\Models\Competency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetencyRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique(Competency::class, 'code')],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', Rule::enum(CompetencyCategory::class)],
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
            'name.required' => 'The competency name is required.',
            'name.max' => 'The competency name must not exceed 255 characters.',
            'code.required' => 'The competency code is required.',
            'code.unique' => 'This competency code is already in use.',
            'code.max' => 'The competency code must not exceed 50 characters.',
            'category.enum' => 'The selected category is invalid.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
