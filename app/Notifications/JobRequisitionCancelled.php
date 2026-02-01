<?php

namespace App\Notifications;

use App\Models\JobRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to pending approvers when a job requisition is cancelled.
 */
class JobRequisitionCancelled extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public JobRequisition $requisition
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Job Requisition Cancelled - '.$this->requisition->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('A job requisition you were assigned to review has been cancelled.')
            ->line("**Reference:** {$this->requisition->reference_number}")
            ->line("**Position:** {$this->requisition->position->title}")
            ->line('No further action is needed.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_requisition_cancelled',
            'title' => 'Job Requisition Cancelled',
            'job_requisition_id' => $this->requisition->id,
            'reference_number' => $this->requisition->reference_number,
            'position' => $this->requisition->position->title,
            'message' => "Job requisition for {$this->requisition->position->title} ({$this->requisition->reference_number}) has been cancelled.",
            'url' => "/recruitment/requisitions/{$this->requisition->id}",
        ];
    }
}
