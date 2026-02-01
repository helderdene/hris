<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to the offer creator when the candidate views the offer.
 */
class OfferViewed extends Notification
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
        return ['database'];
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
            'type' => 'offer_viewed',
            'offer_id' => $this->offer->id,
            'candidate_name' => $candidate->full_name,
            'position_title' => $this->offer->position_title,
            'message' => "{$candidate->full_name} has viewed the offer for {$this->offer->position_title}.",
        ];
    }
}
