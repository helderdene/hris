<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Notification sent to new hires to set up their account password
 * and access their pre-boarding checklist.
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
        $primaryColor = $this->tenant->primary_color ?? '#111827';
        $logoUrl = $this->tenant->logo_path
            ? Storage::disk('public')->url($this->tenant->logo_path)
            : null;

        return (new MailMessage)
            ->subject("Set Up Your {$this->tenant->name} Account")
            ->view('emails.new-hire-account-setup', [
                'subject' => "Set Up Your {$this->tenant->name} Account",
                'userName' => $notifiable->name,
                'tenantName' => $this->tenant->name,
                'setupUrl' => $setupUrl,
                'primaryColor' => $primaryColor,
                'logoUrl' => $logoUrl,
            ]);
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
