<?php

namespace App\Services\Reports;

use App\Enums\PagibigReportType;
use Illuminate\Support\Collection;

/**
 * Main orchestrator for Pag-IBIG compliance report generation.
 *
 * Delegates to specific report generators based on report type
 * and handles output format conversion.
 */
class PagibigReportService
{
    public function __construct(
        protected PagibigMcrfReportGenerator $mcrfGenerator,
        protected PagibigStlReportGenerator $stlGenerator,
        protected PagibigHdlReportGenerator $hdlGenerator
    ) {}

    /**
     * Generate report data for preview.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    public function preview(
        PagibigReportType $reportType,
        int $year,
        ?int $month = null,
        ?int $quarter = null,
        ?array $departmentIds = null,
        int $limit = 50
    ): array {
        return $this->getGenerator($reportType)->getData(
            year: $year,
            month: $month,
            quarter: $quarter,
            departmentIds: $departmentIds,
            limit: $limit
        );
    }

    /**
     * Get summary totals for a report.
     *
     * @param  array<int>|null  $departmentIds
     * @return array<string, mixed>
     */
    public function summary(
        PagibigReportType $reportType,
        int $year,
        ?int $month = null,
        ?int $quarter = null,
        ?array $departmentIds = null
    ): array {
        return $this->getGenerator($reportType)->getSummary(
            year: $year,
            month: $month,
            quarter: $quarter,
            departmentIds: $departmentIds
        );
    }

    /**
     * Generate and return report file content.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{content: string, filename: string, contentType: string}
     */
    public function generate(
        PagibigReportType $reportType,
        string $format,
        int $year,
        ?int $month = null,
        ?int $quarter = null,
        ?array $departmentIds = null
    ): array {
        $generator = $this->getGenerator($reportType);
        $data = $generator->getData(
            year: $year,
            month: $month,
            quarter: $quarter,
            departmentIds: $departmentIds
        );

        return match ($format) {
            'xlsx' => $generator->toExcel($data, $year, $month, $quarter),
            'pdf' => $generator->toPdf($data, $year, $month, $quarter),
            'csv' => $generator->toCsv($data, $year, $month, $quarter),
            default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
        };
    }

    /**
     * Get available periods for report generation.
     *
     * @return array{years: array<int>, months: array<array{value: int, label: string}>}
     */
    public function getAvailablePeriods(): array
    {
        $currentYear = (int) now()->format('Y');
        $years = range($currentYear, $currentYear - 5);

        $months = [
            ['value' => 1, 'label' => 'January'],
            ['value' => 2, 'label' => 'February'],
            ['value' => 3, 'label' => 'March'],
            ['value' => 4, 'label' => 'April'],
            ['value' => 5, 'label' => 'May'],
            ['value' => 6, 'label' => 'June'],
            ['value' => 7, 'label' => 'July'],
            ['value' => 8, 'label' => 'August'],
            ['value' => 9, 'label' => 'September'],
            ['value' => 10, 'label' => 'October'],
            ['value' => 11, 'label' => 'November'],
            ['value' => 12, 'label' => 'December'],
        ];

        return compact('years', 'months');
    }

    /**
     * Get the appropriate generator for the report type.
     */
    protected function getGenerator(PagibigReportType $reportType): BasePagibigReportGenerator
    {
        return match ($reportType) {
            PagibigReportType::Mcrf => $this->mcrfGenerator,
            PagibigReportType::Stl => $this->stlGenerator,
            PagibigReportType::Hdl => $this->hdlGenerator,
        };
    }
}
