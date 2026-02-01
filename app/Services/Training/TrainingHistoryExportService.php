<?php

namespace App\Services\Training;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service for exporting training history to Excel format.
 */
class TrainingHistoryExportService
{
    /**
     * Export training history enrollments to Excel.
     *
     * @param  Collection<int, \App\Models\TrainingEnrollment>  $enrollments
     */
    public function export(Collection $enrollments): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet($enrollments);

        $filename = $this->generateFilename();

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
     * Build the spreadsheet with training history data.
     *
     * @param  Collection<int, \App\Models\TrainingEnrollment>  $enrollments
     */
    private function buildSpreadsheet(Collection $enrollments): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Training History');

        $tenant = tenant();
        $companyName = $tenant?->name ?? 'Company';

        // Title rows
        $sheet->setCellValue('A1', $companyName);
        $sheet->setCellValue('A2', 'Training History Report');
        $sheet->setCellValue('A3', 'Generated: '.now()->format('F j, Y g:i A'));

        // Style title rows
        $sheet->getStyle('A1:A3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);

        // Headers
        $headers = $this->getHeaders();
        $headerRow = 5;
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
                'startColor' => ['rgb' => '2563EB'], // Blue theme
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $currentRow = $headerRow + 1;
        foreach ($enrollments as $enrollment) {
            $col = 'A';
            foreach ($this->mapEnrollmentToRow($enrollment) as $value) {
                $sheet->setCellValue($col.$currentRow, $value);
                $col++;
            }
            $currentRow++;
        }

        // Auto-size columns
        foreach (range('A', $lastCol) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
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
        $sheet->setCellValue('A'.$currentRow, 'Total Records: '.$enrollments->count());
        $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);

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
            'Employee Number',
            'Employee Name',
            'Department',
            'Position',
            'Course Code',
            'Course Title',
            'Session Title',
            'Session Date',
            'Duration',
            'Location',
            'Trainer',
            'Enrollment Status',
            'Attended At',
            'Completion Status',
            'Assessment Score',
            'Certificate Number',
            'Certificate Issued',
            'Notes',
        ];
    }

    /**
     * Map an enrollment to a row of data.
     *
     * @return array<mixed>
     */
    private function mapEnrollmentToRow(\App\Models\TrainingEnrollment $enrollment): array
    {
        $employee = $enrollment->employee;
        $session = $enrollment->session;
        $course = $session?->course;
        $instructor = $session?->instructor;

        return [
            $employee?->employee_number ?? '',
            $employee?->full_name ?? '',
            $employee?->department?->name ?? '',
            $employee?->position?->name ?? '',
            $course?->code ?? '',
            $course?->title ?? '',
            $session?->display_title ?? '',
            $this->formatDateRange($session),
            $course?->formatted_duration ?? '',
            $session?->location ?? '',
            $instructor?->full_name ?? '',
            $enrollment->status->label(),
            $enrollment->attended_at?->format('Y-m-d H:i') ?? '',
            $enrollment->completion_status?->label() ?? '',
            $enrollment->assessment_score !== null ? number_format((float) $enrollment->assessment_score, 2).'%' : '',
            $enrollment->certificate_number ?? '',
            $enrollment->certificate_issued_at?->format('Y-m-d') ?? '',
            $enrollment->notes ?? '',
        ];
    }

    /**
     * Format the session date range.
     */
    private function formatDateRange(?\App\Models\TrainingSession $session): string
    {
        if (! $session || ! $session->start_date) {
            return '';
        }

        $start = $session->start_date->format('Y-m-d');

        if ($session->end_date && ! $session->start_date->isSameDay($session->end_date)) {
            return $start.' to '.$session->end_date->format('Y-m-d');
        }

        return $start;
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
    private function generateFilename(): string
    {
        return 'training_history_'.now()->format('Y-m-d_His').'.xlsx';
    }
}
