<?php

namespace App\Notifications;

use App\Models\ProbationaryEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to HR when a probationary evaluation is submitted for review.
 */
class ProbationaryEvaluationSubmittedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ProbationaryEvaluation $evaluation
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
        $employee = $this->evaluation->employee;
        $evaluator = $this->evaluation->evaluator;
        $milestone = $this->evaluation->milestone;

        return (new MailMessage)
            ->subject("{$milestone->label()} Evaluation Submitted - {$employee->full_name}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("A {$milestone->label()} probationary evaluation has been submitted for review.")
            ->line("**Employee:** {$employee->full_name}")
            ->line("**Submitted by:** {$evaluator->full_name}")
            ->line("**Milestone:** {$milestone->label()}")
            ->line("**Overall Rating:** {$this->evaluation->overall_rating}")
            ->when(
                $milestone->isFinalEvaluation() && $this->evaluation->recommendation,
                fn ($message) => $message->line("**Recommendation:** {$this->evaluation->recommendation->label()}")
            )
            ->action('Review Evaluation', $this->getReviewUrl())
            ->line('Please review and take action on this evaluation.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'probationary_evaluation_submitted',
            'evaluation_id' => $this->evaluation->id,
            'employee_id' => $this->evaluation->employee_id,
            'employee_name' => $this->evaluation->employee->full_name,
            'evaluator_name' => $this->evaluation->evaluator->full_name,
            'milestone' => $this->evaluation->milestone->value,
            'milestone_label' => $this->evaluation->milestone->label(),
            'overall_rating' => $this->evaluation->overall_rating,
            'recommendation' => $this->evaluation->recommendation?->value,
            'message' => "{$this->evaluation->milestone->label()} evaluation for {$this->evaluation->employee->full_name} has been submitted for review.",
        ];
    }

    /**
     * Get the URL for reviewing the evaluation.
     */
    protected function getReviewUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/hr/probationary-evaluations/{$this->evaluation->id}";
    }
}
