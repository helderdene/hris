<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to new hires to set up their account password.
 *
 * This is triggered manually by HR after a candidate is hired.
 */
class NewHireAccountSetup extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Tenant $tenant,
        public string $token
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $setupUrl = $this->getSetupUrl();

        return (new MailMessage)
            ->subject("Set Up Your {$this->tenant->name} Account")
            ->greeting("Welcome to {$this->tenant->name}!")
            ->line('Congratulations on joining our team! Your account has been created and is ready to be activated.')
            ->line('Click the button below to set your password and access your employee portal.')
            ->action('Set Up Your Account', $setupUrl)
            ->line('This link will expire in 30 days.')
            ->line('Once activated, you can access your preboarding checklist, view company announcements, and more.')
            ->line('If you have any questions, please contact HR.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_hire_account_setup',
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
        ];
    }

    /**
     * Get the account setup URL.
     */
    protected function getSetupUrl(): string
    {
        $mainDomain = config('app.main_domain', 'kasamahr.test');
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        // Uses the same invitation acceptance flow
        return "{$scheme}://{$mainDomain}/invitations/{$this->token}/accept";
    }
}
