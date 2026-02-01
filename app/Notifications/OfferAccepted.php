<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to HR when a candidate accepts an offer.
 */
class OfferAccepted extends Notification
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

        return (new MailMessage)
            ->subject('Offer Accepted - '.$candidate->full_name)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$candidate->full_name} has **accepted** the offer for **{$this->offer->position_title}**.")
            ->line("**Start Date:** {$this->offer->start_date?->format('F j, Y')}")
            ->action('View Offer', $this->getOfferUrl())
            ->line('Please proceed with the onboarding process.');
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
            'type' => 'offer_accepted',
            'offer_id' => $this->offer->id,
            'candidate_name' => $candidate->full_name,
            'position_title' => $this->offer->position_title,
            'message' => "{$candidate->full_name} has accepted the offer for {$this->offer->position_title}.",
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
