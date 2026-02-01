<?php

namespace App\Http\Requests;

use App\Enums\JobLevel;
use App\Models\Competency;
use App\Models\Position;
use App\Models\PositionCompetency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionCompetencyRequest extends FormRequest
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
            'competency_id' => ['required', 'integer', Rule::exists(Competency::class, 'id')],
            'job_level' => ['required', 'string', Rule::enum(JobLevel::class)],
            'required_proficiency_level' => ['required', 'integer', 'min:1', 'max:5'],
            'is_mandatory' => ['boolean'],
            'weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'notes' => ['nullable', 'string'],
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
            'competency_id.required' => 'A competency must be selected.',
            'competency_id.exists' => 'The selected competency does not exist.',
            'job_level.required' => 'A job level must be selected.',
            'job_level.enum' => 'The selected job level is invalid.',
            'required_proficiency_level.required' => 'A proficiency level must be selected.',
            'required_proficiency_level.min' => 'Proficiency level must be at least 1.',
            'required_proficiency_level.max' => 'Proficiency level must not exceed 5.',
            'weight.min' => 'Weight must be a positive number.',
            'weight.max' => 'Weight must not exceed 10.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('is_mandatory')) {
            $this->merge(['is_mandatory' => true]);
        }

        if (! $this->has('weight')) {
            $this->merge(['weight' => 1.00]);
        }

        if (! $this->has('required_proficiency_level')) {
            $this->merge(['required_proficiency_level' => 3]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate assignment
            if ($this->filled(['position_id', 'competency_id', 'job_level'])) {
                $exists = PositionCompetency::where('position_id', $this->input('position_id'))
                    ->where('competency_id', $this->input('competency_id'))
                    ->where('job_level', $this->input('job_level'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'competency_id',
                        'This competency is already assigned to this position and job level.'
                    );
                }
            }
        });
    }
}
