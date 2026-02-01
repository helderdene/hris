<?php

namespace App\Services;

use App\Enums\LoanApplicationStatus;
use App\Enums\LoanStatus;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing loan application workflow.
 *
 * Handles submission, approval, rejection, and cancellation
 * with automatic EmployeeLoan creation on approval.
 */
class LoanApplicationService
{
    /**
     * Submit a loan application for HR review.
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
            $application->status = LoanApplicationStatus::Pending;
            $application->submitted_at = now();
            $application->metadata = array_merge($application->metadata ?? [], [
                'submitted_by' => auth()->id(),
                'submitted_ip' => request()->ip(),
            ]);
            $application->save();

            return $application->fresh(['employee']);
        });
    }

    /**
     * Approve a loan application and create the EmployeeLoan.
     *
     * @param  array{interest_rate: float, start_date: string, remarks?: string}  $data
     *
     * @throws ValidationException
     */
    public function approve(
        LoanApplication $application,
        Employee $reviewer,
        array $data
    ): LoanApplication {
        if ($application->status !== LoanApplicationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending applications can be approved.',
            ]);
        }

        return DB::transaction(function () use ($application, $reviewer, $data) {
            $interestRate = $data['interest_rate'];
            $startDate = $data['start_date'];
            $principal = (float) $application->amount_requested;
            $termMonths = $application->term_months;

            // Calculate loan amounts
            $totalAmount = $principal + ($principal * $interestRate * ($termMonths / 12));
            $monthlyDeduction = $totalAmount / $termMonths;

            $expectedEndDate = \Carbon\Carbon::parse($startDate)->addMonths($termMonths);

            // Create the EmployeeLoan
            $loan = EmployeeLoan::create([
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

            // Update application
            $application->status = LoanApplicationStatus::Approved;
            $application->reviewer_employee_id = $reviewer->id;
            $application->reviewer_remarks = $data['remarks'] ?? null;
            $application->reviewed_at = now();
            $application->employee_loan_id = $loan->id;
            $application->save();

            return $application->fresh(['employee', 'reviewer', 'employeeLoan']);
        });
    }

    /**
     * Reject a loan application.
     *
     * @throws ValidationException
     */
    public function reject(
        LoanApplication $application,
        Employee $reviewer,
        string $remarks
    ): LoanApplication {
        if ($application->status !== LoanApplicationStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Only pending applications can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($application, $reviewer, $remarks) {
            $application->status = LoanApplicationStatus::Rejected;
            $application->reviewer_employee_id = $reviewer->id;
            $application->reviewer_remarks = $remarks;
            $application->reviewed_at = now();
            $application->save();

            return $application->fresh(['employee', 'reviewer']);
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
}
