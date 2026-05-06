<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies an approver that a loan application has reached their level.
 *
 * Sent on initial submission (level 1) and again whenever the chain
 * advances to a new level.
 */
class LoanApplicationSubmittedToApprover extends Notification
{
    use Queueable;

    public function __construct(
        public LoanApplication $application,
        public LoanApplicationApproval $approval
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->application->employee;
        $deadline = $this->approval->deadline_at?->format('M d, Y g:i A') ?? 'no deadline';
        $urgencyLabel = $this->urgencyLabel($this->application->urgency_level);

        return (new MailMessage)
            ->subject('Loan Approval Required — '.$employee->full_name.' ('.$this->application->reference_number.')')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("A loan application from {$employee->full_name} has reached your approval step.")
            ->line('**Reference:** '.$this->application->reference_number)
            ->line('**Loan Type:** '.$this->application->loan_type->label())
            ->line('**Amount Requested:** PHP '.number_format((float) $this->application->amount_requested, 2))
            ->line('**Term:** '.$this->application->term_months.' months')
            ->line('**Urgency:** '.$urgencyLabel)
            ->line('**Your Deadline:** '.$deadline)
            ->action('Review Application', $this->getReviewUrl())
            ->line('Please review and act before the deadline.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'loan_application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'employee_name' => $this->application->employee->full_name,
            'amount_requested' => (float) $this->application->amount_requested,
            'approval_level' => $this->approval->approval_level,
            'approver_type' => $this->approval->approver_type,
            'deadline_at' => $this->approval->deadline_at?->toIso8601String(),
            'urgency_level' => $this->application->urgency_level,
            'message' => 'Loan application '.$this->application->reference_number.' awaits your approval.',
        ];
    }

    protected function getReviewUrl(): string
    {
        return url('/loan-approvals');
    }

    protected function urgencyLabel(?int $level): string
    {
        return match ($level) {
            5 => 'High (5)',
            4 => 'Somewhat High (4)',
            3 => 'Medium (3)',
            2 => 'Somewhat Low (2)',
            1 => 'Low (1)',
            default => 'Unspecified',
        };
    }
}
