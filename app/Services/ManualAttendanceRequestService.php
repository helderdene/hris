<?php

namespace App\Services;

use App\Enums\AttendanceSource;
use App\Enums\ManualAttendanceRequestStatus;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\ManualAttendanceRequest;
use App\Models\User;
use App\Notifications\ManualAttendanceRequestApproved;
use App\Notifications\ManualAttendanceRequestRejected;
use App\Notifications\ManualAttendanceRequestSubmitted;
use App\Services\Dtr\DtrCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing manual attendance request workflow.
 *
 * Handles submission, single-level approval (Dept Head OR HR Admin), rejection,
 * cancellation, and AttendanceLog materialization with DTR recalculation.
 */
class ManualAttendanceRequestService
{
    public function __construct(
        protected DtrCalculationService $dtrCalculationService
    ) {}

    /**
     * Submit a draft request and notify eligible approvers.
     *
     * @throws ValidationException
     */
    public function submit(ManualAttendanceRequest $request): ManualAttendanceRequest
    {
        if ($request->status !== ManualAttendanceRequestStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft requests can be submitted.',
            ]);
        }

        return DB::transaction(function () use ($request) {
            $request->status = ManualAttendanceRequestStatus::Pending;
            $request->submitted_at = now();
            $request->metadata = array_merge($request->metadata ?? [], [
                'submitted_by' => auth()->id(),
                'submitted_ip' => request()->ip(),
            ]);
            $request->save();

            $this->notifyEligibleApprovers($request);

            return $request->fresh(['employee']);
        });
    }

    /**
     * Approve a pending request, materialise the attendance logs, and notify
     * the employee.
     *
     * @throws ValidationException
     */
    public function approve(
        ManualAttendanceRequest $request,
        User $approver,
        ?string $remarks = null
    ): ManualAttendanceRequest {
        return DB::transaction(function () use ($request, $approver, $remarks) {
            $locked = ManualAttendanceRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->canBeDecidedBy($approver)) {
                throw ValidationException::withMessages([
                    'approver' => 'You are not authorized to decide this request, or it is no longer pending.',
                ]);
            }

            $deciderEmployee = Employee::query()->where('user_id', $approver->id)->firstOrFail();
            $deciderRole = $locked->resolveDeciderRole($deciderEmployee);

            $locked->status = ManualAttendanceRequestStatus::Approved;
            $locked->decided_at = now();
            $locked->decided_by_user_id = $approver->id;
            $locked->decided_by_role = $deciderRole;
            $locked->decision_remarks = $remarks;
            $locked->save();

            $this->materializeAttendance($locked);

            $locked->employee->user?->notify(new ManualAttendanceRequestApproved($locked));

            return $locked->fresh(['employee']);
        });
    }

    /**
     * Reject a pending request and notify the employee.
     *
     * @throws ValidationException
     */
    public function reject(
        ManualAttendanceRequest $request,
        User $approver,
        string $reason
    ): ManualAttendanceRequest {
        return DB::transaction(function () use ($request, $approver, $reason) {
            $locked = ManualAttendanceRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || ! $locked->canBeDecidedBy($approver)) {
                throw ValidationException::withMessages([
                    'approver' => 'You are not authorized to decide this request, or it is no longer pending.',
                ]);
            }

            $deciderEmployee = Employee::query()->where('user_id', $approver->id)->firstOrFail();
            $deciderRole = $locked->resolveDeciderRole($deciderEmployee);

            $locked->status = ManualAttendanceRequestStatus::Rejected;
            $locked->decided_at = now();
            $locked->decided_by_user_id = $approver->id;
            $locked->decided_by_role = $deciderRole;
            $locked->decision_remarks = $reason;
            $locked->save();

            $locked->employee->user?->notify(new ManualAttendanceRequestRejected($locked, $reason));

            return $locked->fresh(['employee']);
        });
    }

    /**
     * Cancel a draft or pending request.
     *
     * @throws ValidationException
     */
    public function cancel(
        ManualAttendanceRequest $request,
        ?string $reason = null
    ): ManualAttendanceRequest {
        if (! $request->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This request cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($request, $reason) {
            $request->status = ManualAttendanceRequestStatus::Cancelled;
            $request->cancelled_at = now();
            $request->cancellation_reason = $reason;
            $request->save();

            return $request->fresh(['employee']);
        });
    }

    /**
     * Notify the requester's Department Head and every active HR Admin Manager
     * (de-duped) that a new manual attendance request awaits decision.
     */
    protected function notifyEligibleApprovers(ManualAttendanceRequest $request): void
    {
        $employee = $request->employee()->with('department')->first();

        $approverEmployees = collect();

        $departmentHead = $employee?->department?->department_head_id
            ? Employee::query()->find($employee->department->department_head_id)
            : null;

        if ($departmentHead && $departmentHead->id !== $employee->id) {
            $approverEmployees->push($departmentHead);
        }

        $adminManagers = Employee::query()
            ->where('is_leave_admin_manager', true)
            ->where('id', '!=', $employee->id)
            ->get();

        $approverEmployees = $approverEmployees->concat($adminManagers)->unique('id');

        $notifiables = $approverEmployees
            ->map(fn (Employee $emp) => $emp->user)
            ->filter()
            ->values();

        if ($notifiables->isNotEmpty()) {
            Notification::send($notifiables, new ManualAttendanceRequestSubmitted($request));
        }
    }

    /**
     * Create AttendanceLog rows for the requested punches and trigger DTR
     * recalculation for the attendance date.
     */
    public function materializeAttendance(ManualAttendanceRequest $request): void
    {
        $createdLogIds = [];

        if ($request->time_in) {
            $createdLogIds[] = $this->createAttendanceLog($request, 'in', $request->time_in)->id;
        }

        if ($request->time_out) {
            $createdLogIds[] = $this->createAttendanceLog($request, 'out', $request->time_out)->id;
        }

        $this->dtrCalculationService->calculateForDate(
            $request->employee,
            Carbon::parse($request->attendance_date)
        );

        $request->metadata = array_merge($request->metadata ?? [], [
            'created_attendance_log_ids' => $createdLogIds,
            'materialized_at' => now()->toIso8601String(),
        ]);
        $request->save();
    }

    /**
     * Build a single AttendanceLog row tagged as a manual entry.
     */
    protected function createAttendanceLog(
        ManualAttendanceRequest $request,
        string $direction,
        string $time
    ): AttendanceLog {
        $loggedAt = Carbon::parse($request->attendance_date->toDateString().' '.$time);
        $employee = $request->employee;

        return AttendanceLog::create([
            'biometric_device_id' => null,
            'employee_id' => $employee->id,
            'device_person_id' => (string) $employee->id,
            'device_record_id' => $request->reference_number.'-'.$direction,
            'employee_code' => $employee->employee_number,
            'confidence' => 0,
            'verify_status' => 'manual',
            'logged_at' => $loggedAt,
            'direction' => $direction,
            'person_name' => $employee->full_name,
            'captured_photo' => null,
            'source' => AttendanceSource::Manual,
            'raw_payload' => [
                'origin' => 'manual_attendance_request',
                'request_id' => $request->id,
                'reference_number' => $request->reference_number,
                'decided_by_user_id' => $request->decided_by_user_id,
                'decided_by_role' => $request->decided_by_role,
            ],
        ]);
    }
}
