<?php

namespace App\Http\Requests;

use App\Enums\PhilhealthReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validates PhilHealth report generation requests.
 *
 * Handles validation for different report types:
 * - RF1, ER2: require month
 * - MDR: supports custom date range
 */
class GeneratePhilhealthReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('can-manage-organization');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currentYear = (int) now()->format('Y');

        return [
            'report_type' => ['required', Rule::enum(PhilhealthReportType::class)],
            'year' => ['required', 'integer', 'min:2020', "max:{$currentYear}"],
            'month' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
                Rule::requiredIf(fn () => $this->requiresMonth()),
            ],
            'start_date' => [
                'nullable',
                'date',
                'required_with:end_date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
                'required_with:start_date',
            ],
            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'report_type.required' => 'Please select a report type.',
            'report_type.enum' => 'Invalid report type selected.',
            'year.required' => 'Please select a year.',
            'year.min' => 'Year must be 2020 or later.',
            'year.max' => 'Year cannot be in the future.',
            'month.required' => 'This report type requires a month selection.',
            'month.min' => 'Invalid month.',
            'month.max' => 'Invalid month.',
            'start_date.required_with' => 'Start date is required when end date is provided.',
            'end_date.required_with' => 'End date is required when start date is provided.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'format.required' => 'Please select an output format.',
            'format.in' => 'Invalid output format. Supported formats: Excel, PDF.',
        ];
    }

    /**
     * Get custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'report_type' => 'report type',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'department_ids' => 'departments',
        ];
    }

    /**
     * Check if the selected report type requires a month.
     */
    protected function requiresMonth(): bool
    {
        $type = PhilhealthReportType::tryFrom($this->input('report_type'));

        if (! $type) {
            return false;
        }

        // MDR supports date range, so month is not required if dates provided
        if ($type === PhilhealthReportType::Mdr && $this->has('start_date')) {
            return false;
        }

        return true;
    }

    /**
     * Get the validated report type as enum.
     */
    public function getReportType(): PhilhealthReportType
    {
        return PhilhealthReportType::from($this->validated('report_type'));
    }
}
