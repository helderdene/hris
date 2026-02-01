<?php

namespace App\Services\Reports;

use App\Enums\BirReportType;
use App\Models\Bir2316Certificate;
use Illuminate\Support\Collection;

/**
 * Main orchestrator for BIR compliance report generation.
 *
 * Delegates to specific report generators based on report type
 * and handles output format conversion.
 */
class BirReportService
{
    public function __construct(
        protected Bir1601cReportGenerator $form1601cGenerator,
        protected Bir1604cfReportGenerator $form1604cfGenerator,
        protected Bir2316ReportGenerator $form2316Generator,
        protected BirAlphalistReportGenerator $alphalistGenerator
    ) {}

    /**
     * Generate report data for preview.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{data: Collection, totals: array<string, mixed>}
     */
    public function preview(
        BirReportType $reportType,
        int $year,
        ?int $month = null,
        ?int $quarter = null,
        ?array $departmentIds = null,
        int $limit = 50,
        ?string $schedule = null
    ): array {
        return $this->getGenerator($reportType, $schedule)->getData(
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
        BirReportType $reportType,
        int $year,
        ?int $month = null,
        ?int $quarter = null,
        ?array $departmentIds = null,
        ?string $schedule = null
    ): array {
        return $this->getGenerator($reportType, $schedule)->getSummary(
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
        BirReportType $reportType,
        string $format,
        int $year,
        ?int $month = null,
        ?int $quarter = null,
        ?array $departmentIds = null,
        ?string $schedule = null
    ): array {
        $generator = $this->getGenerator($reportType, $schedule);
        $data = $generator->getData(
            year: $year,
            month: $month,
            quarter: $quarter,
            departmentIds: $departmentIds
        );

        // Handle template-based formats for BIR 2316
        if ($reportType === BirReportType::Form2316 && in_array($format, ['xlsx-template', 'pdf-template'])) {
            return match ($format) {
                'xlsx-template' => $this->form2316Generator->toFilledExcel($data, $year),
                'pdf-template' => $this->form2316Generator->toFilledPdf($data, $year),
            };
        }

        return match ($format) {
            'xlsx' => $generator->toExcel($data, $year, $month, $quarter),
            'pdf' => $generator->toPdf($data, $year, $month, $quarter),
            'csv' => $generator->toCsv($data, $year, $month, $quarter),
            'dat' => $generator->toDat($data, $year, $month, $quarter),
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
    protected function getGenerator(BirReportType $reportType, ?string $schedule = null): BaseBirReportGenerator
    {
        return match ($reportType) {
            BirReportType::Form1601c => $this->form1601cGenerator,
            BirReportType::Form1604cf => $this->form1604cfGenerator,
            BirReportType::Form2316 => $this->form2316Generator,
            BirReportType::Alphalist => $this->getAlphalistGenerator($schedule),
        };
    }

    /**
     * Get the Alphalist generator configured with the specified schedule.
     */
    protected function getAlphalistGenerator(?string $schedule = null): BirAlphalistReportGenerator
    {
        $schedule = $schedule ?? '7.1';
        $this->alphalistGenerator->setSchedule($schedule);

        return $this->alphalistGenerator;
    }

    /**
     * Generate a single employee's BIR 2316 certificate PDF.
     *
     * @return array{content: string, filename: string, contentType: string}
     */
    public function generate2316ForEmployee(int $employeeId, int $year): array
    {
        return $this->form2316Generator->generateEmployeePdf($employeeId, $year);
    }

    /**
     * Generate a single employee's BIR 2316 using the official template (Excel).
     *
     * @return array{content: string, filename: string, contentType: string}
     */
    public function generate2316TemplateExcel(int $employeeId, int $year): array
    {
        return $this->form2316Generator->generateEmployeeTemplateExcel($employeeId, $year);
    }

    /**
     * Generate a single employee's BIR 2316 using the official template (PDF).
     *
     * @return array{content: string, filename: string, contentType: string}
     */
    public function generate2316TemplatePdf(int $employeeId, int $year): array
    {
        return $this->form2316Generator->generateEmployeeTemplatePdf($employeeId, $year);
    }

    /**
     * Check if the official BIR 2316 template is available.
     */
    public function hasOfficialTemplate(): bool
    {
        return $this->form2316Generator->hasOfficialTemplate();
    }

    /**
     * Generate and store BIR 2316 certificates for all employees with payroll data.
     *
     * @param  array<int>|null  $departmentIds
     * @return array{generated_count: int, certificates: Collection}
     */
    public function generateBulk2316(int $year, ?array $departmentIds = null, ?int $userId = null): array
    {
        $data = $this->form2316Generator->getData(
            year: $year,
            departmentIds: $departmentIds
        );

        $generatedCertificates = collect();

        foreach ($data['data'] as $employeeData) {
            // Generate and store the certificate
            $certificate = Bir2316Certificate::updateOrCreate(
                [
                    'employee_id' => $employeeData->employee_id,
                    'tax_year' => $year,
                ],
                [
                    'compensation_data' => (array) $employeeData,
                    'generated_at' => now(),
                    'generated_by' => $userId,
                ]
            );

            $generatedCertificates->push($certificate);
        }

        return [
            'generated_count' => $generatedCertificates->count(),
            'certificates' => $generatedCertificates,
        ];
    }

    /**
     * Get available BIR 2316 certificates for an employee.
     *
     * @return Collection<int, Bir2316Certificate>
     */
    public function getEmployee2316Certificates(int $employeeId): Collection
    {
        return Bir2316Certificate::query()
            ->where('employee_id', $employeeId)
            ->orderByDesc('tax_year')
            ->get();
    }
}
