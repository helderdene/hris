<?php

namespace App\Notifications;

use App\Models\PreboardingChecklistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to new hire when a preboarding item is rejected.
 */
class PreboardingItemRejected extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PreboardingChecklistItem $item
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
        return (new MailMessage)
            ->subject("Pre-boarding Item Needs Revision - {$this->item->name}")
            ->greeting('Hello!')
            ->line("Your submission for **{$this->item->name}** needs revision.")
            ->line("**Reason:** {$this->item->rejection_reason}")
            ->action('Update Submission', $this->getChecklistUrl())
            ->line('Please re-submit the item at your earliest convenience.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'preboarding_item_rejected',
            'item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'message' => "Your submission for {$this->item->name} needs revision.",
        ];
    }

    /**
     * Get the URL for viewing the checklist.
     */
    protected function getChecklistUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/preboarding';
    }
}
