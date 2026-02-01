<?php

namespace App\Notifications;

use App\Models\JobRequisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to approvers when a job requisition is submitted.
 */
class JobRequisitionSubmitted extends Notification
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
        $employee = $this->requisition->requestedByEmployee;

        return (new MailMessage)
            ->subject('Job Requisition Pending Approval - '.$this->requisition->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$employee->full_name} has submitted a job requisition that requires your approval.")
            ->line("**Reference:** {$this->requisition->reference_number}")
            ->line("**Position:** {$this->requisition->position->title}")
            ->line("**Department:** {$this->requisition->department->name}")
            ->line("**Headcount:** {$this->requisition->headcount}")
            ->action('Review Requisition', $this->getReviewUrl())
            ->line('Please review and take action on this request.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_requisition_submitted',
            'title' => 'Job Requisition Submitted',
            'job_requisition_id' => $this->requisition->id,
            'reference_number' => $this->requisition->reference_number,
            'employee_name' => $this->requisition->requestedByEmployee->full_name,
            'position' => $this->requisition->position->title,
            'department' => $this->requisition->department->name,
            'headcount' => $this->requisition->headcount,
            'message' => "{$this->requisition->requestedByEmployee->full_name} submitted a job requisition for {$this->requisition->position->title} ({$this->requisition->reference_number}).",
            'url' => '/recruitment/approvals',
        ];
    }

    /**
     * Get the URL for reviewing the requisition.
     */
    protected function getReviewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';
        $slug = $tenant?->slug ?? 'app';

        return "{$scheme}://{$slug}.".config('app.main_domain').'/recruitment/approvals';
    }
}
