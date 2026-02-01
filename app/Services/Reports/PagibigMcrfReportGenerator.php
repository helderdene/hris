<?php

namespace App\Services\Reports;

use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Illuminate\Support\Collection;

/**
 * Generator for Pag-IBIG MCRF Report - Monthly Contribution Remittance Form.
 *
 * Lists all employee and employer Pag-IBIG contributions for the month.
 */
class PagibigMcrfReportGenerator extends BasePagibigReportGenerator
{
    public function getTitle(): string
    {
        return 'MCRF - Monthly Contribution Remittance Form';
    }

    public function getReportCode(): string
    {
        return 'mcrf';
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
                $q->whereNotNull('pagibig_number')
                    ->where('pagibig_number', '!=', '');
            })
            ->with(['employee:id,employee_number,pagibig_number,first_name,middle_name,last_name,suffix,date_of_birth,department_id', 'employee.department:id,name'])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid])
            ->where(function ($q) {
                $q->where('pagibig_employee', '>', 0)
                    ->orWhere('pagibig_employer', '>', 0);
            });

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

        // Aggregate by employee (in case of multiple entries in a month)
        $aggregated = $entries->groupBy('employee_id')->map(function ($group) {
            $first = $group->first();
            $employee = $first->employee;

            return (object) [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'pagibig_number' => $employee->pagibig_number,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'suffix' => $employee->suffix,
                'date_of_birth' => $employee->date_of_birth,
                'department' => $employee->department?->name ?? '-',
                'gross_pay' => $group->sum('gross_pay'),
                'pagibig_employee' => $group->sum('pagibig_employee'),
                'pagibig_employer' => $group->sum('pagibig_employer'),
                'total_contribution' => $group->sum('pagibig_employee') + $group->sum('pagibig_employer'),
            ];
        })->values();

        $totals = [
            'employee_count' => $aggregated->count(),
            'gross_pay' => $aggregated->sum('gross_pay'),
            'pagibig_employee' => $aggregated->sum('pagibig_employee'),
            'pagibig_employer' => $aggregated->sum('pagibig_employer'),
            'total_contribution' => $aggregated->sum('total_contribution'),
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
            'Pag-IBIG MID No.',
            'Last Name',
            'First Name',
            'Middle Name',
            'Date of Birth',
            'Monthly Compensation',
            'EE Share',
            'ER Share',
            'Total',
        ];
    }

    protected function mapRowToExcel($row): array
    {
        static $rowNum = 0;
        $rowNum++;

        return [
            $rowNum,
            $row->pagibig_number,
            $row->last_name,
            $row->first_name,
            $row->middle_name ?? '',
            $row->date_of_birth?->format('m/d/Y') ?? '',
            $this->formatCurrency($row->gross_pay),
            $this->formatCurrency($row->pagibig_employee),
            $this->formatCurrency($row->pagibig_employer),
            $this->formatCurrency($row->total_contribution),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.pagibig.mcrf-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('G'.$row, $this->formatCurrency($totals['gross_pay']));
        $sheet->setCellValue('H'.$row, $this->formatCurrency($totals['pagibig_employee']));
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['pagibig_employer']));
        $sheet->setCellValue('J'.$row, $this->formatCurrency($totals['total_contribution']));

        $lastCol = 'J';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE],
            ],
        ]);
    }
}
