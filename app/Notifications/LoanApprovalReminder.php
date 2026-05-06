<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use App\Models\LoanApplicationApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Daily reminder to an approver whose decision is past the deadline.
 *
 * Dispatched by the loan:send-overdue-reminders artisan command.
 */
class LoanApprovalReminder extends Notification
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
        $deadline = $this->approval->deadline_at?->format('M d, Y g:i A');
        $hoursOverdue = $this->approval->deadline_at
            ? now()->diffInHours($this->approval->deadline_at, false)
            : null;

        $message = (new MailMessage)
            ->subject('OVERDUE: Loan Approval Required — '.$this->application->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('A loan approval assigned to you is past its deadline.')
            ->line('**Applicant:** '.$employee->full_name)
            ->line('**Reference:** '.$this->application->reference_number)
            ->line('**Amount Requested:** PHP '.number_format((float) $this->application->amount_requested, 2))
            ->line('**Original Deadline:** '.$deadline);

        if ($hoursOverdue !== null && $hoursOverdue < 0) {
            $message->line('**Overdue by:** '.abs((int) $hoursOverdue).' hours');
        }

        return $message
            ->action('Review Now', url('/loan-approvals'))
            ->line('Please review and decide as soon as possible.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'loan_application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'approval_level' => $this->approval->approval_level,
            'deadline_at' => $this->approval->deadline_at?->toIso8601String(),
            'message' => 'Loan approval '.$this->application->reference_number.' is overdue.',
        ];
    }
}
