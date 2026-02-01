<?php

namespace App\Services\Reports;

use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Illuminate\Support\Collection;

/**
 * Generator for BIR Form 1604-CF - Annual Information Return of Income Taxes Withheld.
 *
 * Provides employer-level annual summary of total compensation and taxes withheld
 * for all employees during the calendar year.
 */
class Bir1604cfReportGenerator extends BaseBirReportGenerator
{
    public function getTitle(): string
    {
        return '1604-CF - Annual Information Return of Income Taxes Withheld on Compensation';
    }

    public function getReportCode(): string
    {
        return '1604cf';
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
        ?int $limit = null
    ): array {
        $query = PayrollEntry::query()
            ->whereHas('payrollPeriod', function ($q) use ($year) {
                $q->whereYear('cutoff_start', $year);
            })
            ->whereHas('employee', function ($q) {
                $q->whereNotNull('tin')
                    ->where('tin', '!=', '');
            })
            ->with(['employee:id,employee_number,tin,first_name,middle_name,last_name,suffix,department_id,date_of_birth,address', 'employee.department:id,name'])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid]);

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $entries = $query->get();

        // Aggregate by employee for the entire year
        $aggregated = $entries->groupBy('employee_id')->map(function ($group) {
            $first = $group->first();
            $employee = $first->employee;

            $grossCompensation = $group->sum('gross_pay');
            $withholdingTax = $group->sum('withholding_tax');

            // Calculate non-taxable compensation (13th month, de minimis)
            $thirteenthMonthPay = $group->sum('thirteenth_month_pay');
            $deMinimis = $group->sum('de_minimis');
            $nonTaxableCompensation = $thirteenthMonthPay + $deMinimis;

            // Taxable compensation = gross - government contributions - non-taxable
            $sssEmployee = $group->sum('sss_employee');
            $philhealthEmployee = $group->sum('philhealth_employee');
            $pagibigEmployee = $group->sum('pagibig_employee');
            $totalContributions = $sssEmployee + $philhealthEmployee + $pagibigEmployee;
            $taxableCompensation = $grossCompensation - $totalContributions - $nonTaxableCompensation;

            return (object) [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'tin' => $employee->tin,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'suffix' => $employee->suffix,
                'department' => $employee->department?->name ?? '-',
                'date_of_birth' => $employee->date_of_birth?->format('m/d/Y'),
                'address' => $this->formatAddress($employee->address),
                'gross_compensation' => $grossCompensation,
                'non_taxable_compensation' => $nonTaxableCompensation,
                'taxable_compensation' => max(0, $taxableCompensation),
                'withholding_tax' => $withholdingTax,
                'sss_contributions' => $sssEmployee,
                'philhealth_contributions' => $philhealthEmployee,
                'pagibig_contributions' => $pagibigEmployee,
            ];
        })->values();

        // Sort by last name
        $aggregated = $aggregated->sortBy('last_name')->values();

        if ($limit) {
            $aggregated = $aggregated->take($limit);
        }

        $totals = [
            'employee_count' => $aggregated->count(),
            'gross_compensation' => $aggregated->sum('gross_compensation'),
            'non_taxable_compensation' => $aggregated->sum('non_taxable_compensation'),
            'taxable_compensation' => $aggregated->sum('taxable_compensation'),
            'withholding_tax' => $aggregated->sum('withholding_tax'),
        ];

        return [
            'data' => $aggregated,
            'totals' => $totals,
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
            'Suffix',
            'Gross Compensation',
            'Non-Taxable',
            'Taxable Compensation',
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
            $row->suffix ?? '',
            $this->formatCurrency($row->gross_compensation),
            $this->formatCurrency($row->non_taxable_compensation),
            $this->formatCurrency($row->taxable_compensation),
            $this->formatCurrency($row->withholding_tax),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.bir.1604cf-summary';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('G'.$row, $this->formatCurrency($totals['gross_compensation']));
        $sheet->setCellValue('H'.$row, $this->formatCurrency($totals['non_taxable_compensation']));
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['taxable_compensation']));
        $sheet->setCellValue('J'.$row, $this->formatCurrency($totals['withholding_tax']));

        $lastCol = 'J';
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
        // BIR 1604-CF DAT format: TIN|LAST_NAME|FIRST_NAME|MIDDLE_NAME|GROSS|NON_TAXABLE|TAXABLE|TAX_WITHHELD
        return implode('|', [
            $this->formatTinForDat($row->tin),
            strtoupper($row->last_name ?? ''),
            strtoupper($row->first_name ?? ''),
            strtoupper($row->middle_name ?? ''),
            number_format($row->gross_compensation, 2, '.', ''),
            number_format($row->non_taxable_compensation, 2, '.', ''),
            number_format($row->taxable_compensation, 2, '.', ''),
            number_format($row->withholding_tax, 2, '.', ''),
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

        // Header format: H|TIN|EMPLOYER_NAME|ADDRESS|YEAR
        return implode('|', ['H', $tin, $name, $address, $year]);
    }

    /**
     * Get the DAT file footer with control totals.
     */
    protected function getDatFooter(array $totals, int $year): ?string
    {
        // Footer format: C|EMPLOYEE_COUNT|TOTAL_GROSS|TOTAL_NON_TAXABLE|TOTAL_TAXABLE|TOTAL_TAX
        return implode('|', [
            'C',
            $totals['employee_count'],
            number_format($totals['gross_compensation'], 2, '.', ''),
            number_format($totals['non_taxable_compensation'], 2, '.', ''),
            number_format($totals['taxable_compensation'], 2, '.', ''),
            number_format($totals['withholding_tax'], 2, '.', ''),
        ]);
    }

    /**
     * Format TIN for DAT file (remove dashes and special characters).
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
}
