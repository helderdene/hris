<?php

namespace App\Services\Reports;

use App\Enums\LoanType;
use App\Models\LoanPayment;
use Illuminate\Support\Collection;

/**
 * Generator for Pag-IBIG STL Report - Short Term Loan Amortization.
 *
 * Lists all short-term loan payments (MPL, Calamity) deducted during the month.
 */
class PagibigStlReportGenerator extends BasePagibigReportGenerator
{
    public function getTitle(): string
    {
        return 'STL - Short Term Loan Amortization';
    }

    public function getReportCode(): string
    {
        return 'stl';
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
        $stlTypes = [LoanType::PagibigMpl->value, LoanType::PagibigCalamity->value];

        // Calculate month date range
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $query = LoanPayment::query()
            ->whereHas('loan', function ($q) use ($stlTypes) {
                $q->whereIn('loan_type', $stlTypes);
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

        // Aggregate by employee and loan type
        $aggregated = $payments->groupBy(function ($payment) {
            return $payment->loan->employee_id.'_'.$payment->loan->loan_type->value;
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

        // Group totals by loan type
        $byLoanType = $aggregated->groupBy('loan_type')->map(fn ($items) => [
            'count' => $items->count(),
            'total' => $items->sum('total_payments'),
        ]);

        $totals = [
            'employee_count' => $aggregated->unique('employee_id')->count(),
            'loan_count' => $aggregated->count(),
            'total_payments' => $aggregated->sum('total_payments'),
            'by_loan_type' => $byLoanType->toArray(),
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
            'Loan Type',
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
            $row->loan_type_label,
            $row->reference_number ?? '',
            $this->formatCurrency($row->principal_amount),
            $this->formatCurrency($row->total_payments),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.pagibig.stl-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('F'.$row, $totals['loan_count'].' loans');
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['total_payments']));

        $lastCol = 'I';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE],
            ],
        ]);

        // Add breakdown by loan type
        $row++;
        $row++;
        $sheet->setCellValue('A'.$row, 'SUMMARY BY LOAN TYPE');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        foreach ($totals['by_loan_type'] as $loanType => $summary) {
            $row++;
            $loanTypeLabel = LoanType::tryFrom($loanType)?->label() ?? $loanType;
            $sheet->setCellValue('A'.$row, $loanTypeLabel);
            $sheet->setCellValue('F'.$row, $summary['count'].' loans');
            $sheet->setCellValue('I'.$row, $this->formatCurrency($summary['total']));
        }
    }
}
