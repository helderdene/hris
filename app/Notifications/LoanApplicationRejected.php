<?php

namespace App\Notifications;

use App\Models\LoanApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifies the applicant when a loan application is rejected at any level.
 */
class LoanApplicationRejected extends Notification
{
    use Queueable;

    public function __construct(
        public LoanApplication $application,
        public string $reason
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
        return (new MailMessage)
            ->subject('Loan Application Rejected — '.$this->application->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your loan application was not approved.')
            ->line('**Reference:** '.$this->application->reference_number)
            ->line('**Reason:** '.$this->reason)
            ->action('View Application', url('/my/loan-applications/'.$this->application->id))
            ->line('Please contact HR if you have questions.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'loan_application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'reason' => $this->reason,
            'message' => 'Your loan application '.$this->application->reference_number.' was rejected.',
        ];
    }
}
