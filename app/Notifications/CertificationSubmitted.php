<?php

namespace App\Notifications;

use App\Models\Certification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to HR when an employee submits a certification for approval.
 */
class CertificationSubmitted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Certification $certification
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
        $employee = $this->certification->employee;
        $certType = $this->certification->certificationType;

        return (new MailMessage)
            ->subject('Certification Pending Approval - '.$employee->full_name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$employee->full_name} has submitted a certification for your review.")
            ->line("**Certification Type:** {$certType->name}")
            ->line("**Issuing Body:** {$this->certification->issuing_body}")
            ->line("**Issue Date:** {$this->certification->issued_date->format('M d, Y')}")
            ->when($this->certification->expiry_date, fn ($message) => $message->line("**Expiry Date:** {$this->certification->expiry_date->format('M d, Y')}"))
            ->action('Review Certification', $this->getReviewUrl())
            ->line('Please review and take action on this submission.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certification_submitted',
            'certification_id' => $this->certification->id,
            'employee_id' => $this->certification->employee_id,
            'employee_name' => $this->certification->employee->full_name,
            'certification_type' => $this->certification->certificationType->name,
            'issuing_body' => $this->certification->issuing_body,
            'message' => "{$this->certification->employee->full_name} submitted a {$this->certification->certificationType->name} certification for approval.",
        ];
    }

    /**
     * Get the URL for reviewing the certification.
     */
    protected function getReviewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/hr/certifications';
    }
}
