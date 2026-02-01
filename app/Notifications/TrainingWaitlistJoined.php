<?php

namespace App\Notifications;

use App\Models\TrainingWaitlist;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an employee joins a training session waitlist.
 */
class TrainingWaitlistJoined extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public TrainingWaitlist $waitlist
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
        $session = $this->waitlist->session;
        $course = $session->course;

        return (new MailMessage)
            ->subject('Added to Waitlist - '.$session->display_title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You have been added to the waitlist for the following training session:')
            ->line("**Course:** {$course->title}")
            ->line("**Session:** {$session->display_title}")
            ->line("**Date:** {$session->date_range}")
            ->line("**Your Position:** #{$this->waitlist->position}")
            ->line('The session is currently full. You will be automatically enrolled if a spot becomes available.')
            ->line("We'll notify you if you are promoted to the enrollment list.")
            ->action('View Session Details', $this->getSessionUrl());
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $session = $this->waitlist->session;

        return [
            'type' => 'training_waitlist_joined',
            'waitlist_id' => $this->waitlist->id,
            'session_id' => $session->id,
            'session_title' => $session->display_title,
            'course_title' => $session->course?->title,
            'position' => $this->waitlist->position,
            'message' => "You are #{$this->waitlist->position} on the waitlist for {$session->display_title}.",
        ];
    }

    /**
     * Get the URL for viewing the session.
     */
    protected function getSessionUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/my/training/sessions/{$this->waitlist->training_session_id}";
    }
}
