<?php

namespace App\Http\Requests;

use App\Enums\SssReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validates SSS report generation requests.
 *
 * Handles validation for different report types with their specific period requirements:
 * - R3, SBR, ECL: require month
 * - R5: requires quarter
 */
class GenerateSssReportRequest extends FormRequest
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
            'report_type' => ['required', Rule::enum(SssReportType::class)],
            'year' => ['required', 'integer', 'min:2020', "max:{$currentYear}"],
            'month' => [
                'nullable',
                'integer',
                'min:1',
                'max:12',
                Rule::requiredIf(fn () => $this->requiresMonth()),
            ],
            'quarter' => [
                'nullable',
                'integer',
                'min:1',
                'max:4',
                Rule::requiredIf(fn () => $this->requiresQuarter()),
            ],
            'format' => ['required', Rule::in(['xlsx', 'pdf', 'csv'])],
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
            'quarter.required' => 'This report type requires a quarter selection.',
            'quarter.min' => 'Invalid quarter.',
            'quarter.max' => 'Invalid quarter.',
            'format.required' => 'Please select an output format.',
            'format.in' => 'Invalid output format. Supported formats: Excel, PDF, CSV.',
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
            'department_ids' => 'departments',
        ];
    }

    /**
     * Check if the selected report type requires a month.
     */
    protected function requiresMonth(): bool
    {
        $type = SssReportType::tryFrom($this->input('report_type'));

        return $type && $type->isMonthlyReport();
    }

    /**
     * Check if the selected report type requires a quarter.
     */
    protected function requiresQuarter(): bool
    {
        $type = SssReportType::tryFrom($this->input('report_type'));

        return $type && $type->isQuarterlyReport();
    }

    /**
     * Get the validated report type as enum.
     */
    public function getReportType(): SssReportType
    {
        return SssReportType::from($this->validated('report_type'));
    }
}
