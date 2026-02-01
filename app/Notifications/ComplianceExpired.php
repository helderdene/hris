<?php

namespace App\Notifications;

use App\Models\ComplianceAssignment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a compliance training completion has expired.
 */
class ComplianceExpired extends Notification
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
        $expiredDate = $this->assignment->valid_until->format('M d, Y');

        return (new MailMessage)
            ->subject('⚠️ Compliance Training Expired: '.$course->course->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('**Your compliance training has expired and you are no longer compliant.**')
            ->line("**Course:** {$course->course->title}")
            ->line("**Expired On:** {$expiredDate}")
            ->line('You are required to retake this training to restore your compliance status.')
            ->action('Retake Training', $this->getTrainingUrl())
            ->line('Please complete this training as soon as possible.')
            ->line('Your manager and HR have been notified of this expired training.');
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
            'type' => 'compliance_expired',
            'assignment_id' => $this->assignment->id,
            'course_id' => $course->id,
            'course_title' => $course->course->title,
            'expired_at' => $this->assignment->valid_until->format('Y-m-d'),
            'message' => "EXPIRED: Your {$course->course->title} training has expired. Please retake immediately.",
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
