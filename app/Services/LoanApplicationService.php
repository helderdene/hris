<?php

namespace App\Services;

use App\Enums\LeaveApprovalDecision;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use App\Notifications\LoanApplicationApproved;
use App\Notifications\LoanApplicationRejected;
use App\Notifications\LoanApplicationSubmittedToApprover;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages the loan application workflow.
 *
 * 3-step approval chain:
 *   Level 1: CFO  → Level 2: Admin Manager  → Level 3: Releasing Officer
 *
 * The Releasing officer's approval is the disbursement step — that's when
 * the EmployeeLoan is created and the application moves to Approved status.
 */
class LoanApplicationService
{
    public function __construct(
        protected LoanApprovalChainResolver $chainResolver
    ) {}

    /**
     * Submit a loan application for approval.
     *
     * Builds the 3-step chain, persists per-level deadlines, sets the
     * overall SLA deadline, and notifies the level-1 approver.
     *
     * @throws ValidationException
     */
    public function submit(LoanApplication $application): LoanApplication
    {
        if ($application->status !== LoanApplicationStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft applications can be submitted.',
            ]);
        }

        return DB::transaction(function () use ($application) {
            $application->submitted_at = now();

            $chain = $this->chainResolver->resolveChain($application);

            if ($chain->isEmpty()) {
                throw ValidationException::withMessages([
                    'approver' => 'No loan approvers configured. Please contact HR.',
                ]);
            }

            foreach ($chain as $entry) {
                /** @var \App\Models\Employee $approver */
                $approver = $entry['employee'];

                LoanApplicationApproval::create([
                    'loan_application_id' => $application->id,
                    'approval_level' => $entry['level'],
                    'approver_type' => $entry['type'],
                    'approver_employee_id' => $approver->id,
                    'approver_name' => $approver->full_name,
                    'approver_position' => $approver->position?->name,
                    'decision' => LeaveApprovalDecision::Pending,
                    'deadline_at' => $entry['deadline'],
                ]);
            }

            $application->status = LoanApplicationStatus::Pending;
            $application->current_approval_level = 1;
            $application->total_approval_levels = $chain->count();
            $application->sla_deadline_at = $this->chainResolver->computeSlaDeadline($application);
            $application->metadata = array_merge($application->metadata ?? [], [
                'submitted_by' => auth()->id(),
                'submitted_ip' => request()->ip(),
            ]);
            $application->save();

            $firstApproval = $application->approvals()->where('approval_level', 1)->first();
            if ($firstApproval && $firstApproval->approverEmployee?->user) {
                $firstApproval->approverEmployee->user->notify(
                    new LoanApplicationSubmittedToApprover($application, $firstApproval)
                );
            }

            return $application->fresh(['employee', 'approvals']);
        });
    }

    /**
     * Approve a loan application at the current level.
     *
     * On final-level approval, expects {interest_rate, start_date, remarks?}
     * because that's when the EmployeeLoan is created (Releasing step).
     * Earlier levels just take an optional remarks string.
     *
     * @param  array{remarks?: string|null, interest_rate?: float, start_date?: string}  $data
     *
     * @throws ValidationException
     */
    public function approve(
        LoanApplication $application,
        Employee $approver,
        array $data = []
    ): LoanApplication {
        if ($application->status !== LoanApplicationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending applications can be approved.',
            ]);
        }

        $approval = $application->approvals()
            ->where('approval_level', $application->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', LeaveApprovalDecision::Pending)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to approve this application at this level.',
            ]);
        }

        $isFinalLevel = $application->current_approval_level >= $application->total_approval_levels;

        if ($isFinalLevel) {
            if (! array_key_exists('interest_rate', $data) || $data['interest_rate'] === null
                || empty($data['start_date'])) {
                throw ValidationException::withMessages([
                    'interest_rate' => 'Interest rate and start date are required at the final (Releasing) step.',
                ]);
            }
        }

        return DB::transaction(function () use ($application, $approver, $approval, $data, $isFinalLevel) {
            $approval->approve($data['remarks'] ?? null);

            if ($isFinalLevel) {
                $loan = $this->createEmployeeLoan($application, (float) $data['interest_rate'], $data['start_date']);

                $application->status = LoanApplicationStatus::Approved;
                $application->reviewer_employee_id = $approver->id;
                $application->reviewer_remarks = $data['remarks'] ?? null;
                $application->reviewed_at = now();
                $application->employee_loan_id = $loan->id;
                $application->save();

                $application->employee->user?->notify(new LoanApplicationApproved($application));
            } else {
                $application->current_approval_level++;
                $application->save();

                $nextApproval = $application->approvals()
                    ->where('approval_level', $application->current_approval_level)
                    ->first();

                if ($nextApproval && $nextApproval->approverEmployee?->user) {
                    $nextApproval->approverEmployee->user->notify(
                        new LoanApplicationSubmittedToApprover($application, $nextApproval)
                    );
                }
            }

            return $application->fresh(['employee', 'reviewer', 'approvals', 'employeeLoan']);
        });
    }

    /**
     * Reject a loan application at the current level (terminates the chain).
     *
     * @throws ValidationException
     */
    public function reject(
        LoanApplication $application,
        Employee $approver,
        string $remarks
    ): LoanApplication {
        if ($application->status !== LoanApplicationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending applications can be rejected.',
            ]);
        }

        $approval = $application->approvals()
            ->where('approval_level', $application->current_approval_level)
            ->where('approver_employee_id', $approver->id)
            ->where('decision', LeaveApprovalDecision::Pending)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approver' => 'You are not authorized to reject this application.',
            ]);
        }

        return DB::transaction(function () use ($application, $approver, $approval, $remarks) {
            $approval->reject($remarks);

            $application->status = LoanApplicationStatus::Rejected;
            $application->reviewer_employee_id = $approver->id;
            $application->reviewer_remarks = $remarks;
            $application->reviewed_at = now();
            $application->save();

            $application->employee->user?->notify(new LoanApplicationRejected($application, $remarks));

            return $application->fresh(['employee', 'reviewer', 'approvals']);
        });
    }

    /**
     * Cancel a loan application.
     *
     * @throws ValidationException
     */
    public function cancel(
        LoanApplication $application,
        ?string $reason = null
    ): LoanApplication {
        if (! $application->status->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'This application cannot be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($application, $reason) {
            $application->status = LoanApplicationStatus::Cancelled;
            $application->cancellation_reason = $reason;
            $application->save();

            return $application->fresh(['employee']);
        });
    }

    /**
     * Create the EmployeeLoan triggered by the Releasing officer's approval.
     */
    protected function createEmployeeLoan(
        LoanApplication $application,
        float $interestRate,
        string $startDate
    ): EmployeeLoan {
        $principal = (float) $application->amount_requested;
        $termMonths = $application->term_months;
        $totalAmount = $principal + ($principal * $interestRate * ($termMonths / 12));
        $monthlyDeduction = $totalAmount / $termMonths;
        $expectedEndDate = \Carbon\Carbon::parse($startDate)->addMonths($termMonths);

        return EmployeeLoan::create([
            'employee_id' => $application->employee_id,
            'loan_type' => $application->loan_type,
            'loan_code' => strtoupper('LOAN-'.fake()->lexify('????-????')),
            'reference_number' => $application->reference_number,
            'principal_amount' => $principal,
            'interest_rate' => $interestRate,
            'monthly_deduction' => round($monthlyDeduction, 2),
            'term_months' => $termMonths,
            'total_amount' => round($totalAmount, 2),
            'total_paid' => 0,
            'remaining_balance' => round($totalAmount, 2),
            'start_date' => $startDate,
            'expected_end_date' => $expectedEndDate,
            'status' => LoanStatus::Active,
            'notes' => 'Created from loan application '.$application->reference_number,
            'created_by' => auth()->id(),
        ]);
    }
}
