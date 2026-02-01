<?php

namespace App\Notifications;

use App\Models\TrainingEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their training enrollment request is rejected.
 */
class TrainingEnrollmentRejected extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TrainingEnrollment $enrollment,
        public string $reason
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
        $session = $this->enrollment->session;

        return (new MailMessage)
            ->subject('Training Enrollment Rejected - '.$this->enrollment->reference_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Unfortunately, your training enrollment request has been rejected.')
            ->line("**Reference:** {$this->enrollment->reference_number}")
            ->line("**Training:** {$session->display_title}")
            ->line("**Dates:** {$session->date_range}")
            ->line("**Rejection Reason:** {$this->reason}")
            ->action('View Details', $this->getViewUrl())
            ->line('If you have questions about this decision, please contact your supervisor.');
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
            'type' => 'training_enrollment_rejected',
            'training_enrollment_id' => $this->enrollment->id,
            'reference_number' => $this->enrollment->reference_number,
            'session_id' => $session->id,
            'session_title' => $session->display_title,
            'start_date' => $session->start_date->format('Y-m-d'),
            'end_date' => $session->end_date->format('Y-m-d'),
            'rejected_at' => $this->enrollment->rejected_at?->format('Y-m-d H:i:s'),
            'reason' => $this->reason,
            'message' => "Your training enrollment request ({$this->enrollment->reference_number}) has been rejected: {$this->reason}",
        ];
    }

    /**
     * Get the URL for viewing the training enrollment.
     */
    protected function getViewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/training';
    }
}
