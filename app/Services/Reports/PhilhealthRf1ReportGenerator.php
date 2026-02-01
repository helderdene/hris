<?php

namespace App\Services\Reports;

use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Generator for PhilHealth RF1 Report - Electronic Remittance Form.
 *
 * Lists all employee and employer PhilHealth contributions for the month.
 */
class PhilhealthRf1ReportGenerator extends BasePhilhealthReportGenerator
{
    public function getTitle(): string
    {
        return 'RF1 - Electronic Remittance Form';
    }

    public function getReportCode(): string
    {
        return 'rf1';
    }

    /**
     * @param  array<int>|null  $departmentIds
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    public function getData(
        int $year,
        ?int $month = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array $departmentIds = null,
        ?int $limit = null
    ): array {
        $query = PayrollEntry::query()
            ->whereHas('payrollPeriod', function ($q) use ($year, $month) {
                $q->whereYear('cutoff_start', $year)
                    ->whereMonth('cutoff_start', $month);
            })
            ->whereHas('employee', function ($q) {
                $q->whereNotNull('philhealth_number')
                    ->where('philhealth_number', '!=', '');
            })
            ->with([
                'employee:id,employee_number,philhealth_number,first_name,middle_name,last_name,suffix,date_of_birth,gender,department_id',
                'employee.department:id,name',
            ])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid])
            ->where(function ($q) {
                $q->where('philhealth_employee', '>', 0)
                    ->orWhere('philhealth_employer', '>', 0);
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
                'philhealth_number' => $employee->philhealth_number,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'suffix' => $employee->suffix,
                'date_of_birth' => $employee->date_of_birth,
                'gender' => $employee->gender,
                'department' => $employee->department?->name ?? '-',
                'gross_pay' => $group->sum('gross_pay'),
                'philhealth_employee' => $group->sum('philhealth_employee'),
                'philhealth_employer' => $group->sum('philhealth_employer'),
                'total_contribution' => $group->sum('philhealth_employee') + $group->sum('philhealth_employer'),
            ];
        })->values();

        $totals = [
            'employee_count' => $aggregated->count(),
            'gross_pay' => $aggregated->sum('gross_pay'),
            'philhealth_employee' => $aggregated->sum('philhealth_employee'),
            'philhealth_employer' => $aggregated->sum('philhealth_employer'),
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
            'PIN',
            'Last Name',
            'First Name',
            'Middle Name',
            'Suffix',
            'Date of Birth',
            'Sex',
            'Monthly Salary',
            'EE Share',
            'ER Share',
            'Total',
        ];
    }

    protected function mapRowToExcel($row, int $index): array
    {
        return [
            $index,
            $row->philhealth_number,
            $row->last_name,
            $row->first_name,
            $row->middle_name ?? '',
            $row->suffix ?? '',
            $row->date_of_birth?->format('m/d/Y') ?? '',
            $this->formatGender($row->gender),
            $this->formatCurrency($row->gross_pay),
            $this->formatCurrency($row->philhealth_employee),
            $this->formatCurrency($row->philhealth_employer),
            $this->formatCurrency($row->total_contribution),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.philhealth.rf1-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['gross_pay']));
        $sheet->setCellValue('J'.$row, $this->formatCurrency($totals['philhealth_employee']));
        $sheet->setCellValue('K'.$row, $this->formatCurrency($totals['philhealth_employer']));
        $sheet->setCellValue('L'.$row, $this->formatCurrency($totals['total_contribution']));

        $lastCol = 'L';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);
    }

    /**
     * Format gender for display.
     */
    protected function formatGender(?string $gender): string
    {
        if (! $gender) {
            return '';
        }

        return match (strtolower($gender)) {
            'male', 'm' => 'M',
            'female', 'f' => 'F',
            default => $gender,
        };
    }
}
