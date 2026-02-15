<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class UserInvitation extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Tenant $tenant,
        public User $inviter,
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
        $acceptUrl = $this->getAcceptUrl();
        $primaryColor = $this->tenant->primary_color ?? '#111827';
        $logoUrl = $this->tenant->logo_path
            ? Storage::disk('public')->url($this->tenant->logo_path)
            : null;

        return (new MailMessage)
            ->subject("You've been invited to join {$this->tenant->name}")
            ->view('emails.invitation', [
                'subject' => "You've been invited to join {$this->tenant->name}",
                'userName' => $notifiable->name,
                'inviterName' => $this->inviter->name,
                'tenantName' => $this->tenant->name,
                'acceptUrl' => $acceptUrl,
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
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'inviter_id' => $this->inviter->id,
            'inviter_name' => $this->inviter->name,
            'token' => $this->token,
        ];
    }

    /**
     * Get the invitation acceptance URL.
     */
    protected function getAcceptUrl(): string
    {
        $mainDomain = config('app.main_domain', 'kasamahr.test');
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$mainDomain}/invitations/{$this->token}/accept";
    }
}
