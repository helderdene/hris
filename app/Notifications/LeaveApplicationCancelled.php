<?php

namespace App\Notifications;

use App\Models\LeaveApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to approvers when a leave application is cancelled.
 */
class LeaveApplicationCancelled extends Notification
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

        $message = (new MailMessage)
            ->subject('Leave Request Cancelled - '.$this->application->reference_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$employee->full_name} has cancelled their leave request.")
            ->line("**Reference:** {$this->application->reference_number}")
            ->line("**Leave Type:** {$leaveType->name}")
            ->line("**Dates:** {$this->application->date_range}");

        if ($this->application->cancellation_reason) {
            $message->line("**Reason:** {$this->application->cancellation_reason}");
        }

        return $message->line('No further action is required from you.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_application_cancelled',
            'leave_application_id' => $this->application->id,
            'reference_number' => $this->application->reference_number,
            'employee_id' => $this->application->employee_id,
            'employee_name' => $this->application->employee->full_name,
            'leave_type' => $this->application->leaveType->name,
            'start_date' => $this->application->start_date->format('Y-m-d'),
            'end_date' => $this->application->end_date->format('Y-m-d'),
            'total_days' => $this->application->total_days,
            'cancelled_at' => $this->application->cancelled_at?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $this->application->cancellation_reason,
            'message' => "{$this->application->employee->full_name} cancelled their {$this->application->leaveType->name} request ({$this->application->reference_number}).",
        ];
    }
}
