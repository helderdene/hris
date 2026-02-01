<?php

namespace App\Services\Reports;

use App\Enums\EmploymentStatus;
use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Illuminate\Support\Collection;

/**
 * Generator for BIR Alphalist - Year-End Compliance Listing.
 *
 * Generates the required year-end employee listings with schedules:
 * - Schedule 7.1: Employees receiving compensation above minimum wage (with tax withheld)
 * - Schedule 7.2: Minimum wage earners (zero tax withheld)
 * - Schedule 7.3: Employees separated during the year
 */
class BirAlphalistReportGenerator extends BaseBirReportGenerator
{
    /**
     * The schedule to generate (7.1, 7.2, or 7.3).
     */
    protected string $schedule = '7.1';

    public function __construct() {}

    /**
     * Set the schedule to generate.
     */
    public function setSchedule(string $schedule): self
    {
        if (! in_array($schedule, ['7.1', '7.2', '7.3'])) {
            throw new \InvalidArgumentException("Invalid schedule: {$schedule}. Must be 7.1, 7.2, or 7.3.");
        }

        $this->schedule = $schedule;

        return $this;
    }

    public function getSchedule(): string
    {
        return $this->schedule;
    }

    public function getTitle(): string
    {
        return match ($this->schedule) {
            '7.1' => 'Alphalist Schedule 7.1 - Employees with Tax Withheld',
            '7.2' => 'Alphalist Schedule 7.2 - Minimum Wage Earners',
            '7.3' => 'Alphalist Schedule 7.3 - Employees Separated During Year',
            default => 'Alphalist - Year-End Compliance Listing',
        };
    }

    public function getReportCode(): string
    {
        return 'alphalist_'.str_replace('.', '', $this->schedule);
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
        return match ($this->schedule) {
            '7.1' => $this->getSchedule71Data($year, $departmentIds, $limit),
            '7.2' => $this->getSchedule72Data($year, $departmentIds, $limit),
            '7.3' => $this->getSchedule73Data($year, $departmentIds, $limit),
            default => throw new \InvalidArgumentException("Invalid schedule: {$this->schedule}"),
        };
    }

    /**
     * Get Schedule 7.1 data: Employees with tax withheld (above minimum wage).
     *
     * @param  array<int>|null  $departmentIds
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    protected function getSchedule71Data(int $year, ?array $departmentIds, ?int $limit): array
    {
        $query = PayrollEntry::query()
            ->whereHas('payrollPeriod', function ($q) use ($year) {
                $q->whereYear('cutoff_start', $year);
            })
            ->whereHas('employee', function ($q) {
                $q->whereNotNull('tin')
                    ->where('tin', '!=', '');
            })
            ->with(['employee:id,employee_number,tin,first_name,middle_name,last_name,suffix,department_id,date_of_birth,address,hire_date', 'employee.department:id,name'])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid])
            ->where('withholding_tax', '>', 0);

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $entries = $query->get();

        return $this->aggregateEmployeeData($entries, $year, $limit);
    }

    /**
     * Get Schedule 7.2 data: Minimum wage earners (zero tax withheld).
     *
     * @param  array<int>|null  $departmentIds
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    protected function getSchedule72Data(int $year, ?array $departmentIds, ?int $limit): array
    {
        $query = PayrollEntry::query()
            ->whereHas('payrollPeriod', function ($q) use ($year) {
                $q->whereYear('cutoff_start', $year);
            })
            ->whereHas('employee', function ($q) {
                $q->whereNotNull('tin')
                    ->where('tin', '!=', '');
            })
            ->with(['employee:id,employee_number,tin,first_name,middle_name,last_name,suffix,department_id,date_of_birth,address,hire_date', 'employee.department:id,name'])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid]);

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $entries = $query->get();

        // Filter to employees with zero tax withheld (aggregate first, then filter)
        $aggregated = $entries->groupBy('employee_id')->filter(function ($group) {
            return $group->sum('withholding_tax') == 0;
        });

        return $this->aggregateEmployeeDataFromGroups($aggregated, $year, $limit);
    }

    /**
     * Get Schedule 7.3 data: Employees separated during the year.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    protected function getSchedule73Data(int $year, ?array $departmentIds, ?int $limit): array
    {
        $query = PayrollEntry::query()
            ->whereHas('payrollPeriod', function ($q) use ($year) {
                $q->whereYear('cutoff_start', $year);
            })
            ->whereHas('employee', function ($q) use ($year) {
                $q->whereNotNull('tin')
                    ->where('tin', '!=', '')
                    ->where(function ($q) use ($year) {
                        // Separated during the year
                        $q->whereNotNull('termination_date')
                            ->whereYear('termination_date', $year);
                    })
                    ->orWhereIn('employment_status', [
                        EmploymentStatus::Resigned,
                        EmploymentStatus::Terminated,
                        EmploymentStatus::Retired,
                        EmploymentStatus::EndOfContract,
                        EmploymentStatus::Deceased,
                    ]);
            })
            ->with(['employee:id,employee_number,tin,first_name,middle_name,last_name,suffix,department_id,date_of_birth,address,hire_date,termination_date,employment_status', 'employee.department:id,name'])
            ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid]);

        if ($departmentIds && count($departmentIds) > 0) {
            $query->whereHas('employee', function ($q) use ($departmentIds) {
                $q->whereIn('department_id', $departmentIds);
            });
        }

        $entries = $query->get();

        // Filter to only separated employees
        $entries = $entries->filter(function ($entry) use ($year) {
            $employee = $entry->employee;

            $separatedStatuses = [
                EmploymentStatus::Resigned,
                EmploymentStatus::Terminated,
                EmploymentStatus::Retired,
                EmploymentStatus::EndOfContract,
                EmploymentStatus::Deceased,
            ];

            return ($employee->termination_date && $employee->termination_date->year == $year)
                || in_array($employee->employment_status, $separatedStatuses, true);
        });

        return $this->aggregateEmployeeData($entries, $year, $limit);
    }

    /**
     * Aggregate employee payroll data for the year.
     *
     * @param  Collection<int, PayrollEntry>  $entries
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    protected function aggregateEmployeeData(Collection $entries, int $year, ?int $limit): array
    {
        $aggregated = $entries->groupBy('employee_id');

        return $this->aggregateEmployeeDataFromGroups($aggregated, $year, $limit);
    }

    /**
     * Aggregate from pre-grouped employee entries.
     *
     * @param  Collection<int, Collection<int, PayrollEntry>>  $groupedEntries
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    protected function aggregateEmployeeDataFromGroups(Collection $groupedEntries, int $year, ?int $limit): array
    {
        $aggregated = $groupedEntries->map(function ($group) use ($year) {
            $first = $group->first();
            $employee = $first->employee;

            $grossCompensation = $group->sum('gross_pay');
            $withholdingTax = $group->sum('withholding_tax');
            $thirteenthMonthPay = $group->sum('thirteenth_month_pay');
            $deMinimis = $group->sum('de_minimis');
            $nonTaxableCompensation = $thirteenthMonthPay + $deMinimis;

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
                'hire_date' => $employee->hire_date?->format('m/d/Y'),
                'termination_date' => $employee->termination_date?->format('m/d/Y'),
                'gross_compensation' => $grossCompensation,
                'non_taxable_compensation' => $nonTaxableCompensation,
                'taxable_compensation' => max(0, $taxableCompensation),
                'withholding_tax' => $withholdingTax,
                'sss_contributions' => $sssEmployee,
                'philhealth_contributions' => $philhealthEmployee,
                'pagibig_contributions' => $pagibigEmployee,
                'tax_year' => $year,
            ];
        })->values();

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
        $baseHeaders = [
            'No.',
            'TIN',
            'Last Name',
            'First Name',
            'Middle Name',
            'Date of Birth',
            'Gross Compensation',
            'Non-Taxable',
            'Taxable Compensation',
            'Tax Withheld',
        ];

        if ($this->schedule === '7.3') {
            $baseHeaders[] = 'Termination Date';
        }

        return $baseHeaders;
    }

    protected function mapRowToExcel($row): array
    {
        static $rowNum = 0;
        $rowNum++;

        $data = [
            $rowNum,
            $row->tin,
            $row->last_name,
            $row->first_name,
            $row->middle_name ?? '',
            $row->date_of_birth ?? '',
            $this->formatCurrency($row->gross_compensation),
            $this->formatCurrency($row->non_taxable_compensation),
            $this->formatCurrency($row->taxable_compensation),
            $this->formatCurrency($row->withholding_tax),
        ];

        if ($this->schedule === '7.3') {
            $data[] = $row->termination_date ?? '';
        }

        return $data;
    }

    protected function getPdfView(): string
    {
        return match ($this->schedule) {
            '7.1' => 'pdf.bir.alphalist-7-1',
            '7.2' => 'pdf.bir.alphalist-7-2',
            '7.3' => 'pdf.bir.alphalist-7-3',
            default => 'pdf.bir.alphalist-7-1',
        };
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('G'.$row, $this->formatCurrency($totals['gross_compensation']));
        $sheet->setCellValue('H'.$row, $this->formatCurrency($totals['non_taxable_compensation']));
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['taxable_compensation']));
        $sheet->setCellValue('J'.$row, $this->formatCurrency($totals['withholding_tax']));

        $lastCol = $this->schedule === '7.3' ? 'K' : 'J';
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
        $fields = [
            'D', // Detail record
            $this->schedule,
            $this->formatTinForDat($row->tin),
            strtoupper($row->last_name ?? ''),
            strtoupper($row->first_name ?? ''),
            strtoupper($row->middle_name ?? ''),
            $row->date_of_birth ?? '',
            strtoupper($row->address ?? ''),
            number_format($row->gross_compensation, 2, '.', ''),
            number_format($row->non_taxable_compensation, 2, '.', ''),
            number_format($row->taxable_compensation, 2, '.', ''),
            number_format($row->withholding_tax, 2, '.', ''),
        ];

        if ($this->schedule === '7.3') {
            $fields[] = $row->termination_date ?? '';
        }

        return implode('|', $fields);
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

        return implode('|', ['H', 'ALPHALIST', $this->schedule, $tin, $name, $address, $year]);
    }

    /**
     * Get the DAT file footer with control totals.
     */
    protected function getDatFooter(array $totals, int $year): ?string
    {
        return implode('|', [
            'C',
            $this->schedule,
            $totals['employee_count'],
            number_format($totals['gross_compensation'], 2, '.', ''),
            number_format($totals['non_taxable_compensation'], 2, '.', ''),
            number_format($totals['taxable_compensation'], 2, '.', ''),
            number_format($totals['withholding_tax'], 2, '.', ''),
        ]);
    }

    /**
     * Format TIN for DAT file.
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
