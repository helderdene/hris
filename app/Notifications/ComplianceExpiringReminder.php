<?php

namespace App\Notifications;

use App\Models\ComplianceAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder notification sent when a compliance training completion is about to expire.
 */
class ComplianceExpiringReminder extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment,
        public int $daysUntilExpiry
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
        $expiryDate = $this->assignment->valid_until->format('M d, Y');

        $urgencyMessage = match (true) {
            $this->daysUntilExpiry <= 7 => 'This is an urgent reminder.',
            $this->daysUntilExpiry <= 30 => 'Please plan for renewal soon.',
            default => 'Please plan accordingly.',
        };

        return (new MailMessage)
            ->subject("Compliance Training Expiring in {$this->daysUntilExpiry} Days - {$course->course->title}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("Your compliance training completion is expiring in **{$this->daysUntilExpiry} ".($this->daysUntilExpiry === 1 ? 'day' : 'days')."**. {$urgencyMessage}")
            ->line("**Course:** {$course->course->title}")
            ->line("**Original Completion Date:** {$this->assignment->completed_at->format('M d, Y')}")
            ->line("**Expiry Date:** {$expiryDate}")
            ->action('View Training', $this->getTrainingUrl())
            ->line('You will need to retake this training to maintain compliance after expiration.');
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
            'type' => 'compliance_expiring_reminder',
            'assignment_id' => $this->assignment->id,
            'course_id' => $course->id,
            'course_title' => $course->course->title,
            'completed_at' => $this->assignment->completed_at->format('Y-m-d'),
            'valid_until' => $this->assignment->valid_until->format('Y-m-d'),
            'days_until_expiry' => $this->daysUntilExpiry,
            'message' => "Your {$course->course->title} training expires in {$this->daysUntilExpiry} ".($this->daysUntilExpiry === 1 ? 'day' : 'days').'.',
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
