<?php

namespace App\Notifications;

use App\Models\JobRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to the requester when their job requisition is rejected.
 */
class JobRequisitionRejected extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public JobRequisition $requisition,
        public string $reason
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
            ->subject('Job Requisition Rejected - '.$this->requisition->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your job requisition has been rejected.')
            ->line("**Reference:** {$this->requisition->reference_number}")
            ->line("**Position:** {$this->requisition->position->title}")
            ->line("**Reason:** {$this->reason}")
            ->action('View Details', $this->getViewUrl())
            ->line('You may create a new requisition if needed.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_requisition_rejected',
            'title' => 'Job Requisition Rejected',
            'job_requisition_id' => $this->requisition->id,
            'reference_number' => $this->requisition->reference_number,
            'position' => $this->requisition->position->title,
            'reason' => $this->reason,
            'message' => "Your job requisition for {$this->requisition->position->title} ({$this->requisition->reference_number}) has been rejected.",
            'url' => "/recruitment/requisitions/{$this->requisition->id}",
        ];
    }

    /**
     * Get the URL for viewing the requisition.
     */
    protected function getViewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';
        $slug = $tenant?->slug ?? 'app';

        return "{$scheme}://{$slug}.".config('app.main_domain')."/recruitment/requisitions/{$this->requisition->id}";
    }
}
