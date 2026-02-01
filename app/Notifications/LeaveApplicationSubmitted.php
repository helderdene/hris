<?php

namespace App\Notifications;

use App\Models\LeaveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to approvers when a leave application is submitted.
 */
class LeaveApplicationSubmitted extends Notification
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
        $employee = $this->application->employee;
        $leaveType = $this->application->leaveType;

        return (new MailMessage)
            ->subject('Leave Request Pending Approval - '.$employee->full_name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$employee->full_name} has submitted a leave request that requires your approval.")
            ->line("**Leave Type:** {$leaveType->name}")
            ->line("**Dates:** {$this->application->date_range}")
            ->line("**Duration:** {$this->application->total_days} day(s)")
            ->line("**Reason:** {$this->application->reason}")
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
        return [
            'type' => 'leave_application_submitted',
            'leave_application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'employee_id' => $this->application->employee_id,
            'employee_name' => $this->application->employee->full_name,
            'leave_type' => $this->application->leaveType->name,
            'start_date' => $this->application->start_date->format('Y-m-d'),
            'end_date' => $this->application->end_date->format('Y-m-d'),
            'total_days' => $this->application->total_days,
            'message' => "{$this->application->employee->full_name} submitted a {$this->application->leaveType->name} request for {$this->application->total_days} day(s).",
        ];
    }

    /**
     * Get the URL for reviewing the leave request.
     */
    protected function getReviewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/leave/approvals';
    }
}
