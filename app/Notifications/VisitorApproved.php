<?php

namespace App\Notifications;

use App\Models\VisitorVisit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitorApproved extends Notification
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
            ->subject('Your Visit Has Been Approved')
            ->markdown('mail.visitor.approved', ['visit' => $this->visit]);
    }
}
