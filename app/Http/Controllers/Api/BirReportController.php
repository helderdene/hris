<?php

namespace App\Http\Controllers\Api;

use App\Enums\BirReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Download2316Request;
use App\Http\Requests\Api\GenerateBulk2316Request;
use App\Http\Requests\Api\PreviewBirReportRequest;
use App\Http\Requests\Api\SummaryBirReportRequest;
use App\Http\Requests\GenerateBirReportRequest;
use App\Services\Reports\BirReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API controller for BIR compliance report generation.
 *
 * Handles preview, summary, and file generation endpoints.
 */
class BirReportController extends Controller
{
    public function __construct(protected BirReportService $reportService) {}

    /**
     * Get available periods for report generation.
     */
    public function periods(): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $periods = $this->reportService->getAvailablePeriods();

        return response()->json($periods);
    }

    /**
     * Preview report data (limited rows for UI display).
     */
    public function preview(PreviewBirReportRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();

        $reportType = BirReportType::tryFrom($validated['report_type']);

        if (! $reportType) {
            return response()->json(['message' => 'Invalid report type'], 422);
        }

        $data = $this->reportService->preview(
            reportType: $reportType,
            year: $validated['year'],
            month: $validated['month'] ?? null,
            quarter: $validated['quarter'] ?? null,
            departmentIds: $validated['department_ids'] ?? null,
            limit: 50,
            schedule: $validated['schedule'] ?? null
        );

        return response()->json([
            'data' => $data['data']->values(),
            'totals' => $data['totals'],
            'preview_limit' => 50,
        ]);
    }

    /**
     * Get summary totals for a report.
     */
    public function summary(SummaryBirReportRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();

        $reportType = BirReportType::tryFrom($validated['report_type']);

        if (! $reportType) {
            return response()->json(['message' => 'Invalid report type'], 422);
        }

        $summary = $this->reportService->summary(
            reportType: $reportType,
            year: $validated['year'],
            month: $validated['month'] ?? null,
            quarter: $validated['quarter'] ?? null,
            departmentIds: $validated['department_ids'] ?? null,
            schedule: $validated['schedule'] ?? null
        );

        return response()->json($summary);
    }

    /**
     * Generate and download a report file.
     */
    public function generate(GenerateBirReportRequest $request): StreamedResponse
    {
        $validated = $request->validated();

        $result = $this->reportService->generate(
            reportType: $request->getReportType(),
            format: $validated['format'],
            year: $validated['year'],
            month: $validated['month'] ?? null,
            quarter: $validated['quarter'] ?? null,
            departmentIds: $validated['department_ids'] ?? null,
            schedule: $request->getSchedule()
        );

        return response()->streamDownload(
            function () use ($result) {
                echo $result['content'];
            },
            $result['filename'],
            [
                'Content-Type' => $result['contentType'],
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
                'X-Filename' => $result['filename'],
                'Access-Control-Expose-Headers' => 'Content-Disposition, X-Filename',
            ]
        );
    }

    /**
     * Generate bulk BIR 2316 certificates for all employees.
     */
    public function generateBulk2316(GenerateBulk2316Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();

        $result = $this->reportService->generateBulk2316(
            year: $validated['year'],
            departmentIds: $validated['department_ids'] ?? null,
            userId: $request->user()?->id
        );

        return response()->json([
            'message' => "Generated {$result['generated_count']} BIR 2316 certificates for {$validated['year']}.",
            'generated_count' => $result['generated_count'],
        ]);
    }

    /**
     * Download a single employee's BIR 2316 certificate.
     */
    public function download2316(Download2316Request $request, int $employeeId): StreamedResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();

        $result = $this->reportService->generate2316ForEmployee(
            employeeId: $employeeId,
            year: $validated['year']
        );

        return response()->streamDownload(
            function () use ($result) {
                echo $result['content'];
            },
            $result['filename'],
            [
                'Content-Type' => $result['contentType'],
                'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
                'X-Filename' => $result['filename'],
                'Access-Control-Expose-Headers' => 'Content-Disposition, X-Filename',
            ]
        );
    }
}
