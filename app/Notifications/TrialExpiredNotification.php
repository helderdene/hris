<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to tenant admins when the trial period has expired.
 */
class TrialExpiredNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Tenant $tenant) {}

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
            ->subject('Your trial has expired')
            ->greeting("Hello {$notifiable->name}!")
            ->line("The trial period for **{$this->tenant->name}** has expired.")
            ->line('To continue using all features, please select a plan that fits your needs.')
            ->action('Choose a Plan', $this->getBillingUrl())
            ->line('If you have any questions, feel free to reach out to our support team.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trial_expired',
            'tenant_id' => $this->tenant->id,
            'message' => "The trial period for {$this->tenant->name} has expired. Please select a plan to continue.",
        ];
    }

    /**
     * Get the billing page URL.
     */
    protected function getBillingUrl(): string
    {
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$this->tenant->slug}.".config('app.main_domain').'/billing/plans';
    }
}
