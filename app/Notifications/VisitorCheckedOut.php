<?php

namespace App\Notifications;

use App\Models\VisitorVisit;
use Illuminate\Notifications\Notification;

class VisitorCheckedOut extends Notification
{
    public function __construct(
        public VisitorVisit $visit
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $duration = $this->visit->checked_in_at && $this->visit->checked_out_at
            ? $this->visit->checked_in_at->diffForHumans($this->visit->checked_out_at, true)
            : null;

        return [
            'title' => 'Visitor Checked Out',
            'message' => "{$this->visit->visitor->full_name} has checked out.".($duration ? " Duration: {$duration}" : ''),
            'visit_id' => $this->visit->id,
            'visitor_name' => $this->visit->visitor->full_name,
        ];
    }
}
