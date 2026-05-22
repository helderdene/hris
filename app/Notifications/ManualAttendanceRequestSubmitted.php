<?php

namespace App\Notifications;

use App\Models\ManualAttendanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to eligible approvers when a manual attendance request is
 * submitted for approval.
 */
class ManualAttendanceRequestSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public ManualAttendanceRequest $request
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
        $employee = $this->request->employee;

        $message = (new MailMessage)
            ->subject('Manual Attendance Request Pending Approval - '.$employee->full_name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$employee->full_name} has submitted a manual attendance request that requires your approval.")
            ->line("**Reference:** {$this->request->reference_number}")
            ->line("**Date:** {$this->request->attendance_date->format('M d, Y')}");

        if ($this->request->time_in) {
            $message->line("**Time In:** {$this->request->time_in}");
        }

        if ($this->request->time_out) {
            $message->line("**Time Out:** {$this->request->time_out}");
        }

        return $message
            ->line("**Reason:** {$this->request->reason}")
            ->action('Review Request', $this->getReviewUrl())
            ->line('Please review and take action on this request.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'manual_attendance_request_submitted',
            'manual_attendance_request_id' => $this->request->id,
            'reference_number' => $this->request->reference_number,
            'employee_id' => $this->request->employee_id,
            'employee_name' => $this->request->employee->full_name,
            'attendance_date' => $this->request->attendance_date->format('Y-m-d'),
            'time_in' => $this->request->time_in,
            'time_out' => $this->request->time_out,
            'message' => "{$this->request->employee->full_name} submitted a manual attendance request for {$this->request->attendance_date->format('M d, Y')}.",
        ];
    }

    protected function getReviewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/manual-attendance/approvals';
    }
}
