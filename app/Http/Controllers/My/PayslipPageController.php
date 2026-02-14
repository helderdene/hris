<?php

namespace App\Http\Controllers\My;

use App\Enums\PayrollEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Services\Payroll\PayslipPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Page controller for employee self-service payslip viewing and PDF download.
 */
class PayslipPageController extends Controller
{
    public function __construct(protected PayslipPdfService $pdfService) {}

    /**
     * Display the employee's payslip history.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        $payslips = null;
        if ($employee) {
            $payslips = PayrollEntry::query()
                ->forEmployee($employee->id)
                ->whereIn('status', [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid])
                ->with('payrollPeriod')
                ->latest('computed_at')
                ->paginate(15)
                ->through(fn (PayrollEntry $entry) => [
                    'id' => $entry->id,
                    'period_name' => $entry->payrollPeriod?->name,
                    'period_start' => $entry->payrollPeriod?->start_date?->format('M d, Y'),
                    'period_end' => $entry->payrollPeriod?->end_date?->format('M d, Y'),
                    'gross_pay' => (float) $entry->gross_pay,
                    'total_deductions' => (float) $entry->total_deductions,
                    'net_pay' => (float) $entry->net_pay,
                    'status' => $entry->status->value,
                    'status_label' => $entry->status->label(),
                    'status_color' => $entry->status->colorClass(),
                ]);
        }

        return Inertia::render('My/Payslips/Index', [
            'hasEmployeeProfile' => $employee !== null,
            'payslips' => $payslips,
        ]);
    }

    /**
     * Display an individual payslip detail.
     */
    public function show(Request $request, PayrollEntry $entry): InertiaResponse|SymfonyResponse
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        if (! $employee || $entry->employee_id !== $employee->id) {
            abort(403, 'You are not authorized to view this payslip.');
        }

        if (! in_array($entry->status, [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid])) {
            abort(403, 'This payslip is not available for viewing.');
        }

        $entry->load(['payrollPeriod', 'earnings', 'deductions']);

        return Inertia::render('My/Payslips/Show', [
            'entry' => [
                'id' => $entry->id,
                'employee_number' => $entry->employee_number,
                'employee_name' => $entry->employee_name,
                'department_name' => $entry->department_name,
                'position_name' => $entry->position_name,
                'period_name' => $entry->payrollPeriod?->name,
                'period_start' => $entry->payrollPeriod?->start_date?->format('M d, Y'),
                'period_end' => $entry->payrollPeriod?->end_date?->format('M d, Y'),
                'status' => $entry->status->value,
                'status_label' => $entry->status->label(),
                'status_color' => $entry->status->colorClass(),
                'basic_pay' => (float) $entry->basic_pay,
                'overtime_pay' => (float) $entry->overtime_pay,
                'night_diff_pay' => (float) $entry->night_diff_pay,
                'holiday_pay' => (float) $entry->holiday_pay,
                'allowances_total' => (float) $entry->allowances_total,
                'bonuses_total' => (float) $entry->bonuses_total,
                'gross_pay' => (float) $entry->gross_pay,
                'sss_employee' => (float) $entry->sss_employee,
                'philhealth_employee' => (float) $entry->philhealth_employee,
                'pagibig_employee' => (float) $entry->pagibig_employee,
                'withholding_tax' => (float) $entry->withholding_tax,
                'other_deductions_total' => (float) $entry->other_deductions_total,
                'total_deductions' => (float) $entry->total_deductions,
                'net_pay' => (float) $entry->net_pay,
                'earnings' => $entry->earnings->map(fn ($e) => [
                    'description' => $e->description,
                    'amount' => (float) $e->amount,
                ]),
                'deductions' => $entry->deductions
                    ->where('is_employee_share', true)
                    ->values()
                    ->map(fn ($d) => [
                        'description' => $d->description,
                        'amount' => (float) $d->amount,
                    ]),
            ],
        ]);
    }

    /**
     * Download a payslip as PDF.
     */
    public function downloadPdf(Request $request, PayrollEntry $entry): Response
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        if (! $employee || $entry->employee_id !== $employee->id) {
            abort(403, 'You are not authorized to download this payslip.');
        }

        if (! in_array($entry->status, [PayrollEntryStatus::Approved, PayrollEntryStatus::Paid])) {
            abort(403, 'This payslip is not available for download.');
        }

        $pdfContent = $this->pdfService->generateSingle($entry);

        $filename = sprintf(
            'payslip_%s_%s.pdf',
            $entry->employee_number,
            str_replace(' ', '_', $entry->payrollPeriod?->name ?? 'unknown'),
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
