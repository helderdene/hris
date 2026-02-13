<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayrollEntryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkUpdatePayrollStatusRequest;
use App\Http\Requests\Api\DownloadBulkPayslipRequest;
use App\Http\Requests\UpdatePayrollEntryStatusRequest;
use App\Http\Resources\PayrollEntryListResource;
use App\Http\Resources\PayrollEntryResource;
use App\Jobs\GenerateBulkPayslipPdf;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayslipPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PayrollEntryController extends Controller
{
    /**
     * Display a listing of payroll entries for a period.
     */
    public function index(Request $request, string $tenant, PayrollPeriod $payrollPeriod): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = PayrollEntry::query()
            ->where('payroll_period_id', $payrollPeriod->id)
            ->orderBy('employee_name');

        if ($request->filled('status')) {
            $status = PayrollEntryStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->byStatus($status);
            }
        }

        if ($request->filled('department')) {
            $query->where('department_name', $request->input('department'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        return PayrollEntryListResource::collection($query->get());
    }

    /**
     * Display the specified payroll entry with full details.
     */
    public function show(string $tenant, PayrollEntry $payrollEntry): PayrollEntryResource
    {
        Gate::authorize('can-manage-organization');

        $payrollEntry->load([
            'payrollPeriod',
            'earnings',
            'deductions',
            'computedByUser',
            'approvedByUser',
        ]);

        return new PayrollEntryResource($payrollEntry);
    }

    /**
     * Update the status of a payroll entry.
     */
    public function updateStatus(
        UpdatePayrollEntryStatusRequest $request,
        string $tenant,
        PayrollEntry $payrollEntry
    ): PayrollEntryResource|JsonResponse {
        Gate::authorize('can-manage-organization');

        $newStatus = PayrollEntryStatus::from($request->validated('status'));

        try {
            $payrollEntry->transitionTo($newStatus);

            if ($request->filled('remarks')) {
                $payrollEntry->update(['remarks' => $request->input('remarks')]);
            }

            $payrollEntry->load([
                'payrollPeriod',
                'earnings',
                'deductions',
                'computedByUser',
                'approvedByUser',
            ]);

            return new PayrollEntryResource($payrollEntry);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk update status for multiple entries.
     */
    public function bulkUpdateStatus(
        BulkUpdatePayrollStatusRequest $request,
        string $tenant,
        PayrollPeriod $payrollPeriod
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $newStatus = PayrollEntryStatus::tryFrom($validated['status']);

        if (! $newStatus) {
            return response()->json([
                'message' => 'Invalid status provided.',
            ], 422);
        }

        $entries = PayrollEntry::query()
            ->where('payroll_period_id', $payrollPeriod->id)
            ->whereIn('id', $validated['entry_ids'])
            ->get();

        $updated = 0;
        $failed = [];

        foreach ($entries as $entry) {
            if ($entry->canTransitionTo($newStatus)) {
                $entry->transitionTo($newStatus);
                $updated++;
            } else {
                $failed[] = [
                    'id' => $entry->id,
                    'employee_name' => $entry->employee_name,
                    'reason' => "Cannot transition from {$entry->status->label()} to {$newStatus->label()}",
                ];
            }
        }

        return response()->json([
            'message' => "{$updated} entries updated successfully.",
            'updated_count' => $updated,
            'failed' => $failed,
        ]);
    }

    /**
     * Get payslip data for an entry.
     */
    public function payslip(string $tenant, PayrollEntry $payrollEntry): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $payrollEntry->load([
            'payrollPeriod.payrollCycle',
            'earnings',
            'deductions',
        ]);

        return response()->json([
            'company' => [
                'name' => tenant()?->name ?? 'Company Name',
            ],
            'period' => [
                'name' => $payrollEntry->payrollPeriod->name,
                'cutoff_start' => $payrollEntry->payrollPeriod->cutoff_start->format('F j, Y'),
                'cutoff_end' => $payrollEntry->payrollPeriod->cutoff_end->format('F j, Y'),
                'pay_date' => $payrollEntry->payrollPeriod->pay_date->format('F j, Y'),
            ],
            'employee' => [
                'number' => $payrollEntry->employee_number,
                'name' => $payrollEntry->employee_name,
                'department' => $payrollEntry->department_name,
                'position' => $payrollEntry->position_name,
            ],
            'earnings' => $payrollEntry->earnings->map(fn ($e) => [
                'description' => $e->description,
                'amount' => $e->amount,
                'formatted_amount' => number_format((float) $e->amount, 2),
            ]),
            'deductions' => $payrollEntry->deductions
                ->where('is_employee_share', true)
                ->map(fn ($d) => [
                    'description' => $d->description,
                    'amount' => $d->amount,
                    'formatted_amount' => number_format((float) $d->amount, 2),
                ]),
            'summary' => [
                'gross_pay' => $payrollEntry->gross_pay,
                'total_deductions' => $payrollEntry->total_deductions,
                'net_pay' => $payrollEntry->net_pay,
                'formatted_gross' => number_format((float) $payrollEntry->gross_pay, 2),
                'formatted_deductions' => number_format((float) $payrollEntry->total_deductions, 2),
                'formatted_net' => number_format((float) $payrollEntry->net_pay, 2),
            ],
        ]);
    }

    /**
     * Get summary statistics for a period's entries.
     */
    public function summary(string $tenant, PayrollPeriod $payrollPeriod): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $entries = PayrollEntry::query()
            ->where('payroll_period_id', $payrollPeriod->id)
            ->get();

        $byStatus = $entries->groupBy(fn ($e) => $e->status->value)
            ->map(fn ($group) => [
                'count' => $group->count(),
                'total_gross' => $group->sum('gross_pay'),
                'total_net' => $group->sum('net_pay'),
            ]);

        $departments = $entries->groupBy('department_name')
            ->map(fn ($group, $dept) => [
                'department' => $dept ?: 'Unassigned',
                'count' => $group->count(),
                'total_gross' => $group->sum('gross_pay'),
                'total_net' => $group->sum('net_pay'),
            ])
            ->values();

        return response()->json([
            'period_id' => $payrollPeriod->id,
            'total_entries' => $entries->count(),
            'total_gross' => $entries->sum('gross_pay'),
            'total_deductions' => $entries->sum('total_deductions'),
            'total_net' => $entries->sum('net_pay'),
            'by_status' => $byStatus,
            'by_department' => $departments,
        ]);
    }

    /**
     * Download a single payslip as PDF.
     */
    public function downloadPdf(
        string $tenant,
        PayrollEntry $payrollEntry,
        PayslipPdfService $pdfService
    ): Response {
        Gate::authorize('can-manage-organization');

        $pdfContent = $pdfService->generateSingle($payrollEntry);
        $fileName = sprintf(
            'payslip_%s_%s.pdf',
            $payrollEntry->employee_number,
            now()->format('Y-m-d')
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }

    /**
     * Bulk download payslips as PDF or ZIP.
     *
     * For 10 or fewer entries, returns a combined PDF immediately.
     * For more than 10 entries, queues a background job.
     */
    public function downloadBulkPdf(
        DownloadBulkPayslipRequest $request,
        string $tenant,
        PayrollPeriod $payrollPeriod,
        PayslipPdfService $pdfService
    ): Response|JsonResponse {
        Gate::authorize('can-manage-organization');

        $validated = $request->validated();
        $entryIds = $validated['entry_ids'] ?? null;
        $format = $validated['format'] ?? 'pdf';

        $query = PayrollEntry::query()
            ->where('payroll_period_id', $payrollPeriod->id);

        if ($entryIds !== null && count($entryIds) > 0) {
            $query->whereIn('id', $entryIds);
        }

        $entries = $query->orderBy('employee_name')->get();

        if ($entries->isEmpty()) {
            return response()->json([
                'message' => 'No payroll entries found.',
            ], 404);
        }

        $threshold = 10;

        if ($entries->count() <= $threshold) {
            if ($format === 'zip') {
                $zipPath = $pdfService->generateZip($entries);
                $fileName = sprintf('payslips_%s_%s.zip', $payrollPeriod->name, now()->format('Y-m-d'));

                return response()->download($zipPath, $fileName)->deleteFileAfterSend(true);
            }

            $pdfContent = $pdfService->generateBulk($entries);
            $fileName = sprintf('payslips_%s_%s.pdf', $payrollPeriod->name, now()->format('Y-m-d'));

            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Content-Length' => strlen($pdfContent),
            ]);
        }

        GenerateBulkPayslipPdf::dispatch(
            $entries->pluck('id')->toArray(),
            tenant()->id,
            auth()->id(),
            $format
        );

        return response()->json([
            'message' => 'Bulk payslip generation has been queued. You will be notified when it is ready.',
            'entry_count' => $entries->count(),
            'queued' => true,
        ]);
    }
}
