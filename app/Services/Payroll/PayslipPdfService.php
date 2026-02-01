<?php

namespace App\Services\Payroll;

use App\Models\PayrollEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use ZipArchive;

/**
 * Service for generating payslip PDFs.
 *
 * Handles single payslip generation, bulk PDF exports, and ZIP file creation
 * for multiple payslips using DomPDF.
 */
class PayslipPdfService
{
    /**
     * Generate a PDF for a single payroll entry.
     *
     * @return string PDF content as string
     */
    public function generateSingle(PayrollEntry $entry): string
    {
        $entry->load([
            'payrollPeriod.payrollCycle',
            'earnings',
            'deductions',
        ]);

        $data = $this->buildPayslipData($entry);

        $pdf = Pdf::loadView('pdf.payslip', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate a combined PDF for multiple payroll entries.
     *
     * @param  Collection<int, PayrollEntry>  $entries
     * @return string PDF content as string
     */
    public function generateBulk(Collection $entries): string
    {
        $payslips = $entries->map(function ($entry) {
            $entry->load([
                'payrollPeriod.payrollCycle',
                'earnings',
                'deductions',
            ]);

            return $this->buildPayslipData($entry);
        });

        $pdf = Pdf::loadView('pdf.payslip-bulk', ['payslips' => $payslips]);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->output();
    }

    /**
     * Generate a ZIP file containing individual PDF payslips.
     *
     * @param  Collection<int, PayrollEntry>  $entries
     * @return string Path to the generated ZIP file
     */
    public function generateZip(Collection $entries): string
    {
        $tempDir = storage_path('app/temp/payslips');

        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFileName = 'payslips_'.now()->format('Y-m-d_His').'.zip';
        $zipPath = $tempDir.'/'.$zipFileName;

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($entries as $entry) {
            $pdfContent = $this->generateSingle($entry);
            $fileName = $this->generateFileName($entry);
            $zip->addFromString($fileName, $pdfContent);
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Build payslip data array for PDF template.
     *
     * @return array<string, mixed>
     */
    protected function buildPayslipData(PayrollEntry $entry): array
    {
        $tenant = tenant();

        $businessInfo = $tenant?->business_info ?? [];

        return [
            'company' => [
                'name' => $tenant?->name ?? 'Company Name',
                'address' => $businessInfo['address'] ?? null,
                'tin' => $businessInfo['tin'] ?? null,
                'sss_number' => $businessInfo['sss_number'] ?? null,
                'philhealth_number' => $businessInfo['philhealth_number'] ?? null,
                'pagibig_number' => $businessInfo['pagibig_number'] ?? null,
                'logo_path' => $tenant?->logo_path ?? null,
            ],
            'period' => [
                'name' => $entry->payrollPeriod->name,
                'cutoff_start' => $entry->payrollPeriod->cutoff_start->format('F j, Y'),
                'cutoff_end' => $entry->payrollPeriod->cutoff_end->format('F j, Y'),
                'pay_date' => $entry->payrollPeriod->pay_date->format('F j, Y'),
                'cycle_name' => $entry->payrollPeriod->payrollCycle?->name ?? 'Regular',
            ],
            'employee' => [
                'number' => $entry->employee_number,
                'name' => $entry->employee_name,
                'department' => $entry->department_name,
                'position' => $entry->position_name,
            ],
            'earnings' => $entry->earnings->map(fn ($e) => [
                'description' => $e->description,
                'quantity' => $e->quantity,
                'quantity_unit' => $e->quantity_unit,
                'rate' => $e->rate,
                'multiplier' => $e->multiplier,
                'amount' => $e->amount,
                'formatted_amount' => number_format((float) $e->amount, 2),
            ]),
            'deductions' => $entry->deductions
                ->where('is_employee_share', true)
                ->values()
                ->map(fn ($d) => [
                    'description' => $d->description,
                    'basis_amount' => $d->basis_amount,
                    'rate' => $d->rate,
                    'amount' => $d->amount,
                    'formatted_amount' => number_format((float) $d->amount, 2),
                ]),
            'summary' => [
                'gross_pay' => $entry->gross_pay,
                'total_deductions' => $entry->total_deductions,
                'net_pay' => $entry->net_pay,
                'formatted_gross' => number_format((float) $entry->gross_pay, 2),
                'formatted_deductions' => number_format((float) $entry->total_deductions, 2),
                'formatted_net' => number_format((float) $entry->net_pay, 2),
            ],
            'generated_at' => now()->format('F j, Y g:i A'),
        ];
    }

    /**
     * Generate a descriptive filename for a payslip PDF.
     */
    protected function generateFileName(PayrollEntry $entry): string
    {
        $employeeName = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s]/', '', $entry->employee_name));
        $periodName = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\s]/', '', $entry->payrollPeriod->name));

        return "payslip_{$entry->employee_number}_{$employeeName}_{$periodName}.pdf";
    }
}
