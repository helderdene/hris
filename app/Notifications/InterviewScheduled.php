<?php

namespace App\Notifications;

use App\Models\Interview;
use App\Services\InterviewCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an interview is scheduled.
 */
class InterviewScheduled extends Notification
{
    use Queueable;

    public function __construct(
        public Interview $interview
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
        $this->interview->load(['jobApplication.candidate', 'jobApplication.jobPosting']);

        $candidate = $this->interview->jobApplication->candidate->full_name;
        $position = $this->interview->jobApplication->jobPosting->title;
        $date = $this->interview->scheduled_at->format('l, F j, Y \a\t g:i A');

        $mail = (new MailMessage)
            ->subject("Interview Scheduled: {$this->interview->title}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('An interview has been scheduled.')
            ->line("**Title:** {$this->interview->title}")
            ->line("**Candidate:** {$candidate}")
            ->line("**Position:** {$position}")
            ->line("**Date:** {$date}")
            ->line("**Duration:** {$this->interview->duration_minutes} minutes");

        if ($this->interview->location) {
            $mail->line("**Location:** {$this->interview->location}");
        }

        if ($this->interview->meeting_url) {
            $mail->line("**Meeting URL:** {$this->interview->meeting_url}");
        }

        $mail->action('View Interview', $this->getInterviewUrl());

        $ics = app(InterviewCalendarService::class)->generateIcs($this->interview);
        $mail->attachData($ics, 'interview.ics', [
            'mime' => 'text/calendar',
        ]);

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'interview_scheduled',
            'title' => 'Interview Scheduled',
            'interview_id' => $this->interview->id,
            'interview_title' => $this->interview->title,
            'scheduled_at' => $this->interview->scheduled_at->toDateTimeString(),
            'message' => "Interview \"{$this->interview->title}\" scheduled for {$this->interview->scheduled_at->format('M j, Y g:i A')}.",
            'url' => "/recruitment/interviews/{$this->interview->id}",
        ];
    }

    protected function getInterviewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';
        $slug = $tenant?->slug ?? 'app';

        return "{$scheme}://{$slug}.".config('app.main_domain')."/recruitment/interviews/{$this->interview->id}";
    }
}
