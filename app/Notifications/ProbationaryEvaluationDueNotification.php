<?php

namespace App\Notifications;

use App\Models\ProbationaryEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to evaluators (managers) when a probationary evaluation is due or upcoming.
 */
class ProbationaryEvaluationDueNotification extends Notification
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
        $milestone = $this->evaluation->milestone;
        $isOverdue = $this->evaluation->due_date && $this->evaluation->due_date < now();

        $subject = $isOverdue
            ? "OVERDUE: {$milestone->label()} Evaluation for {$employee->full_name}"
            : "Upcoming: {$milestone->label()} Evaluation for {$employee->full_name}";

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Hello '.$notifiable->name.'!');

        if ($isOverdue) {
            $message->line("The {$milestone->label()} probationary evaluation for **{$employee->full_name}** is overdue.");
        } else {
            $message->line("A {$milestone->label()} probationary evaluation for **{$employee->full_name}** is due soon.");
        }

        $message
            ->line("**Employee:** {$employee->full_name}")
            ->line("**Milestone:** {$milestone->label()}")
            ->line("**Due Date:** {$this->evaluation->due_date->format('M d, Y')}")
            ->action('Complete Evaluation', $this->getEvaluationUrl())
            ->line('Please complete this evaluation before the due date.');

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isOverdue = $this->evaluation->due_date && $this->evaluation->due_date < now();

        return [
            'type' => 'probationary_evaluation_due',
            'evaluation_id' => $this->evaluation->id,
            'employee_id' => $this->evaluation->employee_id,
            'employee_name' => $this->evaluation->employee->full_name,
            'milestone' => $this->evaluation->milestone->value,
            'milestone_label' => $this->evaluation->milestone->label(),
            'due_date' => $this->evaluation->due_date?->format('Y-m-d'),
            'is_overdue' => $isOverdue,
            'message' => $isOverdue
                ? "OVERDUE: {$this->evaluation->milestone->label()} evaluation for {$this->evaluation->employee->full_name}"
                : "{$this->evaluation->milestone->label()} evaluation for {$this->evaluation->employee->full_name} is due on {$this->evaluation->due_date->format('M d, Y')}",
        ];
    }

    /**
     * Get the URL for the evaluation.
     */
    protected function getEvaluationUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/manager/probationary-evaluations/{$this->evaluation->id}";
    }
}
