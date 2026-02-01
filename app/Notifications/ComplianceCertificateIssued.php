<?php

namespace App\Notifications;

use App\Models\ComplianceCertificate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when a compliance certificate is issued.
 */
class ComplianceCertificateIssued extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ComplianceCertificate $certificate
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
        $assignment = $this->certificate->complianceAssignment;
        $course = $assignment->complianceCourse;

        $mailMessage = (new MailMessage)
            ->subject('🎓 Certificate Issued: '.$course->course->title)
            ->greeting('Congratulations '.$notifiable->name.'!')
            ->line('Your compliance training certificate has been issued.')
            ->line("**Course:** {$course->course->title}")
            ->line("**Certificate Number:** {$this->certificate->certificate_number}")
            ->line("**Issued On:** {$this->certificate->issued_date->format('M d, Y')}");

        if ($this->certificate->valid_until) {
            $mailMessage->line("**Valid Until:** {$this->certificate->valid_until->format('M d, Y')}");
        }

        if ($this->certificate->final_score !== null) {
            $mailMessage->line("**Score:** {$this->certificate->final_score}%");
        }

        return $mailMessage
            ->action('Download Certificate', $this->getCertificateUrl())
            ->line('You can download your certificate at any time from your compliance training dashboard.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $assignment = $this->certificate->complianceAssignment;
        $course = $assignment->complianceCourse;

        return [
            'type' => 'compliance_certificate_issued',
            'certificate_id' => $this->certificate->id,
            'certificate_number' => $this->certificate->certificate_number,
            'assignment_id' => $assignment->id,
            'course_id' => $course->id,
            'course_title' => $course->course->title,
            'issued_date' => $this->certificate->issued_date->format('Y-m-d'),
            'valid_until' => $this->certificate->valid_until?->format('Y-m-d'),
            'message' => "Certificate issued for {$course->course->title} (#{$this->certificate->certificate_number}).",
        ];
    }

    /**
     * Get the URL for downloading the certificate.
     */
    protected function getCertificateUrl(): string
    {
        $tenant = tenant();
        $scheme = config('app.env') === 'production' ? 'https' : 'http';

        return "{$scheme}://{$tenant->slug}.".config('app.main_domain')."/my/compliance/certificates/{$this->certificate->id}/download";
    }
}
