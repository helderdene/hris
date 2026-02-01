<?php

namespace App\Notifications;

use App\Models\LeaveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their leave application is approved.
 */
class LeaveApplicationApproved extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public LeaveApplication $application
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
        $leaveType = $this->application->leaveType;

        return (new MailMessage)
            ->subject('Leave Request Approved - '.$this->application->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Great news! Your leave request has been approved.')
            ->line("**Reference:** {$this->application->reference_number}")
            ->line("**Leave Type:** {$leaveType->name}")
            ->line("**Dates:** {$this->application->date_range}")
            ->line("**Duration:** {$this->application->total_days} day(s)")
            ->action('View Details', $this->getViewUrl())
            ->line('Enjoy your time off!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_application_approved',
            'leave_application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'leave_type' => $this->application->leaveType->name,
            'start_date' => $this->application->start_date->format('Y-m-d'),
            'end_date' => $this->application->end_date->format('Y-m-d'),
            'total_days' => $this->application->total_days,
            'approved_at' => $this->application->approved_at?->format('Y-m-d H:i:s'),
            'message' => "Your {$this->application->leaveType->name} request ({$this->application->reference_number}) has been approved.",
        ];
    }

    /**
     * Get the URL for viewing the leave application.
     */
    protected function getViewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/leave/applications/{$this->application->id}";
    }
}
