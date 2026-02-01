<?php

namespace App\Http\Requests;

use App\Enums\JobLevel;
use App\Models\Competency;
use App\Models\Position;
use App\Models\PositionCompetency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionCompetencyRequest extends FormRequest
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
            'position_id' => ['sometimes', 'required', 'integer', Rule::exists(Position::class, 'id')],
            'competency_id' => ['sometimes', 'required', 'integer', Rule::exists(Competency::class, 'id')],
            'job_level' => ['sometimes', 'required', 'string', Rule::enum(JobLevel::class)],
            'required_proficiency_level' => ['sometimes', 'required', 'integer', 'min:1', 'max:5'],
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
            'position_id.exists' => 'The selected position does not exist.',
            'competency_id.exists' => 'The selected competency does not exist.',
            'job_level.enum' => 'The selected job level is invalid.',
            'required_proficiency_level.min' => 'Proficiency level must be at least 1.',
            'required_proficiency_level.max' => 'Proficiency level must not exceed 5.',
            'weight.min' => 'Weight must be a positive number.',
            'weight.max' => 'Weight must not exceed 10.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate assignment if updating position, competency, or job level
            if ($this->hasAny(['position_id', 'competency_id', 'job_level'])) {
                $positionCompetency = $this->route('positionCompetency');
                $currentId = $positionCompetency instanceof PositionCompetency
                    ? $positionCompetency->id
                    : $positionCompetency;

                $positionId = $this->input('position_id', $positionCompetency->position_id ?? null);
                $competencyId = $this->input('competency_id', $positionCompetency->competency_id ?? null);
                $jobLevel = $this->input('job_level', $positionCompetency->job_level ?? null);

                $exists = PositionCompetency::where('position_id', $positionId)
                    ->where('competency_id', $competencyId)
                    ->where('job_level', $jobLevel)
                    ->where('id', '!=', $currentId)
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
