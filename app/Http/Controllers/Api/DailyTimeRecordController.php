<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CalculateDtrRangeRequest;
use App\Http\Requests\Api\CalculateDtrRequest;
use App\Http\Requests\Api\ResolveDtrReviewRequest;
use App\Http\Requests\DtrFilterRequest;
use App\Http\Resources\DailyTimeRecordResource;
use App\Http\Resources\DtrPeriodSummaryResource;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Services\Dtr\DtrCalculationService;
use App\Services\Dtr\DtrPeriodAggregator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DailyTimeRecordController extends Controller
{
    public function __construct(
        protected DtrCalculationService $calculationService,
        protected DtrPeriodAggregator $aggregator
    ) {}

    /**
     * Display a listing of DTR records.
     *
     * Supports filtering by employee_id, department_id, date range, status, and needs_review.
     */
    public function index(DtrFilterRequest $request): AnonymousResourceCollection
    {
        $query = DailyTimeRecord::query()
            ->with(['employee', 'workSchedule'])
            ->orderBy('date', 'desc')
            ->orderBy('employee_id');

        // Filter by employee
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // Filter by department
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by needs_review
        if ($request->filled('needs_review')) {
            $query->where('needs_review', $request->boolean('needs_review'));
        }

        // Filter by pending overtime
        if ($request->boolean('overtime_pending')) {
            $query->where('overtime_minutes', '>', 0)
                ->where('overtime_approved', false);
        }

        $perPage = $request->input('per_page', 25);

        return DailyTimeRecordResource::collection($query->paginate($perPage));
    }

    /**
     * Display the specified DTR record.
     */
    public function show(DailyTimeRecord $dailyTimeRecord): DailyTimeRecordResource
    {
        $dailyTimeRecord->load(['employee', 'workSchedule', 'punches.attendanceLog']);

        return new DailyTimeRecordResource($dailyTimeRecord);
    }

    /**
     * Get DTR records for a specific employee.
     */
    public function employeeDtr(DtrFilterRequest $request, Employee $employee): AnonymousResourceCollection
    {
        $query = DailyTimeRecord::query()
            ->where('employee_id', $employee->id)
            ->with(['workSchedule', 'punches'])
            ->orderBy('date', 'desc');

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        return DailyTimeRecordResource::collection($query->get());
    }

    /**
     * Get period summary for an employee.
     */
    public function summary(Request $request, Employee $employee): DtrPeriodSummaryResource
    {
        $startDate = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))
            : now()->startOfMonth();

        $endDate = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))
            : now()->endOfMonth();

        $summary = $this->aggregator->getSummary($employee, $startDate, $endDate);

        // Add employee info to summary
        $summary['employee'] = [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'full_name' => $employee->full_name,
            'department' => $employee->department?->name,
            'position' => $employee->position?->title,
        ];

        return new DtrPeriodSummaryResource($summary);
    }

    /**
     * Calculate/recalculate DTR for an employee on a specific date.
     */
    public function calculate(CalculateDtrRequest $request, Employee $employee): DailyTimeRecordResource
    {
        $date = Carbon::parse($request->validated('date'));
        $dtr = $this->calculationService->calculateForDate($employee, $date);

        return new DailyTimeRecordResource($dtr);
    }

    /**
     * Calculate/recalculate DTR for an employee over a date range.
     */
    public function calculateRange(CalculateDtrRangeRequest $request, Employee $employee): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $startDate = Carbon::parse($validated['date_from']);
        $endDate = Carbon::parse($validated['date_to']);

        $records = $this->calculationService->calculateForDateRange($employee, $startDate, $endDate);

        return DailyTimeRecordResource::collection($records);
    }

    /**
     * Approve overtime for a specific DTR record.
     */
    public function approveOvertime(DailyTimeRecord $dailyTimeRecord): JsonResponse
    {
        if ($dailyTimeRecord->overtime_minutes === 0) {
            return response()->json([
                'message' => 'No overtime to approve for this record.',
            ], 422);
        }

        $dailyTimeRecord->update([
            'overtime_approved' => true,
        ]);

        return response()->json([
            'message' => 'Overtime approved successfully.',
            'data' => new DailyTimeRecordResource($dailyTimeRecord->fresh()),
        ]);
    }

    /**
     * Resolve review flag for a DTR record.
     */
    public function resolveReview(ResolveDtrReviewRequest $request, DailyTimeRecord $dailyTimeRecord): JsonResponse
    {

        if (! $dailyTimeRecord->needs_review) {
            return response()->json([
                'message' => 'This record does not need review.',
            ], 422);
        }

        $dailyTimeRecord->update([
            'needs_review' => false,
            'review_reason' => null,
            'remarks' => $request->input('remarks') ?? $dailyTimeRecord->remarks,
        ]);

        return response()->json([
            'message' => 'Review resolved successfully.',
            'data' => new DailyTimeRecordResource($dailyTimeRecord->fresh()),
        ]);
    }

    /**
     * Get records needing review.
     */
    public function needsReview(DtrFilterRequest $request): AnonymousResourceCollection
    {
        $query = DailyTimeRecord::query()
            ->with(['employee', 'workSchedule'])
            ->where('needs_review', true)
            ->orderBy('date', 'desc');

        // Filter by department
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        $perPage = $request->input('per_page', 25);

        return DailyTimeRecordResource::collection($query->paginate($perPage));
    }

    /**
     * Get records with pending overtime approval.
     */
    public function pendingOvertime(DtrFilterRequest $request): AnonymousResourceCollection
    {
        $query = DailyTimeRecord::query()
            ->with(['employee', 'workSchedule'])
            ->where('overtime_minutes', '>', 0)
            ->where('overtime_approved', false)
            ->orderBy('date', 'desc');

        // Filter by department
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        $perPage = $request->input('per_page', 25);

        return DailyTimeRecordResource::collection($query->paginate($perPage));
    }
}
