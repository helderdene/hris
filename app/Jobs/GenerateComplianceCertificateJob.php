<?php

namespace App\Jobs;

use App\Models\ComplianceAssignment;
use App\Services\ComplianceCertificateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to generate a compliance certificate for a completed assignment.
 */
class GenerateComplianceCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ComplianceCertificateService $certificateService): void
    {
        // Check if assignment is completed
        if (! $this->assignment->isCompleted()) {
            return;
        }

        // Check if certificate already exists
        if ($this->assignment->certificate()->exists()) {
            return;
        }

        // Issue the certificate
        $certificateService->issueCertificate($this->assignment);
    }
}
