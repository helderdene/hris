<?php

namespace App\Notifications;

use App\Models\PreboardingChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to HR when all preboarding items are completed.
 */
class PreboardingCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PreboardingChecklist $checklist
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
        $this->checklist->load('offer.jobApplication.candidate');
        $candidateName = $this->checklist->offer?->jobApplication?->candidate?->full_name ?? 'A new hire';

        return (new MailMessage)
            ->subject("Pre-boarding Completed - {$candidateName}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$candidateName} has completed all pre-boarding requirements.")
            ->action('View Checklist', $this->getChecklistUrl())
            ->line('The new hire is ready for onboarding.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->checklist->load('offer.jobApplication.candidate');
        $candidateName = $this->checklist->offer?->jobApplication?->candidate?->full_name ?? 'A new hire';

        return [
            'type' => 'preboarding_completed',
            'checklist_id' => $this->checklist->id,
            'message' => "{$candidateName} has completed all pre-boarding requirements.",
        ];
    }

    /**
     * Get the URL for viewing the checklist.
     */
    protected function getChecklistUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/preboarding/{$this->checklist->id}";
    }
}
