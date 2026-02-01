<?php

namespace App\Notifications;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an interview is cancelled.
 */
class InterviewCancelled extends Notification
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
        $date = $this->interview->scheduled_at->format('l, F j, Y \a\t g:i A');

        return (new MailMessage)
            ->subject("Interview Cancelled: {$this->interview->title}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('The following interview has been cancelled.')
            ->line("**Title:** {$this->interview->title}")
            ->line("**Candidate:** {$candidate}")
            ->line("**Originally Scheduled:** {$date}")
            ->line("**Reason:** {$this->interview->cancellation_reason}")
            ->line('Please update your calendar accordingly.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'interview_cancelled',
            'title' => 'Interview Cancelled',
            'interview_id' => $this->interview->id,
            'interview_title' => $this->interview->title,
            'cancellation_reason' => $this->interview->cancellation_reason,
            'message' => "Interview \"{$this->interview->title}\" has been cancelled.",
            'url' => "/recruitment/interviews/{$this->interview->id}",
        ];
    }
}
