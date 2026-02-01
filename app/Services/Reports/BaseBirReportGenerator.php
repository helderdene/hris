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
 * Base class for BIR report generators.
 *
 * Provides common functionality for data retrieval and format conversion.
 */
abstract class BaseBirReportGenerator
{
    /**
     * Get the report title.
     */
    abstract public function getTitle(): string;

    /**
     * Get the report code for filenames (e.g., '1601c').
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
        $tin = $tenant?->business_info['tin'] ?? '';

        // Title rows
        $sheet->setCellValue('A1', $companyName);
        $sheet->setCellValue('A2', $this->getTitle());
        $sheet->setCellValue('A3', $this->getPeriodLabel($year, $month, $quarter));
        if ($tin) {
            $sheet->setCellValue('A4', "TIN: {$tin}");
        }

        // Style title rows
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);

        // Headers
        $headers = $this->getExcelHeaders();
        $headerRow = $tin ? 6 : 5;
        $col = 'A';

        foreach ($headers as $header) {
            $sheet->setCellValue($col.$headerRow, $header);
            $col++;
        }

        // Style header row with BIR red theme
        $lastCol = chr(ord('A') + count($headers) - 1);
        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '8B0000'], // Dark red for BIR
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
        $tempFile = tempnam(sys_get_temp_dir(), 'bir_report_');
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
     * Generate DAT output for BIR eFiling submission.
     *
     * DAT files are pipe-delimited text files used by BIR's electronic
     * filing and payment system (eFPS).
     *
     * @param  array{data: Collection, totals: array<string, mixed>}  $data
     * @return array{content: string, filename: string, contentType: string}
     */
    public function toDat(array $data, int $year, ?int $month, ?int $quarter): array
    {
        $lines = [];

        // Add header line (varies by report type)
        $header = $this->getDatHeader($year, $month, $quarter);
        if ($header) {
            $lines[] = $header;
        }

        // Data rows
        foreach ($data['data'] as $row) {
            $lines[] = $this->mapRowToDat($row);
        }

        // Add control totals line if applicable
        $footer = $this->getDatFooter($data['totals'], $year);
        if ($footer) {
            $lines[] = $footer;
        }

        $content = implode("\r\n", $lines);
        $filename = $this->generateFilename('dat', $year, $month, $quarter);

        return [
            'content' => $content,
            'filename' => $filename,
            'contentType' => 'text/plain',
        ];
    }

    /**
     * Map a data row to DAT format (pipe-delimited string).
     *
     * Override this method in subclasses to provide report-specific
     * DAT formatting for BIR eFiling.
     *
     * @param  mixed  $row
     *
     * @throws \RuntimeException If the subclass does not support DAT export
     */
    protected function mapRowToDat($row): string
    {
        throw new \RuntimeException('DAT export is not supported for this report type.');
    }

    /**
     * Get the DAT file header line.
     *
     * Override this method in subclasses to provide a header line
     * containing employer/company information.
     */
    protected function getDatHeader(int $year, ?int $month, ?int $quarter): ?string
    {
        return null;
    }

    /**
     * Get the DAT file footer/control totals line.
     *
     * Override this method in subclasses to provide control totals
     * required by BIR eFiling.
     *
     * @param  array<string, mixed>  $totals
     */
    protected function getDatFooter(array $totals, int $year): ?string
    {
        return null;
    }

    /**
     * Check if this generator supports DAT export.
     */
    public function supportsDat(): bool
    {
        try {
            // Try to call mapRowToDat with a dummy object to check if it's implemented
            $this->mapRowToDat(new \stdClass);

            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
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

        if ($month) {
            $periodPart = sprintf('%04d-%02d', $year, $month);
        } elseif ($quarter) {
            $periodPart = sprintf('%04d-Q%d', $year, $quarter);
        } else {
            $periodPart = sprintf('%04d', $year);
        }

        return "bir_{$reportCode}_{$periodPart}.{$extension}";
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
