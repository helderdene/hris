<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a bulk payslip PDF is ready for download.
 */
class BulkPayslipReady extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $filePath,
        public string $fileName,
        public int $entryCount
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
            'type' => 'bulk_payslip_ready',
            'title' => 'Bulk Payslips Ready',
            'message' => "Your bulk payslip download with {$this->entryCount} payslips is ready.",
            'file_path' => $this->filePath,
            'file_name' => $this->fileName,
            'entry_count' => $this->entryCount,
        ];
    }
}
