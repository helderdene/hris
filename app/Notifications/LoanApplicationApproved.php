<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies the applicant when a loan application is fully approved
 * (Releasing officer's final step).
 */
class LoanApplicationApproved extends Notification
{
    use Queueable;

    public function __construct(public LoanApplication $application) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $loan = $this->application->employeeLoan;

        $message = (new MailMessage)
            ->subject('Loan Application Approved — '.$this->application->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your loan application has been fully approved and is now scheduled for release.')
            ->line('**Reference:** '.$this->application->reference_number)
            ->line('**Loan Type:** '.$this->application->loan_type->label())
            ->line('**Amount:** PHP '.number_format((float) $this->application->amount_requested, 2))
            ->line('**Term:** '.$this->application->term_months.' months');

        if ($loan) {
            $message->line('**Monthly Deduction:** PHP '.number_format((float) $loan->monthly_deduction, 2))
                ->line('**Start Date:** '.$loan->start_date?->format('M d, Y'));
        }

        return $message
            ->action('View Application', url('/my/loan-applications/'.$this->application->id))
            ->line('You can view full repayment details from your dashboard.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'loan_application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'amount_requested' => (float) $this->application->amount_requested,
            'message' => 'Your loan application '.$this->application->reference_number.' has been approved.',
        ];
    }
}
