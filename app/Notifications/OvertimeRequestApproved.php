<?php

namespace App\Notifications;

use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their overtime request is approved.
 */
class OvertimeRequestApproved extends Notification
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
        return (new MailMessage)
            ->subject('Overtime Request Approved - '.$this->request->reference_number)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your overtime request has been approved.')
            ->line("**Reference:** {$this->request->reference_number}")
            ->line("**Date:** {$this->request->overtime_date->format('M d, Y')}")
            ->line("**Type:** {$this->request->overtime_type->label()}")
            ->line("**Duration:** {$this->request->expected_hours_formatted} hours")
            ->action('View Request', $this->getViewUrl());
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'overtime_request_approved',
            'overtime_request_id' => $this->request->id,
            'reference_number' => $this->request->reference_number,
            'overtime_date' => $this->request->overtime_date->format('Y-m-d'),
            'message' => "Your overtime request {$this->request->reference_number} for {$this->request->overtime_date->format('M d, Y')} has been approved.",
        ];
    }

    /**
     * Get the URL for viewing the overtime request.
     */
    protected function getViewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/overtime-requests';
    }
}
