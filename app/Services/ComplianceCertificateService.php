<?php

namespace App\Services;

use App\Models\ComplianceAssignment;
use App\Models\ComplianceCertificate;
use App\Models\Employee;
use App\Notifications\ComplianceCertificateIssued;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Service for managing compliance training certificates.
 *
 * Handles certificate generation, PDF creation, and revocation.
 */
class ComplianceCertificateService
{
    /**
     * Issue a certificate for a completed assignment.
     */
    public function issueCertificate(ComplianceAssignment $assignment): ComplianceCertificate
    {
        // Check if certificate already exists
        $existingCertificate = $assignment->certificate;
        if ($existingCertificate && ! $existingCertificate->is_revoked) {
            return $existingCertificate;
        }

        $complianceCourse = $assignment->complianceCourse;
        $employee = $assignment->employee;

        $certificateNumber = $this->generateCertificateNumber();
        $issuedDate = now();
        $validUntil = $complianceCourse->validity_months
            ? $issuedDate->copy()->addMonths($complianceCourse->validity_months)
            : null;

        $certificate = ComplianceCertificate::create([
            'compliance_assignment_id' => $assignment->id,
            'certificate_number' => $certificateNumber,
            'issued_date' => $issuedDate->toDateString(),
            'valid_until' => $validUntil?->toDateString(),
            'final_score' => $assignment->final_score,
            'metadata' => [
                'course_title' => $complianceCourse->course->title,
                'course_code' => $complianceCourse->course->code,
                'employee_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position?->title,
                'total_time_minutes' => $assignment->total_time_minutes,
            ],
        ]);

        // Generate PDF
        $this->generatePdf($certificate);

        // Send notification
        $employee->notify(new ComplianceCertificateIssued($certificate));

        return $certificate;
    }

    /**
     * Generate a PDF certificate.
     */
    public function generatePdf(ComplianceCertificate $certificate): string
    {
        $assignment = $certificate->complianceAssignment;
        $complianceCourse = $assignment->complianceCourse;
        $course = $complianceCourse->course;
        $employee = $assignment->employee;

        $data = [
            'certificate' => $certificate,
            'course' => $course,
            'employee' => $employee,
            'issuedDate' => $certificate->issued_date->format('F j, Y'),
            'validUntil' => $certificate->valid_until?->format('F j, Y'),
            'score' => $certificate->final_score,
        ];

        $pdf = Pdf::loadView('certificates.compliance', $data);
        $pdf->setPaper('A4', 'landscape');

        $fileName = "certificate-{$certificate->certificate_number}.pdf";
        $filePath = "certificates/compliance/{$fileName}";

        Storage::disk('private')->put($filePath, $pdf->output());

        $certificate->update([
            'file_path' => $filePath,
            'file_name' => $fileName,
        ]);

        return $filePath;
    }

    /**
     * Generate a unique certificate number.
     */
    public function generateCertificateNumber(): string
    {
        return ComplianceCertificate::generateCertificateNumber();
    }

    /**
     * Revoke a certificate.
     */
    public function revokeCertificate(
        ComplianceCertificate $certificate,
        Employee $revokedBy,
        string $reason
    ): ComplianceCertificate {
        $certificate->revoke($revokedBy, $reason);

        return $certificate->fresh();
    }

    /**
     * Download a certificate PDF.
     */
    public function downloadCertificate(ComplianceCertificate $certificate): ?string
    {
        if (! $certificate->file_path) {
            $this->generatePdf($certificate);
            $certificate->refresh();
        }

        if (Storage::disk('private')->exists($certificate->file_path)) {
            return Storage::disk('private')->path($certificate->file_path);
        }

        // Regenerate if file doesn't exist
        $this->generatePdf($certificate);

        return Storage::disk('private')->path($certificate->file_path);
    }

    /**
     * Get certificates expiring within a given number of days.
     *
     * @return \Illuminate\Database\Eloquent\Collection<ComplianceCertificate>
     */
    public function getExpiringCertificates(int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return ComplianceCertificate::query()
            ->expiringSoon($days)
            ->with(['complianceAssignment.employee', 'complianceAssignment.complianceCourse.course'])
            ->get();
    }

    /**
     * Get expired certificates.
     *
     * @return \Illuminate\Database\Eloquent\Collection<ComplianceCertificate>
     */
    public function getExpiredCertificates(): \Illuminate\Database\Eloquent\Collection
    {
        return ComplianceCertificate::query()
            ->expired()
            ->with(['complianceAssignment.employee', 'complianceAssignment.complianceCourse.course'])
            ->get();
    }

    /**
     * Verify a certificate by number.
     *
     * @return array{valid: bool, certificate: ?ComplianceCertificate, message: string}
     */
    public function verifyCertificate(string $certificateNumber): array
    {
        $certificate = ComplianceCertificate::where('certificate_number', $certificateNumber)->first();

        if (! $certificate) {
            return [
                'valid' => false,
                'certificate' => null,
                'message' => 'Certificate not found.',
            ];
        }

        if ($certificate->is_revoked) {
            return [
                'valid' => false,
                'certificate' => $certificate,
                'message' => 'Certificate has been revoked.',
            ];
        }

        if ($certificate->isExpired()) {
            return [
                'valid' => false,
                'certificate' => $certificate,
                'message' => 'Certificate has expired.',
            ];
        }

        return [
            'valid' => true,
            'certificate' => $certificate,
            'message' => 'Certificate is valid.',
        ];
    }
}
