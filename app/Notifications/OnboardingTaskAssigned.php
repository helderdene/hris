<?php

namespace App\Notifications;

use App\Models\OnboardingChecklistItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notification sent to IT/Admin/HR users when they have onboarding tasks assigned.
 */
class OnboardingTaskAssigned extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  Collection<int, OnboardingChecklistItem>  $items
     */
    public function __construct(
        public Collection $items
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
        $count = $this->items->count();
        $firstItem = $this->items->first();
        $firstItem?->load('checklist.employee');

        $employeeName = $firstItem?->checklist?->employee?->full_name ?? 'a new employee';

        $message = (new MailMessage)
            ->subject("New Onboarding Tasks: {$employeeName}")
            ->greeting('Hello!')
            ->line("You have been assigned {$count} onboarding ".($count === 1 ? 'task' : 'tasks')." for {$employeeName}.");

        // List the tasks
        $taskList = $this->items->map(fn ($item) => "- {$item->name}")->join("\n");
        $message->line("**Tasks:**\n{$taskList}");

        // Show earliest due date
        $earliestDue = $this->items->pluck('due_date')->filter()->min();
        if ($earliestDue) {
            $message->line("**Earliest Due:** {$earliestDue->format('F j, Y')}");
        }

        return $message
            ->action('View Onboarding Tasks', $this->getTasksUrl())
            ->line('Please complete these tasks before their due dates.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $firstItem = $this->items->first();
        $firstItem?->load('checklist.employee');

        return [
            'type' => 'onboarding_task_assigned',
            'item_ids' => $this->items->pluck('id')->toArray(),
            'checklist_id' => $firstItem?->onboarding_checklist_id,
            'employee_name' => $firstItem?->checklist?->employee?->full_name,
            'count' => $this->items->count(),
            'message' => "You have {$this->items->count()} new onboarding ".($this->items->count() === 1 ? 'task' : 'tasks').' to complete.',
        ];
    }

    /**
     * Get the URL for viewing the tasks.
     */
    protected function getTasksUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain').'/onboarding/tasks';
    }
}
