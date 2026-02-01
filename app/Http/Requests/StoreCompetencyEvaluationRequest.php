<?php

namespace App\Http\Requests;

use App\Models\CompetencyEvaluation;
use App\Models\PerformanceCycleParticipant;
use App\Models\PositionCompetency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompetencyEvaluationRequest extends FormRequest
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
            'performance_cycle_participant_id' => [
                'required',
                'integer',
                Rule::exists(PerformanceCycleParticipant::class, 'id'),
            ],
            'position_competency_id' => [
                'required',
                'integer',
                Rule::exists(PositionCompetency::class, 'id'),
            ],
            'self_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'self_comments' => ['nullable', 'string'],
            'manager_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'manager_comments' => ['nullable', 'string'],
            'final_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['string'],
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
            'performance_cycle_participant_id.required' => 'A participant must be selected.',
            'performance_cycle_participant_id.exists' => 'The selected participant does not exist.',
            'position_competency_id.required' => 'A position competency must be selected.',
            'position_competency_id.exists' => 'The selected position competency does not exist.',
            'self_rating.min' => 'Self-rating must be at least 1.',
            'self_rating.max' => 'Self-rating must not exceed 5.',
            'manager_rating.min' => 'Manager rating must be at least 1.',
            'manager_rating.max' => 'Manager rating must not exceed 5.',
            'final_rating.min' => 'Final rating must be at least 1.',
            'final_rating.max' => 'Final rating must not exceed 5.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate evaluation
            if ($this->filled(['performance_cycle_participant_id', 'position_competency_id'])) {
                $exists = CompetencyEvaluation::where(
                    'performance_cycle_participant_id',
                    $this->input('performance_cycle_participant_id')
                )
                    ->where('position_competency_id', $this->input('position_competency_id'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'position_competency_id',
                        'An evaluation already exists for this participant and competency.'
                    );
                }
            }
        });
    }
}
