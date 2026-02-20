<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to tenant admins when a subscription is cancelled.
 */
class SubscriptionCancelledNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Tenant $tenant,
        public ?string $endsAt = null
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
        $mail = (new MailMessage)
            ->subject('Your subscription has been cancelled')
            ->greeting("Hello {$notifiable->name}!")
            ->line("The subscription for **{$this->tenant->name}** has been cancelled.");

        if ($this->endsAt) {
            $mail->line("You will continue to have access until **{$this->endsAt}**.");
        }

        return $mail
            ->line('You can reactivate your subscription at any time to restore full access.')
            ->action('Reactivate Subscription', $this->getBillingUrl())
            ->line('We hope to see you again soon!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_cancelled',
            'tenant_id' => $this->tenant->id,
            'ends_at' => $this->endsAt,
            'message' => "The subscription for {$this->tenant->name} has been cancelled.",
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
