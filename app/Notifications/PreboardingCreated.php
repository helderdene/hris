<?php

namespace App\Notifications;

use App\Models\PreboardingChecklist;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to new hire when preboarding checklist is created.
 */
class PreboardingCreated extends Notification
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
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->checklist->load('offer');

        return (new MailMessage)
            ->subject('Your Pre-boarding Checklist is Ready')
            ->greeting('Welcome!')
            ->line('Congratulations on your new role! Your pre-boarding checklist has been created.')
            ->line('Please complete all required items before your start date.')
            ->when($this->checklist->deadline, function (MailMessage $message) {
                $message->line("**Deadline:** {$this->checklist->deadline->format('F j, Y')}");
            })
            ->action('View Checklist', $this->getChecklistUrl())
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
            'type' => 'preboarding_created',
            'checklist_id' => $this->checklist->id,
            'message' => 'Your pre-boarding checklist is ready. Please complete all required items.',
        ];
    }

    /**
     * Get the URL for viewing the checklist.
     */
    protected function getChecklistUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/my/preboarding';
    }
}
