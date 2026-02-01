<?php

namespace App\Notifications;

use App\Models\TrainingEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to approvers when a training enrollment request is submitted.
 */
class TrainingEnrollmentRequestSubmitted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TrainingEnrollment $enrollment
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->enrollment->employee;
        $session = $this->enrollment->session;

        return (new MailMessage)
            ->subject('Training Enrollment Request - '.$employee->full_name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$employee->full_name} has submitted a training enrollment request that requires your approval.")
            ->line("**Reference:** {$this->enrollment->reference_number}")
            ->line("**Training:** {$session->display_title}")
            ->line("**Dates:** {$session->date_range}")
            ->when($this->enrollment->request_reason, fn ($message) => $message->line("**Reason:** {$this->enrollment->request_reason}"))
            ->action('Review Request', $this->getReviewUrl())
            ->line('Please review and take action on this request.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $session = $this->enrollment->session;

        return [
            'type' => 'training_enrollment_request_submitted',
            'training_enrollment_id' => $this->enrollment->id,
            'reference_number' => $this->enrollment->reference_number,
            'employee_id' => $this->enrollment->employee_id,
            'employee_name' => $this->enrollment->employee->full_name,
            'session_id' => $session->id,
            'session_title' => $session->display_title,
            'start_date' => $session->start_date->format('Y-m-d'),
            'end_date' => $session->end_date->format('Y-m-d'),
            'message' => "{$this->enrollment->employee->full_name} submitted a training enrollment request for {$session->display_title}.",
        ];
    }

    /**
     * Get the URL for reviewing the training request.
     */
    protected function getReviewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/training/approvals';
    }
}
