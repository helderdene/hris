<?php

namespace App\Notifications;

use App\Models\ComplianceAssignment;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Escalation notification sent to manager/HR when an employee's compliance training is overdue.
 */
class ComplianceOverdueEscalation extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment,
        public Employee $employee,
        public int $daysOverdue,
        public string $escalationLevel = 'manager'
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
        $levelText = $this->escalationLevel === 'hr' ? 'HR Escalation' : 'Manager Alert';

        return (new MailMessage)
            ->subject("[{$levelText}] Overdue Compliance Training - {$this->employee->full_name}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('This is an escalation notice regarding an overdue mandatory compliance training.')
            ->line("**Employee:** {$this->employee->full_name} ({$this->employee->employee_number})")
            ->line("**Department:** {$this->employee->department?->name}")
            ->line("**Position:** {$this->employee->position?->title}")
            ->line('')
            ->line("**Training Course:** {$course->course->title}")
            ->line("**Due Date:** {$dueDate}")
            ->line("**Days Overdue:** {$this->daysOverdue}")
            ->line("**Progress:** {$this->assignment->getCompletionPercentage()}% complete")
            ->action('View Employee Compliance Status', $this->getComplianceUrl())
            ->line('Please follow up with the employee to ensure they complete this mandatory training.');
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
            'type' => 'compliance_overdue_escalation',
            'assignment_id' => $this->assignment->id,
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_name,
            'course_id' => $course->id,
            'course_title' => $course->course->title,
            'due_date' => $this->assignment->due_date->format('Y-m-d'),
            'days_overdue' => $this->daysOverdue,
            'escalation_level' => $this->escalationLevel,
            'message' => "{$this->employee->full_name} has overdue compliance training: {$course->course->title} ({$this->daysOverdue} days overdue).",
        ];
    }

    /**
     * Get the URL for viewing compliance status.
     */
    protected function getComplianceUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        if ($this->escalationLevel === 'hr') {
            return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/compliance/assignments?employee={$this->employee->id}&status=overdue";
        }

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/team/compliance?status=overdue';
    }
}
