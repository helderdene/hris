<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to the employee when their probation period is extended.
 */
class ProbationExtendedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Employee $employee,
        public ProbationaryEvaluation $evaluation,
        public int $extensionMonths
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
        return (new MailMessage)
            ->subject('Probation Period Extended')
            ->greeting("Hello, {$this->employee->full_name}!")
            ->line('We would like to inform you that your probationary period has been extended.')
            ->line("**Extension Period:** {$this->extensionMonths} month(s)")
            ->line('This extension provides additional time for you to demonstrate your capabilities and meet performance expectations.')
            ->line('Please speak with your supervisor or HR for more details about the evaluation and areas for improvement.')
            ->action('View My Status', $this->getStatusUrl())
            ->line('We are committed to supporting your growth and development.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'probation_extended',
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_name,
            'evaluation_id' => $this->evaluation->id,
            'extension_months' => $this->extensionMonths,
            'message' => "Your probationary period has been extended by {$this->extensionMonths} month(s).",
        ];
    }

    /**
     * Get the URL for viewing probationary status.
     */
    protected function getStatusUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/probationary-status';
    }
}
