<?php

namespace App\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Base class for Pag-IBIG report generators.
 *
 * Provides common functionality for data retrieval and format conversion.
 */
abstract class BasePagibigReportGenerator
{
    /**
     * Get the report title.
     */
    abstract public function getTitle(): string;

    /**
     * Get the report code for filenames (e.g., 'mcrf', 'stl', 'hdl').
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
        ?int $quarter = null,
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
        ?int $quarter = null,
        ?array $departmentIds = null
    ): array {
        $result = $this->getData($year, $month, $quarter, $departmentIds);

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
    abstract protected function mapRowToExcel($row): array;

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
    public function toExcel(array $data, int $year, ?int $month, ?int $quarter): array
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $tenant = tenant();
        $companyName = $tenant?->name ?? 'Company';
        $pagibigNumber = $tenant?->business_info['pagibig_number'] ?? '';

        // Title rows
        $sheet->setCellValue('A1', $companyName);
        $sheet->setCellValue('A2', $this->getTitle());
        $sheet->setCellValue('A3', $this->getPeriodLabel($year, $month, $quarter));
        if ($pagibigNumber) {
            $sheet->setCellValue('A4', "Pag-IBIG Employer No: {$pagibigNumber}");
        }

        // Style title rows
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);

        // Headers
        $headers = $this->getExcelHeaders();
        $headerRow = $pagibigNumber ? 6 : 5;
        $col = 'A';

        foreach ($headers as $header) {
            $sheet->setCellValue($col.$headerRow, $header);
            $col++;
        }

        // Style header row
        $lastCol = chr(ord('A') + count($headers) - 1);
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
        foreach ($data['data'] as $row) {
            $col = 'A';
            foreach ($this->mapRowToExcel($row) as $value) {
                $sheet->setCellValue($col.$currentRow, $value);
                $col++;
            }
            $currentRow++;
        }

        // Auto-size columns
        foreach (range('A', $lastCol) as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Add totals row
        $this->addExcelTotals($sheet, $data['totals'], $currentRow, count($headers));

        // Write to memory
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'pagibig_report_');
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        $filename = $this->generateFilename('xlsx', $year, $month, $quarter);

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
    public function toPdf(array $data, int $year, ?int $month, ?int $quarter): array
    {
        $tenant = tenant();

        $viewData = [
            'company' => [
                'name' => $tenant?->name ?? 'Company',
                'address' => $tenant?->business_info['address'] ?? null,
                'tin' => $tenant?->business_info['tin'] ?? null,
                'pagibig_number' => $tenant?->business_info['pagibig_number'] ?? null,
            ],
            'report' => [
                'title' => $this->getTitle(),
                'period' => $this->getPeriodLabel($year, $month, $quarter),
            ],
            'data' => $data['data'],
            'totals' => $data['totals'],
            'generated_at' => now()->format('F j, Y g:i A'),
        ];

        $pdf = Pdf::loadView($this->getPdfView(), $viewData);
        $pdf->setPaper('A4', 'landscape');

        $filename = $this->generateFilename('pdf', $year, $month, $quarter);

        return [
            'content' => $pdf->output(),
            'filename' => $filename,
            'contentType' => 'application/pdf',
        ];
    }

    /**
     * Generate CSV output.
     *
     * @param  array{data: Collection, totals: array<string, mixed>}  $data
     * @return array{content: string, filename: string, contentType: string}
     */
    public function toCsv(array $data, int $year, ?int $month, ?int $quarter): array
    {
        $output = fopen('php://temp', 'r+');

        // Headers
        fputcsv($output, $this->getExcelHeaders());

        // Data rows
        foreach ($data['data'] as $row) {
            fputcsv($output, $this->mapRowToExcel($row));
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        $filename = $this->generateFilename('csv', $year, $month, $quarter);

        return [
            'content' => $content,
            'filename' => $filename,
            'contentType' => 'text/csv',
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
    protected function generateFilename(string $extension, int $year, ?int $month, ?int $quarter): string
    {
        $reportCode = $this->getReportCode();

        $periodPart = $month
            ? sprintf('%04d-%02d', $year, $month)
            : sprintf('%04d-Q%d', $year, $quarter ?? 1);

        return "pagibig_{$reportCode}_{$periodPart}.{$extension}";
    }

    /**
     * Get period label for display.
     */
    protected function getPeriodLabel(int $year, ?int $month, ?int $quarter): string
    {
        if ($month) {
            $monthName = date('F', mktime(0, 0, 0, $month, 1));

            return "{$monthName} {$year}";
        }

        if ($quarter) {
            $quarterLabel = match ($quarter) {
                1 => '1st Quarter (January - March)',
                2 => '2nd Quarter (April - June)',
                3 => '3rd Quarter (July - September)',
                4 => '4th Quarter (October - December)',
            };

            return "{$quarterLabel} {$year}";
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
}
