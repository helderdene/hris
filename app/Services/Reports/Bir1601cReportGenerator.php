<?php

namespace App\Services\Reports;

use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Illuminate\Support\Collection;

/**
 * Generator for BIR Form 1601-C - Monthly Remittance Return of Income Taxes Withheld.
 *
 * Lists all employees with compensation income subject to withholding tax for the month.
 */
class Bir1601cReportGenerator extends BaseBirReportGenerator
{
    public function getTitle(): string
    {
        return '1601-C - Monthly Remittance Return of Income Taxes Withheld';
    }

    public function getReportCode(): string
    {
        return '1601c';
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
            ->whereHas('payrollPeriod', function ($q) use ($year, $month) {
                $q->whereYear('cutoff_start', $year)
                    ->whereMonth('cutoff_start', $month);
            })
            ->whereHas('employee', function ($q) {
                $q->whereNotNull('tin')
                    ->where('tin', '!=', '');
            })
            ->with(['employee:id,employee_number,tin,first_name,middle_name,last_name,suffix,department_id', 'employee.department:id,name'])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid])
            ->where('withholding_tax', '>', 0);

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $query->orderBy('employee_name');

        if ($limit) {
            $query->limit($limit);
        }

        $entries = $query->get();

        // Aggregate by employee (in case of multiple entries in a month - bi-monthly periods)
        $aggregated = $entries->groupBy('employee_id')->map(function ($group) {
            $first = $group->first();
            $employee = $first->employee;

            $grossCompensation = $group->sum('gross_pay');
            $withholdingTax = $group->sum('withholding_tax');

            // Taxable compensation = gross - government contributions
            $sssEmployee = $group->sum('sss_employee');
            $philhealthEmployee = $group->sum('philhealth_employee');
            $pagibigEmployee = $group->sum('pagibig_employee');
            $taxableCompensation = $grossCompensation - $sssEmployee - $philhealthEmployee - $pagibigEmployee;

            return (object) [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'tin' => $employee->tin,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'suffix' => $employee->suffix,
                'department' => $employee->department?->name ?? '-',
                'gross_compensation' => $grossCompensation,
                'taxable_compensation' => $taxableCompensation,
                'withholding_tax' => $withholdingTax,
            ];
        })->values();

        $totals = [
            'employee_count' => $aggregated->count(),
            'gross_compensation' => $aggregated->sum('gross_compensation'),
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
            $this->formatCurrency($row->taxable_compensation),
            $this->formatCurrency($row->withholding_tax),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.bir.1601c-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('G'.$row, $this->formatCurrency($totals['gross_compensation']));
        $sheet->setCellValue('H'.$row, $this->formatCurrency($totals['taxable_compensation']));
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['withholding_tax']));

        $lastCol = 'I';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE],
            ],
        ]);
    }
}
