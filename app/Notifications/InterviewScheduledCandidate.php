<?php

namespace App\Notifications;

use App\Models\Interview;
use App\Services\InterviewCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to the candidate when their interview is scheduled.
 *
 * Delivered on-demand to the candidate's email address, since candidates
 * do not have user accounts.
 */
class InterviewScheduledCandidate extends Notification
{
    use Queueable;

    public function __construct(
        public Interview $interview
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
        $this->interview->load(['jobApplication.candidate', 'jobApplication.jobPosting']);

        $candidate = $this->interview->jobApplication->candidate;
        $position = $this->interview->jobApplication->jobPosting->title;
        $date = $this->interview->scheduled_at->format('l, F j, Y \a\t g:i A');

        $mail = (new MailMessage)
            ->subject("Interview Invitation: {$position}")
            ->greeting('Dear '.$candidate->full_name.',')
            ->line("Your interview for the position of **{$position}** has been scheduled.")
            ->line("**Date:** {$date}")
            ->line("**Duration:** {$this->interview->duration_minutes} minutes");

        if ($this->interview->location) {
            $mail->line("**Location:** {$this->interview->location}");
        }

        if ($this->interview->meeting_url) {
            $mail->line("**Meeting URL:** {$this->interview->meeting_url}");
        }

        $mail->line('A calendar invitation is attached. We look forward to speaking with you.');

        $ics = app(InterviewCalendarService::class)->generateIcs($this->interview);
        $mail->attachData($ics, 'interview.ics', [
            'mime' => 'text/calendar',
        ]);

        return $mail;
    }
}
