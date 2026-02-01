<?php

namespace App\Notifications;

use App\Models\Certification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their certification is about to expire.
 */
class CertificationExpiryReminder extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Certification $certification,
        public int $daysUntilExpiry
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
        $expiryDate = $this->certification->expiry_date->format('M d, Y');

        $urgencyMessage = match (true) {
            $this->daysUntilExpiry <= 7 => 'This is an urgent reminder.',
            $this->daysUntilExpiry <= 30 => 'Please plan for renewal soon.',
            default => 'Please plan accordingly.',
        };

        return (new MailMessage)
            ->subject("Certification Expiring in {$this->daysUntilExpiry} Days - {$certType->name}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("Your certification is expiring in **{$this->daysUntilExpiry} days**. {$urgencyMessage}")
            ->line("**Certification Type:** {$certType->name}")
            ->line("**Issuing Body:** {$this->certification->issuing_body}")
            ->line("**Certificate Number:** {$this->certification->certificate_number}")
            ->line("**Expiry Date:** {$expiryDate}")
            ->action('View My Certifications', $this->getViewUrl())
            ->line('Please renew your certification before the expiry date to maintain your professional standing.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'certification_expiry_reminder',
            'certification_id' => $this->certification->id,
            'certification_type' => $this->certification->certificationType->name,
            'issuing_body' => $this->certification->issuing_body,
            'expiry_date' => $this->certification->expiry_date->format('Y-m-d'),
            'days_until_expiry' => $this->daysUntilExpiry,
            'message' => "Your {$this->certification->certificationType->name} certification expires in {$this->daysUntilExpiry} days.",
        ];
    }

    /**
     * Get the URL for viewing certifications.
     */
    protected function getViewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/certifications';
    }
}
