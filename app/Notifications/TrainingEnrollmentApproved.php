<?php

namespace App\Notifications;

use App\Models\TrainingEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their training enrollment request is approved.
 */
class TrainingEnrollmentApproved extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TrainingEnrollment $enrollment,
        public bool $addedToWaitlist = false
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

        $message = (new MailMessage)
            ->subject('Training Enrollment '.($this->addedToWaitlist ? 'Waitlisted' : 'Approved').' - '.$this->enrollment->reference_number)
            ->greeting('Hello '.$notifiable->name.'!');

        if ($this->addedToWaitlist) {
            $message->line('Your training enrollment request has been approved, but the session is currently full.')
                ->line('You have been added to the waitlist and will be notified if a spot becomes available.');
        } else {
            $message->line('Great news! Your training enrollment request has been approved.');
        }

        $message->line("**Reference:** {$this->enrollment->reference_number}")
            ->line("**Training:** {$session->display_title}")
            ->line("**Dates:** {$session->date_range}");

        if ($session->time_range) {
            $message->line("**Time:** {$session->time_range}");
        }

        if ($session->location) {
            $message->line("**Location:** {$session->location}");
        }

        if ($this->enrollment->approver_remarks) {
            $message->line("**Approver Remarks:** {$this->enrollment->approver_remarks}");
        }

        return $message
            ->action('View Details', $this->getViewUrl())
            ->line($this->addedToWaitlist ? 'Thank you for your patience.' : 'We look forward to seeing you at the training!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $session = $this->enrollment->session;

        $statusText = $this->addedToWaitlist ? 'waitlisted' : 'approved';

        return [
            'type' => 'training_enrollment_approved',
            'training_enrollment_id' => $this->enrollment->id,
            'reference_number' => $this->enrollment->reference_number,
            'session_id' => $session->id,
            'session_title' => $session->display_title,
            'start_date' => $session->start_date->format('Y-m-d'),
            'end_date' => $session->end_date->format('Y-m-d'),
            'approved_at' => $this->enrollment->approved_at?->format('Y-m-d H:i:s'),
            'added_to_waitlist' => $this->addedToWaitlist,
            'message' => "Your training enrollment request ({$this->enrollment->reference_number}) has been {$statusText}.",
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
