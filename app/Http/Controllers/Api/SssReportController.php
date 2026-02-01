<?php

namespace App\Http\Controllers\Api;

use App\Enums\SssReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateSssReportRequest;
use App\Services\Reports\SssReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API controller for SSS compliance report generation.
 *
 * Handles preview, summary, and file generation endpoints.
 */
class SssReportController extends Controller
{
    public function __construct(protected SssReportService $reportService) {}

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
    public function preview(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validate([
            'report_type' => ['required', 'string'],
            'year' => ['required', 'integer'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer'],
        ]);

        $reportType = SssReportType::tryFrom($validated['report_type']);

        if (! $reportType) {
            return response()->json(['message' => 'Invalid report type'], 422);
        }

        $data = $this->reportService->preview(
            reportType: $reportType,
            year: $validated['year'],
            month: $validated['month'] ?? null,
            quarter: $validated['quarter'] ?? null,
            departmentIds: $validated['department_ids'] ?? null,
            limit: 50
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
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $validated = $request->validate([
            'report_type' => ['required', 'string'],
            'year' => ['required', 'integer'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer'],
        ]);

        $reportType = SssReportType::tryFrom($validated['report_type']);

        if (! $reportType) {
            return response()->json(['message' => 'Invalid report type'], 422);
        }

        $summary = $this->reportService->summary(
            reportType: $reportType,
            year: $validated['year'],
            month: $validated['month'] ?? null,
            quarter: $validated['quarter'] ?? null,
            departmentIds: $validated['department_ids'] ?? null
        );

        return response()->json($summary);
    }

    /**
     * Generate and download a report file.
     */
    public function generate(GenerateSssReportRequest $request): StreamedResponse
    {
        $validated = $request->validated();

        $result = $this->reportService->generate(
            reportType: $request->getReportType(),
            format: $validated['format'],
            year: $validated['year'],
            month: $validated['month'] ?? null,
            quarter: $validated['quarter'] ?? null,
            departmentIds: $validated['department_ids'] ?? null
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
