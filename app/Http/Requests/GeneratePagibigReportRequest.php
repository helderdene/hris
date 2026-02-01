<?php

namespace App\Http\Requests;

use App\Enums\PagibigReportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validates Pag-IBIG report generation requests.
 *
 * All Pag-IBIG reports are monthly, so month is always required.
 */
class GeneratePagibigReportRequest extends FormRequest
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
            'report_type' => ['required', Rule::enum(PagibigReportType::class)],
            'year' => ['required', 'integer', 'min:2020', "max:{$currentYear}"],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
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
            'month.required' => 'Please select a month.',
            'month.min' => 'Invalid month.',
            'month.max' => 'Invalid month.',
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
     * Get the validated report type as enum.
     */
    public function getReportType(): PagibigReportType
    {
        return PagibigReportType::from($this->validated('report_type'));
    }
}
