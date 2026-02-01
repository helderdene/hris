<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to HR when a candidate declines an offer.
 */
class OfferDeclined extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Offer $offer
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
        $candidate = $this->offer->jobApplication->candidate;

        $message = (new MailMessage)
            ->subject('Offer Declined - '.$candidate->full_name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$candidate->full_name} has **declined** the offer for **{$this->offer->position_title}**.");

        if ($this->offer->decline_reason) {
            $message->line("**Reason:** {$this->offer->decline_reason}");
        }

        return $message
            ->action('View Offer', $this->getOfferUrl())
            ->line('You may wish to review alternative candidates.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $candidate = $this->offer->jobApplication->candidate;

        return [
            'type' => 'offer_declined',
            'offer_id' => $this->offer->id,
            'candidate_name' => $candidate->full_name,
            'position_title' => $this->offer->position_title,
            'decline_reason' => $this->offer->decline_reason,
            'message' => "{$candidate->full_name} has declined the offer for {$this->offer->position_title}.",
        ];
    }

    /**
     * Get the URL for viewing the offer.
     */
    protected function getOfferUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/recruitment/offers/{$this->offer->id}";
    }
}
