<?php

namespace App\Notifications;

use App\Models\ManualAttendanceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their manual attendance request is
 * approved.
 */
class ManualAttendanceRequestApproved extends Notification
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
        $message = (new MailMessage)
            ->subject('Manual Attendance Request Approved - '.$this->request->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your manual attendance request has been approved.')
            ->line("**Reference:** {$this->request->reference_number}")
            ->line("**Date:** {$this->request->attendance_date->format('M d, Y')}");

        if ($this->request->time_in) {
            $message->line("**Time In:** {$this->request->time_in}");
        }

        if ($this->request->time_out) {
            $message->line("**Time Out:** {$this->request->time_out}");
        }

        return $message->action('View Request', $this->getViewUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'manual_attendance_request_approved',
            'manual_attendance_request_id' => $this->request->id,
            'reference_number' => $this->request->reference_number,
            'attendance_date' => $this->request->attendance_date->format('Y-m-d'),
            'message' => "Your manual attendance request {$this->request->reference_number} for {$this->request->attendance_date->format('M d, Y')} has been approved.",
        ];
    }

    protected function getViewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/manual-attendance-requests';
    }
}
