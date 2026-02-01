<?php

namespace App\Notifications;

use App\Models\TrainingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder notification sent before a training session starts.
 */
class TrainingSessionReminder extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TrainingSession $session
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

        return (new MailMessage)
            ->subject('Reminder: Training Session Tomorrow - '.$this->session->display_title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('This is a reminder that you are enrolled in a training session tomorrow:')
            ->line("**Course:** {$course->title}")
            ->line("**Session:** {$this->session->display_title}")
            ->line("**Date:** {$this->session->date_range}")
            ->lineIf($this->session->time_range, "**Time:** {$this->session->time_range}")
            ->lineIf($this->session->location, "**Location:** {$this->session->location}")
            ->lineIf($this->session->virtual_link, "**Virtual Link:** {$this->session->virtual_link}")
            ->action('View Session Details', $this->getSessionUrl())
            ->line('Please ensure you are prepared and arrive on time.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'training_session_reminder',
            'session_id' => $this->session->id,
            'session_title' => $this->session->display_title,
            'course_title' => $this->session->course?->title,
            'start_date' => $this->session->start_date->format('Y-m-d'),
            'message' => "Reminder: {$this->session->display_title} is tomorrow ({$this->session->date_range}).",
        ];
    }

    /**
     * Get the URL for viewing the session.
     */
    protected function getSessionUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/my/training/sessions/{$this->session->id}";
    }
}
