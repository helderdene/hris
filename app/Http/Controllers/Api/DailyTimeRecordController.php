<?php

namespace App\Http\Controllers\Api;

use App\Enums\DtrStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CalculateDtrRangeRequest;
use App\Http\Requests\Api\CalculateDtrRequest;
use App\Http\Requests\Api\ResolveDtrReviewRequest;
use App\Http\Requests\DtrFilterRequest;
use App\Http\Resources\DailyTimeRecordResource;
use App\Http\Resources\DtrPeriodSummaryResource;
use App\Models\AttendanceLog;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Services\Dtr\DtrCalculationService;
use App\Services\Dtr\DtrPeriodAggregator;
use App\Services\Dtr\ScheduleResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;

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
     * Deny overtime for a specific DTR record.
     */
    public function denyOvertime(DailyTimeRecord $dailyTimeRecord): JsonResponse
    {
        if ($dailyTimeRecord->overtime_minutes === 0) {
            return response()->json([
                'message' => 'No overtime to deny for this record.',
            ], 422);
        }

        $dailyTimeRecord->update([
            'overtime_approved' => false,
            'overtime_denied' => true,
        ]);

        return response()->json([
            'message' => 'Overtime denied successfully.',
            'data' => new DailyTimeRecordResource($dailyTimeRecord->fresh()),
        ]);
    }

    /**
     * Update remarks for a specific DTR record.
     */
    public function updateRemarks(Request $request, DailyTimeRecord $dailyTimeRecord): JsonResponse
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:1000',
        ]);

        $dailyTimeRecord->update([
            'remarks' => $validated['remarks'],
        ]);

        return response()->json([
            'message' => 'Remarks updated successfully.',
            'data' => new DailyTimeRecordResource($dailyTimeRecord->fresh()),
        ]);
    }

    /**
     * Resolve review flag for a DTR record.
     *
     * Supports multiple resolution types:
     * - manual_time_out: Add a manual clock-out time and recalculate DTR
     * - use_schedule_end: Use the employee's scheduled end time as clock-out
     * - mark_half_day: Mark as present with half-day undertime
     * - mark_absent: Override the record status to absent
     * - no_change: Simply clear the review flag without modifying times
     */
    public function resolveReview(ResolveDtrReviewRequest $request, DailyTimeRecord $dailyTimeRecord): JsonResponse
    {
        if (! $dailyTimeRecord->needs_review) {
            return response()->json([
                'message' => 'This record does not need review.',
            ], 422);
        }

        $resolutionType = $request->input('resolution_type');
        $remarks = $request->input('remarks');
        $resolvedBy = auth()->user()?->name ?? 'System';
        $resolvedAt = now()->format('M j, Y g:ia');
        $auditRemarks = "[Resolved by {$resolvedBy} on {$resolvedAt}] {$remarks}";

        return match ($resolutionType) {
            'manual_time_out' => $this->resolveWithManualTimeOut($dailyTimeRecord, $request->input('manual_time_out'), $auditRemarks),
            'use_schedule_end' => $this->resolveWithScheduleEnd($dailyTimeRecord, $auditRemarks),
            'mark_half_day' => $this->resolveAsHalfDay($dailyTimeRecord, $auditRemarks),
            'mark_absent' => $this->resolveAsAbsent($dailyTimeRecord, $auditRemarks),
            default => $this->resolveNoChange($dailyTimeRecord, $auditRemarks),
        };
    }

    /**
     * Resolve by adding a manual time-out and recalculating.
     */
    protected function resolveWithManualTimeOut(DailyTimeRecord $dtr, string $timeOut, string $remarks): JsonResponse
    {
        $clockOutTime = Carbon::parse($dtr->date->toDateString().' '.$timeOut);

        $this->createManualAttendanceLog($dtr, $clockOutTime, 'out');

        $employee = $dtr->employee;
        $recalculated = $this->calculationService->calculateForDate($employee, $dtr->date);

        $recalculated->update([
            'needs_review' => false,
            'review_reason' => null,
            'remarks' => $remarks,
        ]);

        Log::info('DTR review resolved with manual time-out', [
            'dtr_id' => $dtr->id,
            'manual_time_out' => $clockOutTime->toDateTimeString(),
            'resolved_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Review resolved. Time-out set to '.$clockOutTime->format('g:i A').' and DTR recalculated.',
            'data' => new DailyTimeRecordResource($recalculated->fresh(['workSchedule', 'punches.attendanceLog'])),
        ]);
    }

    /**
     * Resolve by using the employee's scheduled end time.
     */
    protected function resolveWithScheduleEnd(DailyTimeRecord $dtr, string $remarks): JsonResponse
    {
        $employee = $dtr->employee;
        $scheduleResolver = app(ScheduleResolver::class);
        $endTime = $scheduleResolver->getScheduledEndTime($dtr->workSchedule, $dtr->date, $dtr->shift_name);

        if ($endTime === null) {
            return response()->json([
                'message' => 'Could not determine the scheduled end time for this record.',
            ], 422);
        }

        $this->createManualAttendanceLog($dtr, $endTime, 'out');

        $recalculated = $this->calculationService->calculateForDate($employee, $dtr->date);

        $recalculated->update([
            'needs_review' => false,
            'review_reason' => null,
            'remarks' => $remarks,
        ]);

        Log::info('DTR review resolved with schedule end time', [
            'dtr_id' => $dtr->id,
            'schedule_end_time' => $endTime->toDateTimeString(),
            'resolved_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Review resolved. Time-out set to scheduled end ('.$endTime->format('g:i A').') and DTR recalculated.',
            'data' => new DailyTimeRecordResource($recalculated->fresh(['workSchedule', 'punches.attendanceLog'])),
        ]);
    }

    /**
     * Resolve by marking as half-day with undertime.
     */
    protected function resolveAsHalfDay(DailyTimeRecord $dtr, string $remarks): JsonResponse
    {
        $scheduleConfig = $dtr->workSchedule?->time_configuration ?? [];
        $breakMinutes = (int) ($scheduleConfig['break']['duration_minutes'] ?? 60);

        // Half day = 4 hours of work (half of 8-hour day)
        $halfDayMinutes = 240;
        $fullDayMinutes = 480;
        $undertimeMinutes = max(0, $fullDayMinutes - $halfDayMinutes);

        $dtr->update([
            'total_work_minutes' => $halfDayMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_minutes' => 0,
            'needs_review' => false,
            'review_reason' => null,
            'remarks' => $remarks,
        ]);

        Log::info('DTR review resolved as half-day', [
            'dtr_id' => $dtr->id,
            'resolved_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Review resolved. Record marked as half-day (4 hours) with undertime.',
            'data' => new DailyTimeRecordResource($dtr->fresh(['workSchedule', 'punches.attendanceLog'])),
        ]);
    }

    /**
     * Resolve by marking the record as absent.
     */
    protected function resolveAsAbsent(DailyTimeRecord $dtr, string $remarks): JsonResponse
    {
        $dtr->update([
            'status' => DtrStatus::Absent,
            'first_in' => null,
            'last_out' => null,
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'night_diff_minutes' => 0,
            'needs_review' => false,
            'review_reason' => null,
            'remarks' => $remarks,
        ]);

        Log::info('DTR review resolved as absent', [
            'dtr_id' => $dtr->id,
            'resolved_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Review resolved. Record marked as absent.',
            'data' => new DailyTimeRecordResource($dtr->fresh(['workSchedule', 'punches.attendanceLog'])),
        ]);
    }

    /**
     * Resolve without modifying times — just clear the review flag.
     */
    protected function resolveNoChange(DailyTimeRecord $dtr, string $remarks): JsonResponse
    {
        $dtr->update([
            'needs_review' => false,
            'review_reason' => null,
            'remarks' => $remarks,
        ]);

        return response()->json([
            'message' => 'Review resolved.',
            'data' => new DailyTimeRecordResource($dtr->fresh(['workSchedule', 'punches.attendanceLog'])),
        ]);
    }

    /**
     * Create a manual attendance log entry for time-out resolution.
     */
    protected function createManualAttendanceLog(DailyTimeRecord $dtr, Carbon $time, string $direction): AttendanceLog
    {
        $deviceId = \App\Models\BiometricDevice::first()?->id;

        return AttendanceLog::create([
            'biometric_device_id' => $deviceId,
            'employee_id' => $dtr->employee_id,
            'device_person_id' => (string) $dtr->employee_id,
            'device_record_id' => 'MANUAL-'.now()->format('YmdHis'),
            'employee_code' => $dtr->employee?->employee_number,
            'confidence' => 0,
            'verify_status' => 'manual',
            'logged_at' => $time,
            'direction' => $direction,
            'person_name' => $dtr->employee?->full_name,
            'captured_photo' => null,
            'raw_payload' => [
                'source' => 'manual_review_resolution',
                'resolved_by' => auth()->id(),
                'resolved_at' => now()->toISOString(),
            ],
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
