<?php

namespace App\Services\Reports;

use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Illuminate\Support\Collection;

/**
 * Generator for SSS ECL Report - Electronic Collection List.
 *
 * Fixed-width format file for bank tellering and EPRS submission.
 */
class SssEclReportGenerator extends BaseSssReportGenerator
{
    public function getTitle(): string
    {
        return 'ECL - Electronic Collection List';
    }

    public function getReportCode(): string
    {
        return 'ecl';
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

        // Aggregate by employee
        $aggregated = $entries->groupBy('employee_id')->map(function ($group) {
            $first = $group->first();
            $employee = $first->employee;

            $totalSs = $group->sum('sss_employee') + $group->sum('sss_employer');

            return (object) [
                'employee_id' => $employee->id,
                'sss_number' => $this->cleanSssNumber($employee->sss_number),
                'last_name' => $this->cleanName($employee->last_name),
                'first_name' => $this->cleanName($employee->first_name),
                'middle_name' => $this->cleanName($employee->middle_name ?? ''),
                'suffix' => $this->cleanName($employee->suffix ?? ''),
                'date_of_birth' => $employee->date_of_birth,
                'ss_contribution' => $totalSs,
                'ec_contribution' => 0, // EC placeholder
                'department' => $employee->department?->name ?? '-',
            ];
        })->values();

        $totals = [
            'employee_count' => $aggregated->count(),
            'ss_contribution' => $aggregated->sum('ss_contribution'),
            'ec_contribution' => $aggregated->sum('ec_contribution'),
            'total_contribution' => $aggregated->sum('ss_contribution') + $aggregated->sum('ec_contribution'),
        ];

        return [
            'data' => $aggregated,
            'totals' => $totals,
        ];
    }

    /**
     * Generate CSV output with fixed-width format for ECL.
     *
     * @param  array{data: Collection, totals: array<string, mixed>}  $data
     * @return array{content: string, filename: string, contentType: string}
     */
    public function toCsv(array $data, int $year, ?int $month, ?int $quarter): array
    {
        $lines = [];

        foreach ($data['data'] as $row) {
            $lines[] = $this->formatEclLine($row);
        }

        $content = implode("\r\n", $lines);
        $filename = $this->generateFilename('txt', $year, $month, $quarter);

        return [
            'content' => $content,
            'filename' => $filename,
            'contentType' => 'text/plain',
        ];
    }

    /**
     * Format a single ECL line according to SSS fixed-width specifications.
     *
     * Field positions:
     * - SSS Number: 1-10 (10 chars, numeric)
     * - Last Name: 11-35 (25 chars, alpha)
     * - First Name: 36-60 (25 chars, alpha)
     * - Middle Name: 61-85 (25 chars, alpha)
     * - Suffix: 86-90 (5 chars, alpha)
     * - Birth Date: 91-98 (8 chars, MMDDYYYY)
     * - SS Contribution: 99-108 (10 chars, numeric with 2 decimals)
     * - EC Contribution: 109-118 (10 chars, numeric with 2 decimals)
     */
    protected function formatEclLine(object $row): string
    {
        $sssNumber = str_pad(substr($row->sss_number, 0, 10), 10, ' ', STR_PAD_RIGHT);
        $lastName = str_pad(substr($row->last_name, 0, 25), 25, ' ', STR_PAD_RIGHT);
        $firstName = str_pad(substr($row->first_name, 0, 25), 25, ' ', STR_PAD_RIGHT);
        $middleName = str_pad(substr($row->middle_name, 0, 25), 25, ' ', STR_PAD_RIGHT);
        $suffix = str_pad(substr($row->suffix, 0, 5), 5, ' ', STR_PAD_RIGHT);
        $birthDate = $row->date_of_birth ? $row->date_of_birth->format('mdY') : '        ';
        $ssContribution = str_pad($this->formatEclAmount($row->ss_contribution), 10, '0', STR_PAD_LEFT);
        $ecContribution = str_pad($this->formatEclAmount($row->ec_contribution), 10, '0', STR_PAD_LEFT);

        return $sssNumber.$lastName.$firstName.$middleName.$suffix.$birthDate.$ssContribution.$ecContribution;
    }

    /**
     * Format amount for ECL (no decimal point, 2 implied decimals).
     */
    protected function formatEclAmount(float $amount): string
    {
        return (string) round($amount * 100);
    }

    /**
     * Clean SSS number for fixed-width format.
     */
    protected function cleanSssNumber(?string $sssNumber): string
    {
        if (! $sssNumber) {
            return '';
        }

        // Remove any non-numeric characters
        return preg_replace('/[^0-9]/', '', $sssNumber);
    }

    /**
     * Clean name for fixed-width format (uppercase, alpha only).
     */
    protected function cleanName(?string $name): string
    {
        if (! $name) {
            return '';
        }

        // Convert to uppercase and remove non-alpha characters except spaces
        $clean = strtoupper(preg_replace('/[^a-zA-Z\s]/', '', $name));

        // Trim multiple spaces
        return trim(preg_replace('/\s+/', ' ', $clean));
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
            'SS Contribution',
            'EC Contribution',
            'Total',
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
            $row->middle_name,
            $row->suffix,
            $row->date_of_birth?->format('m/d/Y') ?? '',
            $this->formatCurrency($row->ss_contribution),
            $this->formatCurrency($row->ec_contribution),
            $this->formatCurrency($row->ss_contribution + $row->ec_contribution),
        ];
    }

    protected function getPdfView(): string
    {
        return 'pdf.sss.ecl-report';
    }

    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->setCellValue('B'.$row, $totals['employee_count'].' employees');
        $sheet->setCellValue('H'.$row, $this->formatCurrency($totals['ss_contribution']));
        $sheet->setCellValue('I'.$row, $this->formatCurrency($totals['ec_contribution']));
        $sheet->setCellValue('J'.$row, $this->formatCurrency($totals['total_contribution']));

        $lastCol = 'J';
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => [
                'top' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE],
            ],
        ]);
    }

    protected function generateFilename(string $extension, int $year, ?int $month, ?int $quarter): string
    {
        $periodPart = sprintf('%04d-%02d', $year, $month ?? 1);

        // ECL text files use .txt extension
        if ($extension === 'csv') {
            $extension = 'txt';
        }

        return "sss_{$this->getReportCode()}_{$periodPart}.{$extension}";
    }
}
