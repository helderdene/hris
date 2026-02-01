<?php

namespace App\Http\Requests;

use App\Models\CourseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseCategoryRequest extends FormRequest
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
        $category = $this->route('category');
        $categoryId = $category instanceof CourseCategory ? $category->id : $category;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(CourseCategory::class, 'code')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(CourseCategory::class, 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($categoryId) {
                    if ($value === null) {
                        return;
                    }

                    if ((int) $value === (int) $categoryId) {
                        $fail('A category cannot be its own parent.');

                        return;
                    }

                    $category = CourseCategory::find($categoryId);
                    if ($category && ! $category->validateNotCircularReference((int) $value)) {
                        $fail('This would create a circular reference in the category hierarchy.');
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
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
            'name.required' => 'The category name is required.',
            'code.required' => 'The category code is required.',
            'code.unique' => 'This category code is already in use.',
            'parent_id.exists' => 'The selected parent category does not exist.',
        ];
    }
}
