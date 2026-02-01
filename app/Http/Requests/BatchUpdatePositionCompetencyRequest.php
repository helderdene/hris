<?php

namespace App\Http\Requests;

use App\Enums\JobLevel;
use App\Models\Competency;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchUpdatePositionCompetencyRequest extends FormRequest
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
            'position_id' => ['required', 'integer', Rule::exists(Position::class, 'id')],
            'job_level' => ['required', 'string', Rule::enum(JobLevel::class)],
            'assignments' => ['required', 'array'],
            'assignments.*.competency_id' => ['required', 'integer', Rule::exists(Competency::class, 'id')],
            'assignments.*.required_proficiency_level' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'assignments.*.is_mandatory' => ['sometimes', 'boolean'],
            'assignments.*.weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'assignments.*.notes' => ['nullable', 'string'],
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
            'position_id.required' => 'A position must be selected.',
            'position_id.exists' => 'The selected position does not exist.',
            'job_level.required' => 'A job level must be selected.',
            'job_level.enum' => 'The selected job level is invalid.',
            'assignments.required' => 'At least one competency assignment is required.',
            'assignments.array' => 'Assignments must be an array.',
            'assignments.*.competency_id.required' => 'Each assignment must have a competency ID.',
            'assignments.*.competency_id.exists' => 'One or more competencies do not exist.',
            'assignments.*.required_proficiency_level.min' => 'Proficiency level must be at least 1.',
            'assignments.*.required_proficiency_level.max' => 'Proficiency level must not exceed 5.',
            'assignments.*.weight.min' => 'Weight must be a positive number.',
            'assignments.*.weight.max' => 'Weight must not exceed 10.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate competency IDs in the assignments array
            if ($this->has('assignments')) {
                $competencyIds = collect($this->input('assignments'))
                    ->pluck('competency_id')
                    ->filter()
                    ->toArray();

                if (count($competencyIds) !== count(array_unique($competencyIds))) {
                    $validator->errors()->add(
                        'assignments',
                        'Duplicate competencies are not allowed in the same batch.'
                    );
                }
            }
        });
    }
}
