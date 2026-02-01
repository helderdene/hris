<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to the employee when they are regularized after passing probation.
 */
class RegularizationApprovedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Employee $employee,
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
        return (new MailMessage)
            ->subject('Congratulations on Your Regularization!')
            ->greeting("Congratulations, {$this->employee->full_name}!")
            ->line('We are pleased to inform you that you have successfully completed your probationary period.')
            ->line('Based on your performance evaluation, you have been officially regularized as a permanent employee.')
            ->line("**Regularization Date:** {$this->employee->regularization_date}")
            ->line('We appreciate your dedication and hard work during the probationary period.')
            ->action('View My Status', $this->getStatusUrl())
            ->line('Welcome to the team as a regular employee!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'regularization_approved',
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_name,
            'evaluation_id' => $this->evaluation->id,
            'regularization_date' => $this->employee->regularization_date,
            'message' => 'Congratulations! You have been regularized as a permanent employee.',
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
