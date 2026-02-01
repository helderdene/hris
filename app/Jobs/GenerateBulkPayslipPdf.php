<?php

namespace App\Jobs;

use App\Models\PayrollEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BulkPayslipReady;
use App\Services\Payroll\PayslipPdfService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job to generate bulk payslip PDFs in the background.
 *
 * Used when the number of payslips exceeds the threshold for
 * synchronous generation. Stores the result in temporary storage
 * and notifies the user when complete.
 */
class GenerateBulkPayslipPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     *
     * @param  array<int>  $entryIds  The payroll entry IDs to include
     * @param  int  $tenantId  The tenant ID for database switching
     * @param  int  $userId  The user who requested the download
     * @param  string  $format  Output format ('pdf' or 'zip')
     */
    public function __construct(
        public array $entryIds,
        public int $tenantId,
        public int $userId,
        public string $format = 'pdf'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TenantDatabaseManager $databaseManager,
        PayslipPdfService $pdfService
    ): void {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning('GenerateBulkPayslipPdf: Tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $databaseManager->switchConnection($tenant);
        app()->instance('tenant', $tenant);

        Log::info('GenerateBulkPayslipPdf: Starting bulk PDF generation', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'entry_count' => count($this->entryIds),
            'format' => $this->format,
        ]);

        $entries = PayrollEntry::query()
            ->whereIn('id', $this->entryIds)
            ->orderBy('employee_name')
            ->get();

        if ($entries->isEmpty()) {
            Log::warning('GenerateBulkPayslipPdf: No entries found', [
                'entry_ids' => $this->entryIds,
            ]);

            return;
        }

        $firstEntry = $entries->first();
        $periodName = $firstEntry->payrollPeriod->name ?? 'payroll';
        $timestamp = now()->format('Y-m-d_His');

        if ($this->format === 'zip') {
            $filePath = $pdfService->generateZip($entries);
            $fileName = "payslips_{$periodName}_{$timestamp}.zip";
            $mimeType = 'application/zip';
        } else {
            $content = $pdfService->generateBulk($entries);
            $fileName = "payslips_{$periodName}_{$timestamp}.pdf";
            $mimeType = 'application/pdf';

            $tempDir = storage_path('app/temp/payslips');

            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $filePath = $tempDir.'/'.$fileName;
            file_put_contents($filePath, $content);
        }

        $storagePath = "payslips/{$this->tenantId}/{$fileName}";
        Storage::disk('local')->put($storagePath, file_get_contents($filePath));

        @unlink($filePath);

        $this->notifyUser($storagePath, $fileName, $entries->count());

        Log::info('GenerateBulkPayslipPdf: Completed', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'entry_count' => $entries->count(),
            'file_path' => $storagePath,
        ]);
    }

    /**
     * Notify the user that the bulk payslip is ready.
     */
    protected function notifyUser(string $filePath, string $fileName, int $entryCount): void
    {
        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        $user->notify(new BulkPayslipReady($filePath, $fileName, $entryCount));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateBulkPayslipPdf failed', [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'entry_count' => count($this->entryIds),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'payslip',
            'bulk-pdf',
            'tenant:'.$this->tenantId,
            'user:'.$this->userId,
        ];
    }
}
