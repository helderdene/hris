<?php

namespace App\Notifications;

use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to employees when their document request status is updated.
 */
class DocumentRequestStatusUpdated extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public DocumentRequest $documentRequest
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_request_status_updated',
            'document_request_id' => $this->documentRequest->id,
            'document_type' => $this->documentRequest->document_type->label(),
            'status' => $this->documentRequest->status->value,
            'status_label' => $this->documentRequest->status->label(),
            'title' => 'Document Request Updated',
            'message' => "Your {$this->documentRequest->document_type->label()} request has been updated to {$this->documentRequest->status->label()}.",
        ];
    }
}
