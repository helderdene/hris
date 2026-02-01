<?php

namespace App\Http\Requests;

use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Enums\CourseProviderType;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
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
        $course = $this->route('course');
        $courseId = $course instanceof Course ? $course->id : $course;

        return [
            'title' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Course::class, 'code')->ignore($courseId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'delivery_method' => ['required', 'string', Rule::in(CourseDeliveryMethod::values())],
            'provider_type' => ['required', 'string', Rule::in(CourseProviderType::values())],
            'provider_name' => ['nullable', 'string', 'max:255'],
            'duration_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'duration_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['nullable', 'string', Rule::in(CourseStatus::values())],
            'level' => ['nullable', 'string', Rule::in(CourseLevel::values())],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'learning_objectives' => ['nullable', 'array'],
            'learning_objectives.*' => ['string', 'max:500'],
            'syllabus' => ['nullable', 'string', 'max:10000'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', Rule::exists(CourseCategory::class, 'id')],
            'prerequisites' => ['nullable', 'array'],
            'prerequisites.*.id' => [
                'required',
                'integer',
                Rule::exists(Course::class, 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($courseId) {
                    if ((int) $value === (int) $courseId) {
                        $fail('A course cannot be its own prerequisite.');
                    }
                },
            ],
            'prerequisites.*.is_mandatory' => ['nullable', 'boolean'],
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
            'title.required' => 'The course title is required.',
            'code.required' => 'The course code is required.',
            'code.unique' => 'This course code is already in use.',
            'delivery_method.required' => 'The delivery method is required.',
            'delivery_method.in' => 'The selected delivery method is invalid.',
            'provider_type.required' => 'The provider type is required.',
            'provider_type.in' => 'The selected provider type is invalid.',
            'status.in' => 'The selected status is invalid.',
            'level.in' => 'The selected level is invalid.',
            'category_ids.*.exists' => 'One or more selected categories do not exist.',
            'prerequisites.*.id.exists' => 'One or more selected prerequisites do not exist.',
        ];
    }
}
