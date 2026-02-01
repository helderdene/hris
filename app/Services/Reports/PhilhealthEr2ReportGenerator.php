<?php

namespace App\Services\Reports;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Generator for PhilHealth ER2 Report - Employer Remittance Report.
 *
 * Lists complete employee member details including personal information and employment data.
 */
class PhilhealthEr2ReportGenerator extends BasePhilhealthReportGenerator
{
    public function getTitle(): string
    {
        return 'ER2 - Employer Remittance Report';
    }

    public function getReportCode(): string
    {
        return 'er2';
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
        $query = Employee::query()
            ->whereNotNull('philhealth_number')
            ->where('philhealth_number', '!=', '')
            ->whereNull('termination_date')
            ->with([
                'department:id,name',
                'position:id,title',
            ])
            ->select([
                'id',
                'employee_number',
                'philhealth_number',
                'sss_number',
                'tin',
                'first_name',
                'middle_name',
                'last_name',
                'suffix',
                'date_of_birth',
                'gender',
                'email',
                'phone',
                'address',
                'hire_date',
                'employment_status',
                'basic_salary',
                'department_id',
                'position_id',
            ]);

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereIn('department_id', $departmentIds);
        }

        $query->orderBy('last_name')->orderBy('first_name');

        if ($limit) {
            $query->limit($limit);
        }

        $employees = $query->get();

        $data = $employees->map(function ($employee) {
            return (object) [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'philhealth_number' => $employee->philhealth_number,
                'sss_number' => $employee->sss_number,
                'tin' => $employee->tin,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'suffix' => $employee->suffix,
                'name' => $employee->full_name ?? "{$employee->last_name}, {$employee->first_name}",
                'date_of_birth' => $employee->date_of_birth,
                'gender' => $employee->gender,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'address' => $this->formatAddress($employee->address),
                'position' => $employee->position?->title ?? '-',
                'department' => $employee->department?->name ?? '-',
                'hire_date' => $employee->hire_date,
                'employment_status' => $employee->employment_status?->label(),
                'basic_salary' => $employee->basic_salary,
            ];
        });

        $totals = [
            'employee_count' => $data->count(),
            'total_salary' => $data->sum('basic_salary'),
        ];

        return [
            'data' => $data,
            'totals' => $totals,
        ];
    }

    protected function getExcelHeaders(): array
    {
        return [
            'No.',
            'PIN',
            'Name',
            'Date of Birth',
            'Sex',
            'SSS No.',
            'TIN',
            'Address',
            'Email',
            'Phone',
            'Position',
            'Salary',
            'Date Employed',
            'Status',
        ];
    }

    protected function mapRowToExcel($row, int $index): array
    {
        return [
            $index,
            $row->philhealth_number,
            "{$row->last_name}, {$row->first_name}".($row->middle_name ? ' '.substr($row->middle_name, 0, 1).'.' : ''),
            $row->date_of_birth?->format('m/d/Y') ?? '',
            $this->formatGender($row->gender),
            $row->sss_number ?? '',
            $row->tin ?? '',
            $row->address ?? '',
            $row->email ?? '',
            $row->phone ?? '',
            $row->position ?? '',
            $this->formatCurrency($row->basic_salary ?? 0),
            $row->hire_date?->format('m/d/Y') ?? '',
            $this->formatStatus($row->employment_status),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.philhealth.er2-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('L'.$row, $this->formatCurrency($totals['total_salary']));

        $lastCol = 'N';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
        ]);
    }

    /**
     * Format address from JSON.
     *
     * @param  array<string, mixed>|null  $address
     */
    protected function formatAddress(?array $address): string
    {
        if (! $address) {
            return '';
        }

        $parts = array_filter([
            $address['street'] ?? null,
            $address['barangay'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
            $address['postal_code'] ?? $address['zip_code'] ?? null,
        ]);

        return implode(', ', $parts);
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

    /**
     * Format employment status for display.
     */
    protected function formatStatus(?string $status): string
    {
        if (! $status) {
            return '';
        }

        return match ($status) {
            'active' => 'Active',
            'probationary' => 'Probationary',
            'regular' => 'Regular',
            'resigned' => 'Resigned',
            'terminated' => 'Terminated',
            default => ucfirst($status),
        };
    }
}
