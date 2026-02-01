<?php

namespace App\Notifications;

use App\Models\TrainingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a training session is cancelled.
 */
class TrainingSessionCancelled extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TrainingSession $session,
        public ?string $reason = null
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
        $course = $this->session->course;

        $message = (new MailMessage)
            ->subject('Training Session Cancelled - '.$this->session->display_title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We regret to inform you that the following training session has been cancelled:')
            ->line("**Course:** {$course->title}")
            ->line("**Session:** {$this->session->display_title}")
            ->line("**Originally Scheduled:** {$this->session->date_range}");

        if ($this->reason) {
            $message->line("**Reason:** {$this->reason}");
        }

        $message->line('Your enrollment has been cancelled. We apologize for any inconvenience.')
            ->action('Browse Other Sessions', $this->getSessionsUrl())
            ->line('Please check for alternative sessions if you still need to complete this training.');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'training_session_cancelled',
            'session_id' => $this->session->id,
            'session_title' => $this->session->display_title,
            'course_title' => $this->session->course?->title,
            'original_date' => $this->session->start_date->format('Y-m-d'),
            'reason' => $this->reason,
            'message' => "The training session {$this->session->display_title} has been cancelled.",
        ];
    }

    /**
     * Get the URL for browsing sessions.
     */
    protected function getSessionsUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/training/sessions';
    }
}
