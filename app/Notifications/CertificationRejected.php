<?php

namespace App\Notifications;

use App\Models\Certification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to an employee when their certification is rejected.
 */
class CertificationRejected extends Notification
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
        $certType = $this->certification->certificationType;

        return (new MailMessage)
            ->subject('Certification Requires Revision - '.$certType->name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your certification submission requires revision.')
            ->line("**Certification Type:** {$certType->name}")
            ->line("**Issuing Body:** {$this->certification->issuing_body}")
            ->line("**Reason for Rejection:** {$this->certification->rejection_reason}")
            ->action('Edit and Resubmit', $this->getEditUrl())
            ->line('Please review the feedback and resubmit your certification.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certification_rejected',
            'certification_id' => $this->certification->id,
            'certification_type' => $this->certification->certificationType->name,
            'rejection_reason' => $this->certification->rejection_reason,
            'message' => "Your {$this->certification->certificationType->name} certification was returned for revision.",
        ];
    }

    /**
     * Get the URL for editing the certification.
     */
    protected function getEditUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/certifications';
    }
}
