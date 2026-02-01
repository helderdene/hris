<?php

namespace App\Notifications;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder to panelists to submit interview feedback.
 */
class InterviewFeedbackRequested extends Notification
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
        $this->interview->load(['jobApplication.candidate']);

        $candidate = $this->interview->jobApplication->candidate->full_name;

        return (new MailMessage)
            ->subject("Feedback Requested: {$this->interview->title}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Please submit your feedback for the following interview.')
            ->line("**Title:** {$this->interview->title}")
            ->line("**Candidate:** {$candidate}")
            ->action('Submit Feedback', $this->getInterviewUrl())
            ->line('Your feedback helps us make better hiring decisions.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'interview_feedback_requested',
            'title' => 'Feedback Requested',
            'interview_id' => $this->interview->id,
            'interview_title' => $this->interview->title,
            'message' => "Please submit your feedback for \"{$this->interview->title}\".",
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
