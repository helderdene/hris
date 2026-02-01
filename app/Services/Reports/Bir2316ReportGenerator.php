<?php

namespace App\Services\Reports;

use App\Enums\PayrollEntryStatus;
use App\Models\Employee;
use App\Models\PayrollEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;

/**
 * Generator for BIR Form 2316 - Certificate of Compensation Payment/Tax Withheld.
 *
 * Generates per-employee certificates of annual compensation and tax withheld.
 * These certificates are given to employees for their personal income tax filing.
 */
class Bir2316ReportGenerator extends BaseBirReportGenerator
{
    public function __construct(
        protected Bir2316TemplateService $templateService
    ) {}

    public function getTitle(): string
    {
        return '2316 - Certificate of Compensation Payment/Tax Withheld';
    }

    public function getReportCode(): string
    {
        return '2316';
    }

    /**
     * @param  array<int>|null  $departmentIds
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    public function getData(
        int $year,
        ?int $month = null,
        ?int $quarter = null,
        ?array $departmentIds = null,
        ?int $limit = null,
        ?int $employeeId = null
    ): array {
        $query = PayrollEntry::query()
            ->whereHas('payrollPeriod', function ($q) use ($year) {
                $q->whereYear('cutoff_start', $year);
            })
            ->whereHas('employee', function ($q) {
                $q->whereNotNull('tin')
                    ->where('tin', '!=', '');
            })
            ->with([
                'employee:id,employee_number,tin,first_name,middle_name,last_name,suffix,department_id,date_of_birth,address,hire_date,termination_date',
                'employee.department:id,name',
                'employee.position:id,title',
            ])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid]);

        // Filter by specific employee for self-service
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $entries = $query->get();

        // Aggregate by employee for the entire year
        $aggregated = $entries->groupBy('employee_id')->map(function ($group) use ($year) {
            $first = $group->first();
            $employee = $first->employee;

            return $this->buildEmployeeCertificateData($employee, $group, $year);
        })->values();

        // Sort by last name
        $aggregated = $aggregated->sortBy('last_name')->values();

        if ($limit) {
            $aggregated = $aggregated->take($limit);
        }

        $totals = [
            'employee_count' => $aggregated->count(),
            'gross_compensation' => $aggregated->sum('gross_compensation'),
            'non_taxable_compensation' => $aggregated->sum('total_non_taxable'),
            'taxable_compensation' => $aggregated->sum('taxable_compensation'),
            'withholding_tax' => $aggregated->sum('withholding_tax'),
        ];

        return [
            'data' => $aggregated,
            'totals' => $totals,
        ];
    }

    /**
     * Get data for a single employee's 2316 certificate.
     *
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    public function getEmployeeData(int $employeeId, int $year): array
    {
        return $this->getData(
            year: $year,
            employeeId: $employeeId
        );
    }

    /**
     * Build certificate data structure for an employee.
     *
     * @param  Collection<int, PayrollEntry>  $entries
     */
    protected function buildEmployeeCertificateData(Employee $employee, Collection $entries, int $year): object
    {
        $grossCompensation = $entries->sum('gross_pay');
        $withholdingTax = $entries->sum('withholding_tax');

        // 13th month pay (typically tax-exempt up to PHP 90,000)
        $thirteenthMonthPay = $entries->sum('thirteenth_month_pay');

        // De minimis benefits
        $deMinimis = $entries->sum('de_minimis');

        // Government contributions (tax-deductible)
        $sssEmployee = $entries->sum('sss_employee');
        $philhealthEmployee = $entries->sum('philhealth_employee');
        $pagibigEmployee = $entries->sum('pagibig_employee');
        $totalContributions = $sssEmployee + $philhealthEmployee + $pagibigEmployee;

        // Non-taxable compensation
        $nonTaxable13thMonth = min($thirteenthMonthPay, 90000);
        $totalNonTaxable = $nonTaxable13thMonth + $deMinimis;

        // Taxable compensation
        $taxableCompensation = $grossCompensation - $totalContributions - $totalNonTaxable;

        return (object) [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'tin' => $employee->tin,
            'last_name' => $employee->last_name,
            'first_name' => $employee->first_name,
            'middle_name' => $employee->middle_name,
            'suffix' => $employee->suffix,
            'full_name' => $employee->full_name,
            'date_of_birth' => $employee->date_of_birth?->format('F j, Y'),
            'address' => $this->formatAddress($employee->address),
            'position' => $employee->position?->title ?? '-',
            'department' => $employee->department?->name ?? '-',
            'hire_date' => $employee->hire_date?->format('F j, Y'),
            'termination_date' => $employee->termination_date?->format('F j, Y'),
            'tax_year' => $year,

            // Compensation breakdown
            'gross_compensation' => $grossCompensation,
            'basic_salary' => $entries->sum('basic_pay'),
            'overtime_pay' => $entries->sum('overtime_pay'),
            'holiday_pay' => $entries->sum('holiday_pay'),
            'night_differential' => $entries->sum('night_differential'),
            'thirteenth_month_pay' => $thirteenthMonthPay,
            'de_minimis' => $deMinimis,
            'other_benefits' => $entries->sum('other_earnings'),

            // Non-taxable breakdown
            'non_taxable_13th_month' => $nonTaxable13thMonth,
            'total_non_taxable' => $totalNonTaxable,

            // Contributions
            'sss_contributions' => $sssEmployee,
            'philhealth_contributions' => $philhealthEmployee,
            'pagibig_contributions' => $pagibigEmployee,
            'total_contributions' => $totalContributions,

            // Tax computation
            'taxable_compensation' => max(0, $taxableCompensation),
            'withholding_tax' => $withholdingTax,

            // Period covered
            'period_from' => "{$year}-01-01",
            'period_to' => "{$year}-12-31",
        ];
    }

    protected function getExcelHeaders(): array
    {
        return [
            'No.',
            'TIN',
            'Last Name',
            'First Name',
            'Middle Name',
            'Gross Compensation',
            '13th Month',
            'De Minimis',
            'SSS',
            'PhilHealth',
            'Pag-IBIG',
            'Taxable',
            'Tax Withheld',
        ];
    }

    protected function mapRowToExcel($row): array
    {
        static $rowNum = 0;
        $rowNum++;

        return [
            $rowNum,
            $row->tin,
            $row->last_name,
            $row->first_name,
            $row->middle_name ?? '',
            $this->formatCurrency($row->gross_compensation),
            $this->formatCurrency($row->thirteenth_month_pay),
            $this->formatCurrency($row->de_minimis),
            $this->formatCurrency($row->sss_contributions),
            $this->formatCurrency($row->philhealth_contributions),
            $this->formatCurrency($row->pagibig_contributions),
            $this->formatCurrency($row->taxable_compensation),
            $this->formatCurrency($row->withholding_tax),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.bir.2316-certificate';
    }

    /**
     * Generate PDF output for BIR 2316 certificates.
     *
     * Overrides the base method to handle the per-employee certificate format.
     * Generates a multi-page PDF with one certificate per employee.
     *
     * @param  array{data: Collection, totals: array<string, mixed>}  $data
     * @return array{content: string, filename: string, contentType: string}
     */
    public function toPdf(array $data, int $year, ?int $month, ?int $quarter): array
    {
        $tenant = tenant();

        $company = [
            'name' => $tenant?->name ?? 'Company',
            'address' => $tenant?->business_info['address'] ?? null,
            'tin' => $tenant?->business_info['tin'] ?? null,
            'zip_code' => $tenant?->business_info['zip_code'] ?? null,
        ];

        $generatedAt = now()->format('F j, Y g:i A');

        // Generate a multi-page PDF with one certificate per employee
        $viewData = [
            'company' => $company,
            'employees' => $data['data'],
            'tax_year' => $year,
            'generated_at' => $generatedAt,
        ];

        $pdf = Pdf::loadView('pdf.bir.2316-certificates-batch', $viewData);
        $pdf->setPaper('A4', 'portrait');

        $filename = $this->generateFilename('pdf', $year, $month, $quarter);

        return [
            'content' => $pdf->output(),
            'filename' => $filename,
            'contentType' => 'application/pdf',
        ];
    }

    /**
     * Generate a single employee's 2316 certificate as PDF.
     *
     * @return array{content: string, filename: string, contentType: string}
     */
    public function generateEmployeePdf(int $employeeId, int $year): array
    {
        $data = $this->getEmployeeData($employeeId, $year);

        if ($data['data']->isEmpty()) {
            throw new \RuntimeException('No payroll data found for the specified employee and year.');
        }

        $employeeData = $data['data']->first();
        $tenant = tenant();

        $viewData = [
            'company' => [
                'name' => $tenant?->name ?? 'Company',
                'address' => $tenant?->business_info['address'] ?? null,
                'tin' => $tenant?->business_info['tin'] ?? null,
                'zip_code' => $tenant?->business_info['zip_code'] ?? null,
            ],
            'employee' => $employeeData,
            'tax_year' => $year,
            'generated_at' => now()->format('F j, Y g:i A'),
        ];

        $pdf = Pdf::loadView($this->getPdfView(), $viewData);
        $pdf->setPaper('A4', 'portrait');

        $filename = sprintf('bir_2316_%s_%d.pdf', $employeeData->employee_number, $year);

        return [
            'content' => $pdf->output(),
            'filename' => $filename,
            'contentType' => 'application/pdf',
        ];
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('F'.$row, $this->formatCurrency($totals['gross_compensation']));
        $sheet->setCellValue('L'.$row, $this->formatCurrency($totals['taxable_compensation']));
        $sheet->setCellValue('M'.$row, $this->formatCurrency($totals['withholding_tax']));

        $lastCol = 'M';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE],
            ],
        ]);
    }

    /**
     * Map a data row to DAT format for BIR eFiling.
     */
    protected function mapRowToDat($row): string
    {
        // BIR 2316 DAT format
        return implode('|', [
            'D', // Detail record
            $this->formatTinForDat($row->tin),
            strtoupper($row->last_name ?? ''),
            strtoupper($row->first_name ?? ''),
            strtoupper($row->middle_name ?? ''),
            $row->date_of_birth ? date('m/d/Y', strtotime($row->date_of_birth)) : '',
            strtoupper($row->address ?? ''),
            number_format($row->gross_compensation, 2, '.', ''),
            number_format($row->total_non_taxable, 2, '.', ''),
            number_format($row->taxable_compensation, 2, '.', ''),
            number_format($row->withholding_tax, 2, '.', ''),
            number_format($row->sss_contributions, 2, '.', ''),
            number_format($row->philhealth_contributions, 2, '.', ''),
            number_format($row->pagibig_contributions, 2, '.', ''),
        ]);
    }

    /**
     * Get the DAT file header line with employer information.
     */
    protected function getDatHeader(int $year, ?int $month, ?int $quarter): ?string
    {
        $tenant = tenant();
        $tin = $this->formatTinForDat($tenant?->business_info['tin'] ?? '');
        $name = strtoupper($tenant?->name ?? '');
        $address = strtoupper($tenant?->business_info['address'] ?? '');

        return implode('|', ['H', '2316', $tin, $name, $address, $year]);
    }

    /**
     * Get the DAT file footer with control totals.
     */
    protected function getDatFooter(array $totals, int $year): ?string
    {
        return implode('|', [
            'C',
            $totals['employee_count'],
            number_format($totals['gross_compensation'], 2, '.', ''),
            number_format($totals['non_taxable_compensation'] ?? 0, 2, '.', ''),
            number_format($totals['taxable_compensation'], 2, '.', ''),
            number_format($totals['withholding_tax'], 2, '.', ''),
        ]);
    }

    /**
     * Format TIN for DAT file.
     */
    protected function formatTinForDat(?string $tin): string
    {
        if (! $tin) {
            return '';
        }

        return preg_replace('/[^0-9]/', '', $tin) ?? '';
    }

    /**
     * Format address array to string.
     */
    protected function formatAddress(?array $address): string
    {
        if (! $address) {
            return '';
        }

        $parts = array_filter([
            $address['street'] ?? '',
            $address['barangay'] ?? '',
            $address['city'] ?? '',
            $address['province'] ?? '',
            $address['postal_code'] ?? '',
        ]);

        return implode(', ', $parts);
    }

    /**
     * Generate filled Excel file from official BIR 2316 template.
     *
     * Uses the official BIR Form 2316 (September 2021 ENCS) Excel template,
     * filling in employee and company data into the appropriate cells.
     *
     * @param  array{data: Collection, totals: array<string, mixed>}  $data
     * @return array{content: string, filename: string, contentType: string}
     */
    public function toFilledExcel(array $data, int $year): array
    {
        if (! $this->templateService->templateExists()) {
            throw new \RuntimeException('BIR 2316 template file not found.');
        }

        $tenant = tenant();
        $companyData = $this->buildCompanyData($tenant);

        // Convert collection items to arrays for the template service
        $employeesData = $data['data']->map(fn ($item) => $this->convertToTemplateFormat($item))->toArray();

        return $this->templateService->exportBatchAsExcel($employeesData, $companyData, $year);
    }

    /**
     * Generate filled PDF file from official BIR 2316 template.
     *
     * Uses the official BIR Form 2316 (September 2021 ENCS) Excel template,
     * fills in data, and converts to PDF format.
     *
     * @param  array{data: Collection, totals: array<string, mixed>}  $data
     * @return array{content: string, filename: string, contentType: string}
     */
    public function toFilledPdf(array $data, int $year): array
    {
        if (! $this->templateService->templateExists()) {
            throw new \RuntimeException('BIR 2316 template file not found.');
        }

        $tenant = tenant();
        $companyData = $this->buildCompanyData($tenant);

        // For PDF, we generate one filled form at a time since PhpSpreadsheet PDF
        // doesn't handle multi-sheet PDFs well. For batch, use Excel export.
        if ($data['data']->count() === 1) {
            $employeeData = $this->convertToTemplateFormat($data['data']->first());
            $spreadsheet = $this->templateService->fillTemplate($employeeData, $companyData);

            $filename = sprintf('bir_2316_%s_%d.pdf', $employeeData['employee_number'], $year);

            return $this->templateService->exportAsPdf($spreadsheet, $filename);
        }

        // For multiple employees, generate individual PDFs combined would require
        // a different approach. For now, return first employee's PDF.
        $firstEmployee = $this->convertToTemplateFormat($data['data']->first());
        $spreadsheet = $this->templateService->fillTemplate($firstEmployee, $companyData);
        $filename = sprintf('bir_2316_%d.pdf', $year);

        return $this->templateService->exportAsPdf($spreadsheet, $filename);
    }

    /**
     * Generate a single employee's 2316 certificate as filled Excel from template.
     *
     * @return array{content: string, filename: string, contentType: string}
     */
    public function generateEmployeeTemplateExcel(int $employeeId, int $year): array
    {
        if (! $this->templateService->templateExists()) {
            throw new \RuntimeException('BIR 2316 template file not found.');
        }

        $data = $this->getEmployeeData($employeeId, $year);

        if ($data['data']->isEmpty()) {
            throw new \RuntimeException('No payroll data found for the specified employee and year.');
        }

        $employeeData = $this->convertToTemplateFormat($data['data']->first());
        $tenant = tenant();
        $companyData = $this->buildCompanyData($tenant);

        $spreadsheet = $this->templateService->fillTemplate($employeeData, $companyData);
        $filename = sprintf('bir_2316_%s_%d.xlsx', $employeeData['employee_number'], $year);

        return $this->templateService->exportAsExcel($spreadsheet, $filename);
    }

    /**
     * Generate a single employee's 2316 certificate as filled PDF from template.
     *
     * @return array{content: string, filename: string, contentType: string}
     */
    public function generateEmployeeTemplatePdf(int $employeeId, int $year): array
    {
        if (! $this->templateService->templateExists()) {
            throw new \RuntimeException('BIR 2316 template file not found.');
        }

        $data = $this->getEmployeeData($employeeId, $year);

        if ($data['data']->isEmpty()) {
            throw new \RuntimeException('No payroll data found for the specified employee and year.');
        }

        $employeeData = $this->convertToTemplateFormat($data['data']->first());
        $tenant = tenant();
        $companyData = $this->buildCompanyData($tenant);

        $spreadsheet = $this->templateService->fillTemplate($employeeData, $companyData);
        $filename = sprintf('bir_2316_%s_%d.pdf', $employeeData['employee_number'], $year);

        return $this->templateService->exportAsPdf($spreadsheet, $filename);
    }

    /**
     * Check if the official template is available.
     */
    public function hasOfficialTemplate(): bool
    {
        return $this->templateService->templateExists();
    }

    /**
     * Build company data array from tenant.
     *
     * @param  mixed  $tenant
     * @return array<string, mixed>
     */
    protected function buildCompanyData($tenant): array
    {
        return [
            'name' => $tenant?->name ?? 'Company',
            'address' => $tenant?->business_info['address'] ?? null,
            'tin' => $tenant?->business_info['tin'] ?? null,
            'zip_code' => $tenant?->business_info['zip_code'] ?? null,
            'telephone' => $tenant?->business_info['telephone'] ?? null,
            'rdo_code' => $tenant?->business_info['rdo_code'] ?? null,
        ];
    }

    /**
     * Convert employee data object to template-compatible array format.
     *
     * @param  object  $data
     * @return array<string, mixed>
     */
    protected function convertToTemplateFormat($data): array
    {
        return [
            // Employee identification
            'employee_id' => $data->employee_id,
            'employee_number' => $data->employee_number,
            'tin' => $data->tin,
            'last_name' => $data->last_name,
            'first_name' => $data->first_name,
            'middle_name' => $data->middle_name ?? '',
            'suffix' => $data->suffix ?? '',
            'full_name' => $data->full_name,
            'date_of_birth' => $data->date_of_birth,
            'address' => $data->address,
            'tax_year' => $data->tax_year,

            // Gross compensation
            'gross_compensation' => $data->gross_compensation,

            // Taxable income breakdown
            'basic_salary' => $data->basic_salary,
            'overtime_pay' => $data->overtime_pay ?? 0,
            'holiday_pay' => $data->holiday_pay ?? 0,
            'night_differential' => $data->night_differential ?? 0,

            // Non-taxable benefits
            'thirteenth_month_pay' => $data->thirteenth_month_pay ?? 0,
            'non_taxable_13th_month' => $data->non_taxable_13th_month ?? 0,
            'de_minimis' => $data->de_minimis ?? 0,
            'total_non_taxable' => $data->total_non_taxable,

            // Contributions (these reduce taxable income)
            'sss_contributions' => $data->sss_contributions ?? 0,
            'philhealth_contributions' => $data->philhealth_contributions ?? 0,
            'pagibig_contributions' => $data->pagibig_contributions ?? 0,
            'total_contributions' => $data->total_contributions ?? 0,

            // Tax computation
            'taxable_compensation' => $data->taxable_compensation,
            'withholding_tax' => $data->withholding_tax,

            // Additional fields for template mapping (set defaults for now)
            'representation' => 0,
            'transportation' => 0,
            'cola' => 0,
            'housing_allowance' => 0,
            'commission' => 0,
            'profit_sharing' => 0,
            'directors_fees' => 0,
            'hazard_pay' => 0,
            'previous_employer_income' => 0,
            'tax_due' => $data->withholding_tax, // Simplified: assume tax due = tax withheld
            'year_end_adjustment' => 0,
            'pera_tax_credit' => 0,
        ];
    }
}
