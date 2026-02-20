<?php

namespace App\Notifications;

use App\Models\VisitorVisit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitorPreRegistered extends Notification
{
    public function __construct(
        public VisitorVisit $visit
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You Have Been Pre-Registered for a Visit')
            ->markdown('mail.visitor.pre-registered', ['visit' => $this->visit]);
    }
}
