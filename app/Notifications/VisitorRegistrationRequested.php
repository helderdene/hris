<?php

namespace App\Notifications;

use App\Models\VisitorVisit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitorRegistrationRequested extends Notification
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
            ->subject('New Visitor Registration Request')
            ->greeting('New Visitor Request')
            ->line("{$visitor->full_name}".($visitor->company ? " from {$visitor->company}" : '').' has requested to visit.')
            ->line("**Purpose:** {$this->visit->purpose}")
            ->line('**Expected:** '.($this->visit->expected_at?->format('M d, Y g:i A') ?? 'Not specified'))
            ->line('**Location:** '.$this->visit->workLocation?->name)
            ->line('Please review and approve or reject this visit request.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New Visitor Registration',
            'message' => "{$this->visit->visitor->full_name} has requested to visit on ".($this->visit->expected_at?->format('M d, Y') ?? 'TBD'),
            'visit_id' => $this->visit->id,
            'visitor_name' => $this->visit->visitor->full_name,
        ];
    }
}
