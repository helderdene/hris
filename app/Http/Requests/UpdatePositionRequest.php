<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Models\Position;
use App\Models\SalaryGrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePositionRequest extends FormRequest
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
        $position = $this->route('position');
        $positionId = $position instanceof Position ? $position->id : $position;

        return [
            'title' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Position::class, 'code')->ignore($positionId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'salary_grade_id' => [
                'nullable',
                'integer',
                Rule::exists(SalaryGrade::class, 'id'),
            ],
            'job_level' => ['required', new Enum(JobLevel::class)],
            'employment_type' => ['required', new Enum(EmploymentType::class)],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
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
            'title.required' => 'The position title is required.',
            'code.required' => 'The position code is required.',
            'code.unique' => 'This position code is already in use.',
            'salary_grade_id.exists' => 'The selected salary grade does not exist.',
            'job_level.required' => 'The job level is required.',
            'job_level.Illuminate\Validation\Rules\Enum' => 'The selected job level is invalid.',
            'employment_type.required' => 'The employment type is required.',
            'employment_type.Illuminate\Validation\Rules\Enum' => 'The selected employment type is invalid.',
            'status.required' => 'The position status is required.',
            'status.in' => 'The status must be either active or inactive.',
        ];
    }
}
