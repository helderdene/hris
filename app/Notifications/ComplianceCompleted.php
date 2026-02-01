<?php

namespace App\Notifications;

use App\Models\ComplianceAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an employee completes a compliance training.
 */
class ComplianceCompleted extends Notification
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
        $completedDate = $this->assignment->completed_at->format('M d, Y');

        $mailMessage = (new MailMessage)
            ->subject('✓ Compliance Training Completed: '.$course->course->title)
            ->greeting('Congratulations '.$notifiable->name.'!')
            ->line('You have successfully completed a mandatory compliance training.')
            ->line("**Course:** {$course->course->title}")
            ->line("**Completed On:** {$completedDate}");

        if ($this->assignment->final_score !== null) {
            $mailMessage->line("**Final Score:** {$this->assignment->final_score}%");
        }

        if ($this->assignment->total_time_minutes) {
            $hours = floor($this->assignment->total_time_minutes / 60);
            $minutes = $this->assignment->total_time_minutes % 60;
            $duration = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes} minutes";
            $mailMessage->line("**Time Spent:** {$duration}");
        }

        if ($this->assignment->valid_until) {
            $mailMessage->line("**Valid Until:** {$this->assignment->valid_until->format('M d, Y')}");
        }

        return $mailMessage
            ->action('View My Compliance Training', $this->getTrainingUrl())
            ->line('Your certificate will be available shortly if applicable.');
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
            'type' => 'compliance_completed',
            'assignment_id' => $this->assignment->id,
            'course_id' => $course->id,
            'course_title' => $course->course->title,
            'completed_at' => $this->assignment->completed_at->format('Y-m-d H:i:s'),
            'final_score' => $this->assignment->final_score,
            'valid_until' => $this->assignment->valid_until?->format('Y-m-d'),
            'message' => "Completed: {$course->course->title}".
                ($this->assignment->final_score !== null ? " (Score: {$this->assignment->final_score}%)" : ''),
        ];
    }

    /**
     * Get the URL for viewing the training.
     */
    protected function getTrainingUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/compliance';
    }
}
