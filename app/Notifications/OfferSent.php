<?php

namespace App\Notifications;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Notification sent to the candidate when an offer is dispatched.
 */
class OfferSent extends Notification
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $candidate = $this->offer->jobApplication->candidate;

        return (new MailMessage)
            ->subject('Job Offer - '.$this->offer->position_title)
            ->greeting('Dear '.$candidate->full_name.',')
            ->line('We are pleased to extend an offer for the position of **'.$this->offer->position_title.'**.')
            ->line('Please review the offer details and respond by **'.($this->offer->expiry_date?->format('F j, Y') ?? 'the specified deadline').'**.')
            ->action('View Offer', $this->getOfferUrl())
            ->line('We look forward to hearing from you.');
    }

    /**
     * Get the signed URL for the offer response page.
     */
    protected function getOfferUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';
        $domain = "{$scheme}://{$tenant->slug}.".config('app.main_domain');

        return URL::temporarySignedRoute(
            'offers.respond',
            now()->addDays(30),
            ['tenant' => $tenant->slug, 'offer' => $this->offer->id]
        );
    }
}
