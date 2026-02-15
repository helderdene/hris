<?php

namespace App\Services\Dtr;

use App\Models\DailyTimeRecord;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service for exporting employee DTR records to Excel and PDF formats.
 */
class DtrExportService
{
    /**
     * Export DTR records to Excel format.
     *
     * @param  Collection<int, DailyTimeRecord>  $records
     * @param  array{total_work_hours: float, total_late_hours: float, total_undertime_hours: float, total_overtime_hours: float}  $summary
     */
    public function exportXlsx(
        Employee $employee,
        Collection $records,
        array $summary,
        string $dateFrom,
        string $dateTo,
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($employee, $records, $summary, $dateFrom, $dateTo);
        $filename = $this->generateFilename($employee, 'xlsx');

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Export DTR records to PDF format.
     *
     * @param  Collection<int, DailyTimeRecord>  $records
     * @param  array{total_work_hours: float, total_late_hours: float, total_undertime_hours: float, total_overtime_hours: float}  $summary
     */
    public function exportPdf(
        Employee $employee,
        Collection $records,
        array $summary,
        string $dateFrom,
        string $dateTo,
    ): StreamedResponse {
        $tenant = tenant();
        $filename = $this->generateFilename($employee, 'pdf');

        $data = [
            'company' => [
                'name' => $tenant?->name ?? 'Company',
            ],
            'employee' => [
                'name' => $employee->full_name,
                'number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
            ],
            'period' => [
                'date_from' => Carbon::parse($dateFrom)->format('F j, Y'),
                'date_to' => Carbon::parse($dateTo)->format('F j, Y'),
            ],
            'records' => $records->map(fn (DailyTimeRecord $record) => $this->mapRecordToRow($record)),
            'summary' => $summary,
            'generated_at' => now()->format('F j, Y g:i A'),
        ];

        $pdf = Pdf::loadView('pdf.dtr-report', $data);
        $pdf->setPaper('A4', 'landscape');

        return new StreamedResponse(function () use ($pdf) {
            echo $pdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Build the spreadsheet with DTR data.
     *
     * @param  Collection<int, DailyTimeRecord>  $records
     * @param  array<string, mixed>  $summary
     */
    private function buildSpreadsheet(
        Employee $employee,
        Collection $records,
        array $summary,
        string $dateFrom,
        string $dateTo,
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('DTR');

        $tenant = tenant();
        $companyName = $tenant?->name ?? 'Company';

        // Title rows
        $sheet->setCellValue('A1', $companyName);
        $sheet->setCellValue('A2', "Daily Time Record - {$employee->full_name} ({$employee->employee_number})");
        $sheet->setCellValue('A3', 'Period: '.Carbon::parse($dateFrom)->format('F j, Y').' to '.Carbon::parse($dateTo)->format('F j, Y'));
        $sheet->setCellValue('A4', 'Generated: '.now()->format('F j, Y g:i A'));

        // Style title rows
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);

        // Headers
        $headers = $this->getHeaders();
        $headerRow = 6;
        $col = 'A';

        foreach ($headers as $header) {
            $sheet->setCellValue($col.$headerRow, $header);
            $col++;
        }

        // Style header row
        $lastCol = $this->getColumnLetter(count($headers) - 1);
        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $currentRow = $headerRow + 1;
        foreach ($records as $record) {
            $col = 'A';
            $rowData = $this->mapRecordToRow($record);
            $values = [
                $rowData['date'],
                $rowData['day'],
                $rowData['status'],
                $rowData['time_in'],
                $rowData['time_out'],
                $rowData['work_hours'],
                $rowData['late'],
                $rowData['undertime'],
                $rowData['overtime'],
                $rowData['night_diff'],
                $rowData['remarks'],
            ];

            foreach ($values as $value) {
                $sheet->setCellValue($col.$currentRow, $value);
                $col++;
            }
            $currentRow++;
        }

        // Add borders to data
        if ($currentRow > $headerRow + 1) {
            $dataRange = 'A'.($headerRow + 1).":{$lastCol}".($currentRow - 1);
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);
        }

        // Summary row
        $currentRow++;
        $sheet->setCellValue('A'.$currentRow, 'Summary');
        $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);
        $currentRow++;

        $summaryData = [
            'Total Work Hours' => number_format($summary['time_summary']['total_work_hours'] ?? 0, 1),
            'Total Late' => number_format($summary['late_undertime']['total_late_hours'] ?? 0, 1).'h',
            'Total Undertime' => number_format($summary['late_undertime']['total_undertime_hours'] ?? 0, 1).'h',
            'Total Overtime' => number_format($summary['overtime']['total_overtime_hours'] ?? 0, 1).'h',
            'Present Days' => $summary['attendance']['present_days'] ?? 0,
            'Absent Days' => $summary['attendance']['absent_days'] ?? 0,
        ];

        foreach ($summaryData as $label => $value) {
            $sheet->setCellValue('A'.$currentRow, $label);
            $sheet->setCellValue('B'.$currentRow, $value);
            $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);
            $currentRow++;
        }

        // Auto-size columns
        foreach (range('A', $lastCol) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Get the column headers.
     *
     * @return array<string>
     */
    private function getHeaders(): array
    {
        return [
            'Date',
            'Day',
            'Status',
            'Time In',
            'Time Out',
            'Work Hours',
            'Late',
            'Undertime',
            'Overtime',
            'Night Diff',
            'Remarks',
        ];
    }

    /**
     * Map a DTR record to a row of display data.
     *
     * @return array<string, string>
     */
    private function mapRecordToRow(DailyTimeRecord $record): array
    {
        return [
            'date' => $record->date?->format('Y-m-d') ?? '',
            'day' => $record->date?->englishDayOfWeek ?? '',
            'status' => $record->status?->label() ?? '',
            'time_in' => $record->first_in?->format('H:i') ?? '',
            'time_out' => $record->last_out?->format('H:i') ?? '',
            'work_hours' => $record->total_work_minutes > 0
                ? number_format($record->total_work_minutes / 60, 1)
                : '',
            'late' => $record->late_minutes > 0 ? $record->late_formatted : '',
            'undertime' => $record->undertime_minutes > 0 ? $record->undertime_formatted : '',
            'overtime' => $record->overtime_minutes > 0 ? $record->overtime_formatted : '',
            'night_diff' => $record->night_diff_minutes > 0
                ? sprintf('%d:%02d', intdiv($record->night_diff_minutes, 60), $record->night_diff_minutes % 60)
                : '',
            'remarks' => $record->remarks ?? '',
        ];
    }

    /**
     * Get column letter from index.
     */
    private function getColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(($index % 26) + 65).$letter;
            $index = intval($index / 26) - 1;
        }

        return $letter;
    }

    /**
     * Generate the export filename.
     */
    private function generateFilename(Employee $employee, string $extension): string
    {
        return "dtr_{$employee->employee_number}_".now()->format('Y-m-d').".{$extension}";
    }
}
