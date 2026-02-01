<?php

namespace App\Http\Controllers\Api;

use App\Enums\PhilhealthReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePhilhealthReportRequest;
use App\Services\Reports\PhilhealthReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API controller for PhilHealth compliance report generation.
 *
 * Handles preview, summary, and file generation endpoints.
 */
class PhilhealthReportController extends Controller
{
    public function __construct(protected PhilhealthReportService $reportService) {}

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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer'],
        ]);

        $reportType = PhilhealthReportType::tryFrom($validated['report_type']);

        if (! $reportType) {
            return response()->json(['message' => 'Invalid report type'], 422);
        }

        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
        $endDate = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : null;

        $data = $this->reportService->preview(
            reportType: $reportType,
            year: $validated['year'],
            month: $validated['month'] ?? null,
            startDate: $startDate,
            endDate: $endDate,
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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer'],
        ]);

        $reportType = PhilhealthReportType::tryFrom($validated['report_type']);

        if (! $reportType) {
            return response()->json(['message' => 'Invalid report type'], 422);
        }

        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
        $endDate = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : null;

        $summary = $this->reportService->summary(
            reportType: $reportType,
            year: $validated['year'],
            month: $validated['month'] ?? null,
            startDate: $startDate,
            endDate: $endDate,
            departmentIds: $validated['department_ids'] ?? null
        );

        return response()->json($summary);
    }

    /**
     * Generate and download a report file.
     */
    public function generate(GeneratePhilhealthReportRequest $request): StreamedResponse
    {
        $validated = $request->validated();

        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
        $endDate = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : null;

        $result = $this->reportService->generate(
            reportType: $request->getReportType(),
            format: $validated['format'],
            year: $validated['year'],
            month: $validated['month'] ?? null,
            startDate: $startDate,
            endDate: $endDate,
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
