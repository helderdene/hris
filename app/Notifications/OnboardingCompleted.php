<?php

namespace App\Notifications;

use App\Models\OnboardingChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to HR when all onboarding tasks are complete.
 */
class OnboardingCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public OnboardingChecklist $checklist
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
        $this->checklist->load('employee');

        return (new MailMessage)
            ->subject("Onboarding Completed: {$this->checklist->employee->full_name}")
            ->greeting('Great news!')
            ->line("All onboarding tasks for **{$this->checklist->employee->full_name}** have been completed.")
            ->line("**Completed on:** {$this->checklist->completed_at->format('F j, Y')}")
            ->action('View Onboarding Details', $this->getChecklistUrl())
            ->line('The employee is now fully onboarded and ready to start.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'onboarding_completed',
            'checklist_id' => $this->checklist->id,
            'employee_id' => $this->checklist->employee_id,
            'employee_name' => $this->checklist->employee?->full_name,
            'message' => "Onboarding completed for {$this->checklist->employee?->full_name}.",
        ];
    }

    /**
     * Get the URL for viewing the checklist.
     */
    protected function getChecklistUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/hr/onboarding/{$this->checklist->id}";
    }
}
