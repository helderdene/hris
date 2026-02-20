<?php

namespace App\Notifications;

use App\Models\VisitorVisit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VisitorRejected extends Notification
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
        $mail = (new MailMessage)
            ->subject('Visit Request Update')
            ->greeting("Hello {$this->visit->visitor->first_name},")
            ->line('Unfortunately, your visit request has been declined.');

        if ($this->visit->rejection_reason) {
            $mail->line("**Reason:** {$this->visit->rejection_reason}");
        }

        return $mail->line('If you have questions, please contact us directly.');
    }
}
