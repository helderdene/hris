<?php

namespace App\Services\Reports;

use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Illuminate\Support\Collection;

/**
 * Generator for SSS R3 Report - Monthly Contribution Collection List.
 *
 * Lists all employee and employer SSS contributions for the month.
 */
class SssR3ReportGenerator extends BaseSssReportGenerator
{
    public function getTitle(): string
    {
        return 'R3 - Monthly Contribution Collection List';
    }

    public function getReportCode(): string
    {
        return 'r3';
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
                $q->whereNotNull('sss_number')
                    ->where('sss_number', '!=', '');
            })
            ->with(['employee:id,employee_number,sss_number,first_name,middle_name,last_name,suffix,date_of_birth,department_id', 'employee.department:id,name'])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid])
            ->where(function ($q) {
                $q->where('sss_employee', '>', 0)
                    ->orWhere('sss_employer', '>', 0);
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
                'sss_number' => $employee->sss_number,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'suffix' => $employee->suffix,
                'date_of_birth' => $employee->date_of_birth,
                'department' => $employee->department?->name ?? '-',
                'gross_pay' => $group->sum('gross_pay'),
                'sss_employee' => $group->sum('sss_employee'),
                'sss_employer' => $group->sum('sss_employer'),
                'sss_ec' => 0, // EC contribution placeholder
                'total_contribution' => $group->sum('sss_employee') + $group->sum('sss_employer'),
            ];
        })->values();

        $totals = [
            'employee_count' => $aggregated->count(),
            'gross_pay' => $aggregated->sum('gross_pay'),
            'sss_employee' => $aggregated->sum('sss_employee'),
            'sss_employer' => $aggregated->sum('sss_employer'),
            'sss_ec' => $aggregated->sum('sss_ec'),
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
            'SSS Number',
            'Last Name',
            'First Name',
            'Middle Name',
            'Suffix',
            'Date of Birth',
            'Monthly Salary Credit',
            'SS (Employee)',
            'SS (Employer)',
            'EC',
            'Total Contribution',
        ];
    }

    protected function mapRowToExcel($row): array
    {
        static $rowNum = 0;
        $rowNum++;

        return [
            $rowNum,
            $row->sss_number,
            $row->last_name,
            $row->first_name,
            $row->middle_name ?? '',
            $row->suffix ?? '',
            $row->date_of_birth?->format('m/d/Y') ?? '',
            $this->formatCurrency($row->gross_pay),
            $this->formatCurrency($row->sss_employee),
            $this->formatCurrency($row->sss_employer),
            $this->formatCurrency($row->sss_ec),
            $this->formatCurrency($row->total_contribution),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.sss.r3-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('H'.$row, $this->formatCurrency($totals['gross_pay']));
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['sss_employee']));
        $sheet->setCellValue('J'.$row, $this->formatCurrency($totals['sss_employer']));
        $sheet->setCellValue('K'.$row, $this->formatCurrency($totals['sss_ec']));
        $sheet->setCellValue('L'.$row, $this->formatCurrency($totals['total_contribution']));

        $lastCol = 'L';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE],
            ],
        ]);
    }
}
