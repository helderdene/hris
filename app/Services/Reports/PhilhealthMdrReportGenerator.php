<?php

namespace App\Services\Reports;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Generator for PhilHealth MDR Report - Member Data Record.
 *
 * Lists newly hired employees for PhilHealth registration.
 */
class PhilhealthMdrReportGenerator extends BasePhilhealthReportGenerator
{
    public function getTitle(): string
    {
        return 'MDR - Member Data Record';
    }

    public function getReportCode(): string
    {
        return 'mdr';
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
                'civil_status',
                'email',
                'phone',
                'address',
                'hire_date',
                'basic_salary',
                'department_id',
                'position_id',
            ]);

        // Filter by hire date
        if ($startDate && $endDate) {
            $query->whereBetween('hire_date', [$startDate->startOfDay(), $endDate->endOfDay()]);
        } elseif ($month) {
            $query->whereYear('hire_date', $year)
                ->whereMonth('hire_date', $month);
        } else {
            $query->whereYear('hire_date', $year);
        }

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereIn('department_id', $departmentIds);
        }

        $query->orderBy('hire_date', 'desc')->orderBy('last_name');

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
                'civil_status' => $employee->civil_status,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'address' => $this->formatAddress($employee->address),
                'position' => $employee->position?->title ?? '-',
                'department' => $employee->department?->name ?? '-',
                'hire_date' => $employee->hire_date,
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
            'Name',
            'Date of Birth',
            'Sex',
            'Civil Status',
            'TIN',
            'SSS No.',
            'PIN',
            'Address',
            'Phone',
            'Email',
            'Date Employed',
            'Position',
            'Department',
            'Salary',
        ];
    }

    protected function mapRowToExcel($row, int $index): array
    {
        return [
            $index,
            "{$row->last_name}, {$row->first_name}".($row->middle_name ? ' '.substr($row->middle_name, 0, 1).'.' : ''),
            $row->date_of_birth?->format('m/d/Y') ?? '',
            $this->formatGender($row->gender),
            $this->formatCivilStatus($row->civil_status),
            $row->tin ?? '',
            $row->sss_number ?? '',
            $row->philhealth_number ?? '',
            $row->address ?? '',
            $row->phone ?? '',
            $row->email ?? '',
            $row->hire_date?->format('m/d/Y') ?? '',
            $row->position ?? '',
            $row->department ?? '',
            $this->formatCurrency($row->basic_salary ?? 0),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.philhealth.mdr-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' new employees');
        $sheet->setCellValue('O'.$row, $this->formatCurrency($totals['total_salary']));

        $lastCol = 'O';
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
     * Format civil status for display.
     */
    protected function formatCivilStatus(?string $status): string
    {
        if (! $status) {
            return '';
        }

        return match (strtolower($status)) {
            'single' => 'Single',
            'married' => 'Married',
            'widowed' => 'Widowed',
            'separated' => 'Separated',
            'divorced' => 'Divorced',
            default => ucfirst($status),
        };
    }
}
