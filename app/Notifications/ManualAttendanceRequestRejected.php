<?php

namespace App\Notifications;

use App\Models\ManualAttendanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their manual attendance request is
 * rejected.
 */
class ManualAttendanceRequestRejected extends Notification
{
    use Queueable;

    public function __construct(
        public ManualAttendanceRequest $request,
        public string $reason
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
        return (new MailMessage)
            ->subject('Manual Attendance Request Rejected - '.$this->request->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your manual attendance request has been rejected.')
            ->line("**Reference:** {$this->request->reference_number}")
            ->line("**Date:** {$this->request->attendance_date->format('M d, Y')}")
            ->line("**Reason for rejection:** {$this->reason}")
            ->action('View Request', $this->getViewUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'manual_attendance_request_rejected',
            'manual_attendance_request_id' => $this->request->id,
            'reference_number' => $this->request->reference_number,
            'attendance_date' => $this->request->attendance_date->format('Y-m-d'),
            'reason' => $this->reason,
            'message' => "Your manual attendance request {$this->request->reference_number} for {$this->request->attendance_date->format('M d, Y')} has been rejected.",
        ];
    }

    protected function getViewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/manual-attendance-requests';
    }
}
