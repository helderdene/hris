<?php

namespace App\Notifications;

use App\Models\VisitorVisit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitorArrived extends Notification
{
    public function __construct(
        public VisitorVisit $visit
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $visitor = $this->visit->visitor;

        return (new MailMessage)
            ->subject('Your Visitor Has Arrived')
            ->greeting('Visitor Arrival')
            ->line("{$visitor->full_name}".($visitor->company ? " from {$visitor->company}" : '').' has arrived.')
            ->line("**Purpose:** {$this->visit->purpose}")
            ->line("**Check-in Method:** {$this->visit->check_in_method?->label()}")
            ->line("**Check-in Time:** {$this->visit->checked_in_at?->format('g:i A')}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Visitor Arrived',
            'message' => "{$this->visit->visitor->full_name} has checked in.",
            'visit_id' => $this->visit->id,
            'visitor_name' => $this->visit->visitor->full_name,
            'check_in_method' => $this->visit->check_in_method?->label(),
        ];
    }
}
