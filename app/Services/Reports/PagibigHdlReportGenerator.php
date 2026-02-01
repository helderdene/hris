<?php

namespace App\Services\Reports;

use App\Enums\LoanType;
use App\Models\LoanPayment;
use Illuminate\Support\Collection;

/**
 * Generator for Pag-IBIG HDL Report - Housing Loan Amortization.
 *
 * Lists all housing loan payments deducted during the month.
 */
class PagibigHdlReportGenerator extends BasePagibigReportGenerator
{
    public function getTitle(): string
    {
        return 'HDL - Housing Loan Amortization';
    }

    public function getReportCode(): string
    {
        return 'hdl';
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
        $hdlType = LoanType::PagibigHousing->value;

        // Calculate month date range
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $query = LoanPayment::query()
            ->whereHas('loan', function ($q) use ($hdlType) {
                $q->where('loan_type', $hdlType);
            })
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->with([
                'loan:id,employee_id,loan_type,reference_number,principal_amount,total_amount',
                'loan.employee:id,employee_number,pagibig_number,first_name,middle_name,last_name,suffix,department_id',
                'loan.employee.department:id,name',
            ]);

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereHas('loan.employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $query->orderBy('payment_date');

        if ($limit) {
            $query->limit($limit);
        }

        $payments = $query->get();

        // Aggregate by employee (there's only one loan type - housing)
        $aggregated = $payments->groupBy(function ($payment) {
            return $payment->loan->employee_id;
        })->map(function ($group) {
            $first = $group->first();
            $loan = $first->loan;
            $employee = $loan->employee;

            return (object) [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'pagibig_number' => $employee->pagibig_number,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'suffix' => $employee->suffix,
                'department' => $employee->department?->name ?? '-',
                'loan_type' => $loan->loan_type->value,
                'loan_type_label' => $loan->loan_type->label(),
                'reference_number' => $loan->reference_number,
                'principal_amount' => $loan->principal_amount,
                'total_payments' => $group->sum('amount'),
                'payment_count' => $group->count(),
            ];
        })->values();

        $totals = [
            'employee_count' => $aggregated->count(),
            'loan_count' => $aggregated->count(),
            'total_payments' => $aggregated->sum('total_payments'),
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
            'Reference No.',
            'Principal Amount',
            'Monthly Amortization',
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
            $row->reference_number ?? '',
            $this->formatCurrency($row->principal_amount),
            $this->formatCurrency($row->total_payments),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.pagibig.hdl-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('F'.$row, $totals['loan_count'].' loans');
        $sheet->setCellValue('H'.$row, $this->formatCurrency($totals['total_payments']));

        $lastCol = 'H';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE],
            ],
        ]);
    }
}
