<?php

namespace App\Notifications;

use App\Models\Certification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their certification has expired.
 */
class CertificationExpired extends Notification
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
        $expiredDate = $this->certification->expiry_date->format('M d, Y');

        return (new MailMessage)
            ->subject('Certification Expired - '.$certType->name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your certification has expired and is no longer valid.')
            ->line("**Certification Type:** {$certType->name}")
            ->line("**Issuing Body:** {$this->certification->issuing_body}")
            ->line("**Certificate Number:** {$this->certification->certificate_number}")
            ->line("**Expired On:** {$expiredDate}")
            ->action('Submit Renewed Certification', $this->getSubmitUrl())
            ->line('Please submit your renewed certification as soon as possible.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certification_expired',
            'certification_id' => $this->certification->id,
            'certification_type' => $this->certification->certificationType->name,
            'issuing_body' => $this->certification->issuing_body,
            'expiry_date' => $this->certification->expiry_date->format('Y-m-d'),
            'message' => "Your {$this->certification->certificationType->name} certification has expired.",
        ];
    }

    /**
     * Get the URL for submitting a new certification.
     */
    protected function getSubmitUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/certifications';
    }
}
