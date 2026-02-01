<?php

namespace App\Notifications;

use App\Models\TrainingEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an employee is enrolled in a training session.
 */
class TrainingEnrollmentConfirmed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TrainingEnrollment $enrollment
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
        $session = $this->enrollment->session;
        $course = $session->course;

        return (new MailMessage)
            ->subject('Training Enrollment Confirmed - '.$session->display_title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Your enrollment has been confirmed for the following training session:')
            ->line("**Course:** {$course->title}")
            ->line("**Session:** {$session->display_title}")
            ->line("**Date:** {$session->date_range}")
            ->lineIf($session->time_range, "**Time:** {$session->time_range}")
            ->lineIf($session->location, "**Location:** {$session->location}")
            ->lineIf($session->virtual_link, "**Virtual Link:** {$session->virtual_link}")
            ->action('View Session Details', $this->getSessionUrl())
            ->line('Please mark your calendar and arrive on time.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $session = $this->enrollment->session;

        return [
            'type' => 'training_enrollment_confirmed',
            'enrollment_id' => $this->enrollment->id,
            'session_id' => $session->id,
            'session_title' => $session->display_title,
            'course_title' => $session->course?->title,
            'start_date' => $session->start_date->format('Y-m-d'),
            'message' => "You are enrolled in {$session->display_title} on {$session->date_range}.",
        ];
    }

    /**
     * Get the URL for viewing the session.
     */
    protected function getSessionUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/my/training/sessions/{$this->enrollment->training_session_id}";
    }
}
