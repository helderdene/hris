<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to tenant admins when a subscription payment fails.
 */
class PaymentFailedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Tenant $tenant,
        public ?string $reason = null
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
            ->subject('Payment failed for your subscription')
            ->greeting("Hello {$notifiable->name}!")
            ->line("We were unable to process the payment for **{$this->tenant->name}**.");

        if ($this->reason) {
            $mail->line("**Reason:** {$this->reason}");
        }

        return $mail
            ->line('Please update your payment method to avoid service interruption.')
            ->action('Update Payment Method', $this->getBillingUrl())
            ->line('If you believe this is an error, please contact our support team.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'tenant_id' => $this->tenant->id,
            'reason' => $this->reason,
            'message' => "Payment failed for {$this->tenant->name}. Please update your payment method.",
        ];
    }

    /**
     * Get the billing page URL.
     */
    protected function getBillingUrl(): string
    {
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$this->tenant->slug}.".config('app.main_domain').'/billing';
    }
}
