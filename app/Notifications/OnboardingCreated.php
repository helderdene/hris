<?php

namespace App\Notifications;

use App\Models\OnboardingChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to new employee when their onboarding checklist is created.
 */
class OnboardingCreated extends Notification
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
            ->subject('Welcome! Your Onboarding Has Begun')
            ->greeting("Welcome, {$this->checklist->employee->first_name}!")
            ->line('We are excited to have you join our team! Your onboarding checklist has been created.')
            ->line('Our team is working on preparing everything you need for your first day.')
            ->when($this->checklist->start_date, function (MailMessage $message) {
                $message->line("**Start Date:** {$this->checklist->start_date->format('F j, Y')}");
            })
            ->action('View Your Onboarding Status', $this->getChecklistUrl())
            ->line('If you have any questions, please contact HR.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'onboarding_created',
            'checklist_id' => $this->checklist->id,
            'employee_id' => $this->checklist->employee_id,
            'message' => 'Your onboarding checklist has been created. Check the status of your onboarding tasks.',
        ];
    }

    /**
     * Get the URL for viewing the checklist.
     */
    protected function getChecklistUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/onboarding';
    }
}
