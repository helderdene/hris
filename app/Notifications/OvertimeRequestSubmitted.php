<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to approvers when an overtime request is submitted.
 */
class OvertimeRequestSubmitted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public OvertimeRequest $request
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
        $employee = $this->request->employee;

        return (new MailMessage)
            ->subject('Overtime Request Pending Approval - '.$employee->full_name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$employee->full_name} has submitted an overtime request that requires your approval.")
            ->line("**Date:** {$this->request->overtime_date->format('M d, Y')}")
            ->line("**Type:** {$this->request->overtime_type->label()}")
            ->line("**Duration:** {$this->request->expected_hours_formatted} hours")
            ->line("**Reason:** {$this->request->reason}")
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
            'type' => 'overtime_request_submitted',
            'overtime_request_id' => $this->request->id,
            'reference_number' => $this->request->reference_number,
            'employee_id' => $this->request->employee_id,
            'employee_name' => $this->request->employee->full_name,
            'overtime_date' => $this->request->overtime_date->format('Y-m-d'),
            'expected_minutes' => $this->request->expected_minutes,
            'message' => "{$this->request->employee->full_name} submitted an overtime request for {$this->request->overtime_date->format('M d, Y')} ({$this->request->expected_hours_formatted} hrs).",
        ];
    }

    /**
     * Get the URL for reviewing the overtime request.
     */
    protected function getReviewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/overtime/approvals';
    }
}
