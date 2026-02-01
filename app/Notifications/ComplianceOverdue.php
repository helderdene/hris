<?php

namespace App\Notifications;

use App\Models\ComplianceAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a compliance training becomes overdue.
 */
class ComplianceOverdue extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment,
        public int $daysOverdue = 0
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

        $overdueMessage = $this->daysOverdue > 0
            ? "This training has been overdue for **{$this->daysOverdue} ".($this->daysOverdue === 1 ? 'day' : 'days').'**.'
            : 'This training is now overdue.';

        return (new MailMessage)
            ->subject('OVERDUE: Mandatory Compliance Training - '.$course->course->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('⚠️ **Your mandatory compliance training is overdue.**')
            ->line($overdueMessage)
            ->line("**Course:** {$course->course->title}")
            ->line("**Due Date:** {$dueDate}")
            ->line("**Progress:** {$this->assignment->getCompletionPercentage()}% complete")
            ->action('Complete Training Now', $this->getTrainingUrl())
            ->line('Please complete this training immediately to avoid further compliance issues.')
            ->line('Your manager and HR have been notified of this overdue training.');
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
            'type' => 'compliance_overdue',
            'assignment_id' => $this->assignment->id,
            'course_id' => $course->id,
            'course_title' => $course->course->title,
            'due_date' => $this->assignment->due_date->format('Y-m-d'),
            'days_overdue' => $this->daysOverdue,
            'completion_percentage' => $this->assignment->getCompletionPercentage(),
            'message' => "OVERDUE: {$course->course->title} was due on {$dueDate}. Please complete immediately.",
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
