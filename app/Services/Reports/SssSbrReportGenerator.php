<?php

namespace App\Services\Reports;

use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Illuminate\Support\Collection;

/**
 * Generator for SSS SBR Report - Statement of Billing/Remittance.
 *
 * Summary report showing total contributions for proof of payment.
 */
class SssSbrReportGenerator extends BaseSssReportGenerator
{
    public function getTitle(): string
    {
        return 'SBR - Statement of Billing/Remittance';
    }

    public function getReportCode(): string
    {
        return 'sbr';
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
            ->with(['employee:id,sss_number,first_name,last_name,department_id', 'employee.department:id,name', 'payrollPeriod:id,name,cutoff_start,cutoff_end,pay_date'])
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

        // Group by payroll period for summary display
        $byPeriod = $entries->groupBy('payroll_period_id')->map(function ($group) {
            $period = $group->first()->payrollPeriod;

            return (object) [
                'period_id' => $period->id,
                'period_name' => $period->name,
                'cutoff_start' => $period->cutoff_start,
                'cutoff_end' => $period->cutoff_end,
                'pay_date' => $period->pay_date,
                'employee_count' => $group->count(),
                'gross_pay' => $group->sum('gross_pay'),
                'sss_employee' => $group->sum('sss_employee'),
                'sss_employer' => $group->sum('sss_employer'),
                'total_ss' => $group->sum('sss_employee') + $group->sum('sss_employer'),
                'sss_ec' => 0, // EC placeholder
            ];
        })->values();

        $totalEmployee = $byPeriod->sum('sss_employee');
        $totalEmployer = $byPeriod->sum('sss_employer');
        $totalEc = $byPeriod->sum('sss_ec');

        $totals = [
            'period_count' => $byPeriod->count(),
            'employee_count' => $entries->unique('employee_id')->count(),
            'gross_pay' => $byPeriod->sum('gross_pay'),
            'sss_employee' => $totalEmployee,
            'sss_employer' => $totalEmployer,
            'sss_ec' => $totalEc,
            'total_ss' => $totalEmployee + $totalEmployer,
            'total_contribution' => $totalEmployee + $totalEmployer + $totalEc,
        ];

        return [
            'data' => $byPeriod,
            'totals' => $totals,
        ];
    }

    protected function getExcelHeaders(): array
    {
        return [
            'Payroll Period',
            'Cutoff Start',
            'Cutoff End',
            'Pay Date',
            'Employees',
            'Total Gross Pay',
            'SS (Employee)',
            'SS (Employer)',
            'Total SS',
            'EC',
        ];
    }

    protected function mapRowToExcel($row): array
    {
        return [
            $row->period_name,
            $row->cutoff_start->format('m/d/Y'),
            $row->cutoff_end->format('m/d/Y'),
            $row->pay_date->format('m/d/Y'),
            $row->employee_count,
            $this->formatCurrency($row->gross_pay),
            $this->formatCurrency($row->sss_employee),
            $this->formatCurrency($row->sss_employer),
            $this->formatCurrency($row->total_ss),
            $this->formatCurrency($row->sss_ec),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.sss.sbr-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'GRAND TOTAL');
        $sheet->setCellValue('E'.$row, $totals['employee_count']);
        $sheet->setCellValue('F'.$row, $this->formatCurrency($totals['gross_pay']));
        $sheet->setCellValue('G'.$row, $this->formatCurrency($totals['sss_employee']));
        $sheet->setCellValue('H'.$row, $this->formatCurrency($totals['sss_employer']));
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['total_ss']));
        $sheet->setCellValue('J'.$row, $this->formatCurrency($totals['sss_ec']));

        $lastCol = 'J';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE],
            ],
        ]);

        // Add total contribution row
        $row++;
        $row++;
        $sheet->setCellValue('A'.$row, 'TOTAL AMOUNT FOR REMITTANCE:');
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['total_contribution']));
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("I{$row}")->getFont()->setSize(14);
    }
}
