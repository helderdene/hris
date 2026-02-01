<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Base class for PhilHealth report generators.
 *
 * Provides common functionality for data retrieval and format conversion.
 */
abstract class BasePhilhealthReportGenerator
{
    /**
     * Get the report title.
     */
    abstract public function getTitle(): string;

    /**
     * Get the report code for filenames (e.g., 'rf1', 'er2', 'mdr').
     */
    abstract public function getReportCode(): string;

    /**
     * Get the report data.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    abstract public function getData(
        int $year,
        ?int $month = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array $departmentIds = null,
        ?int $limit = null
    ): array;

    /**
     * Get summary totals.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<string, mixed>
     */
    public function getSummary(
        int $year,
        ?int $month = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?array $departmentIds = null
    ): array {
        $result = $this->getData($year, $month, $startDate, $endDate, $departmentIds);

        return $result['totals'];
    }

    /**
     * Get column headers for Excel export.
     *
     * @return array<string>
     */
    abstract protected function getExcelHeaders(): array;

    /**
     * Map a data row to Excel columns.
     *
     * @param  mixed  $row
     * @return array<mixed>
     */
    abstract protected function mapRowToExcel($row, int $index): array;

    /**
     * Get the PDF view name.
     */
    abstract protected function getPdfView(): string;

    /**
     * Generate Excel output.
     *
     * @param  array{data: Collection, totals: array<string, mixed>}  $data
     * @return array{content: string, filename: string, contentType: string}
     */
    public function toExcel(array $data, int $year, ?int $month, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $tenant = tenant();
        $companyName = $tenant?->name ?? 'Company';
        $philhealthNumber = $tenant?->business_info['philhealth_number'] ?? '';

        // Title rows
        $sheet->setCellValue('A1', $companyName);
        $sheet->setCellValue('A2', $this->getTitle());
        $sheet->setCellValue('A3', $this->getPeriodLabel($year, $month, $startDate, $endDate));
        if ($philhealthNumber) {
            $sheet->setCellValue('A4', "PhilHealth Employer No: {$philhealthNumber}");
        }

        // Style title rows
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);

        // Headers
        $headers = $this->getExcelHeaders();
        $headerRow = $philhealthNumber ? 6 : 5;
        $col = 'A';

        foreach ($headers as $header) {
            $sheet->setCellValue($col.$headerRow, $header);
            $col++;
        }

        // Style header row
        $lastCol = $this->getColumnLetter(count($headers) - 1);
        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $currentRow = $headerRow + 1;
        $index = 1;
        foreach ($data['data'] as $row) {
            $col = 'A';
            foreach ($this->mapRowToExcel($row, $index) as $value) {
                $sheet->setCellValue($col.$currentRow, $value);
                $col++;
            }
            $currentRow++;
            $index++;
        }

        // Auto-size columns
        foreach (range('A', $lastCol) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Add totals row
        $this->addExcelTotals($sheet, $data['totals'], $currentRow, count($headers));

        // Write to memory
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'philhealth_report_');
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        $filename = $this->generateFilename('xlsx', $year, $month, $startDate, $endDate);

        return [
            'content' => $content,
            'filename' => $filename,
            'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * Generate PDF output.
     *
     * @param  array{data: Collection, totals: array<string, mixed>}  $data
     * @return array{content: string, filename: string, contentType: string}
     */
    public function toPdf(array $data, int $year, ?int $month, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $tenant = tenant();

        $viewData = [
            'company' => [
                'name' => $tenant?->name ?? 'Company',
                'address' => $tenant?->business_info['address'] ?? null,
                'tin' => $tenant?->business_info['tin'] ?? null,
                'philhealth_number' => $tenant?->business_info['philhealth_number'] ?? null,
            ],
            'report' => [
                'title' => $this->getTitle(),
                'period' => $this->getPeriodLabel($year, $month, $startDate, $endDate),
            ],
            'data' => $data['data'],
            'totals' => $data['totals'],
            'generated_at' => now()->format('F j, Y g:i A'),
        ];

        $pdf = Pdf::loadView($this->getPdfView(), $viewData);
        $pdf->setPaper('A4', 'landscape');

        $filename = $this->generateFilename('pdf', $year, $month, $startDate, $endDate);

        return [
            'content' => $pdf->output(),
            'filename' => $filename,
            'contentType' => 'application/pdf',
        ];
    }

    /**
     * Add totals row to Excel sheet.
     *
     * @param  array<string, mixed>  $totals
     */
    protected function addExcelTotals($sheet, array $totals, int $row, int $columnCount): void
    {
        $sheet->setCellValue('A'.$row, 'TOTALS');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
    }

    /**
     * Generate a descriptive filename.
     */
    protected function generateFilename(string $extension, int $year, ?int $month, ?Carbon $startDate, ?Carbon $endDate): string
    {
        $reportCode = $this->getReportCode();

        if ($startDate && $endDate) {
            $periodPart = $startDate->format('Y-m-d').'_to_'.$endDate->format('Y-m-d');
        } elseif ($month) {
            $periodPart = sprintf('%04d-%02d', $year, $month);
        } else {
            $periodPart = (string) $year;
        }

        return "philhealth_{$reportCode}_{$periodPart}.{$extension}";
    }

    /**
     * Get period label for display.
     */
    protected function getPeriodLabel(int $year, ?int $month, ?Carbon $startDate, ?Carbon $endDate): string
    {
        if ($startDate && $endDate) {
            return $startDate->format('F j, Y').' - '.$endDate->format('F j, Y');
        }

        if ($month) {
            $monthName = date('F', mktime(0, 0, 0, $month, 1));

            return "{$monthName} {$year}";
        }

        return (string) $year;
    }

    /**
     * Format a number as currency.
     */
    protected function formatCurrency(float $amount): string
    {
        return number_format($amount, 2);
    }

    /**
     * Get Excel column letter from index.
     */
    protected function getColumnLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intval($index / 26) - 1;
        }

        return $letter;
    }
}
