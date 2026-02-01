<?php

namespace App\Notifications;

use App\Models\ComplianceAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder notification sent before a compliance training is due.
 */
class ComplianceDueReminder extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment,
        public int $daysUntilDue
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
        $course = $this->assignment->complianceCourse;
        $dueDate = $this->assignment->due_date->format('M d, Y');

        $urgencyMessage = match (true) {
            $this->daysUntilDue <= 1 => 'This training is due tomorrow!',
            $this->daysUntilDue <= 3 => 'This training is due very soon.',
            $this->daysUntilDue <= 7 => 'Please prioritize completing this training.',
            default => 'Please plan to complete this training soon.',
        };

        $subject = $this->daysUntilDue === 1
            ? 'URGENT: Compliance Training Due Tomorrow'
            : "Compliance Training Due in {$this->daysUntilDue} Days";

        return (new MailMessage)
            ->subject($subject.' - '.$course->course->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("Your mandatory compliance training is due in **{$this->daysUntilDue} ".($this->daysUntilDue === 1 ? 'day' : 'days').'**. '.$urgencyMessage)
            ->line("**Course:** {$course->course->title}")
            ->line("**Due Date:** {$dueDate}")
            ->line("**Progress:** {$this->assignment->getCompletionPercentage()}% complete")
            ->action('Continue Training', $this->getTrainingUrl())
            ->line('Failure to complete mandatory training may result in compliance violations.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $course = $this->assignment->complianceCourse;

        return [
            'type' => 'compliance_due_reminder',
            'assignment_id' => $this->assignment->id,
            'course_id' => $course->id,
            'course_title' => $course->course->title,
            'due_date' => $this->assignment->due_date->format('Y-m-d'),
            'days_until_due' => $this->daysUntilDue,
            'completion_percentage' => $this->assignment->getCompletionPercentage(),
            'message' => "{$course->course->title} is due in {$this->daysUntilDue} ".($this->daysUntilDue === 1 ? 'day' : 'days')." ({$this->assignment->getCompletionPercentage()}% complete).",
        ];
    }

    /**
     * Get the URL for viewing the training.
     */
    protected function getTrainingUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/my/compliance/{$this->assignment->id}";
    }
}
