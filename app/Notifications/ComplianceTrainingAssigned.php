<?php

namespace App\Notifications;

use App\Models\ComplianceAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a compliance training is assigned to an employee.
 */
class ComplianceTrainingAssigned extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment
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
        $dueDate = $this->assignment->due_date?->format('M d, Y');

        $mailMessage = (new MailMessage)
            ->subject('Mandatory Training Assigned: '.$course->course->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('You have been assigned a mandatory compliance training that requires your completion.')
            ->line("**Course:** {$course->course->title}")
            ->lineIf($course->course->description, "**Description:** {$course->course->description}");

        if ($dueDate) {
            $mailMessage->line("**Due Date:** {$dueDate}");
        }

        if ($course->estimated_duration_minutes) {
            $hours = floor($course->estimated_duration_minutes / 60);
            $minutes = $course->estimated_duration_minutes % 60;
            $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes} minutes";
            $mailMessage->line("**Estimated Duration:** {$duration}");
        }

        return $mailMessage
            ->action('Start Training', $this->getTrainingUrl())
            ->line('Please complete this training before the due date to maintain compliance.');
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
            'type' => 'compliance_training_assigned',
            'assignment_id' => $this->assignment->id,
            'course_id' => $course->id,
            'course_title' => $course->course->title,
            'due_date' => $this->assignment->due_date?->format('Y-m-d'),
            'message' => "Mandatory training assigned: {$course->course->title}. ".
                ($this->assignment->due_date ? "Due by {$this->assignment->due_date->format('M d, Y')}." : ''),
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
