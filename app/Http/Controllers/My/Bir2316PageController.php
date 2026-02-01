<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Reports\BirReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page controller for employee self-service BIR 2316 certificates.
 */
class Bir2316PageController extends Controller
{
    public function __construct(protected BirReportService $reportService) {}

    /**
     * Display the BIR 2316 certificates page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        $certificates = [];
        if ($employee) {
            $certificates = $this->reportService->getEmployee2316Certificates($employee->id)
                ->map(fn ($cert) => [
                    'id' => $cert->id,
                    'tax_year' => $cert->tax_year,
                    'generated_at' => $cert->generated_at?->format('F j, Y g:i A'),
                    'has_data' => ! empty($cert->compensation_data),
                ])
                ->values()
                ->toArray();
        }

        return Inertia::render('My/Bir2316/Index', [
            'hasEmployeeProfile' => $employee !== null,
            'certificates' => $certificates,
            'availableYears' => range(now()->year, max(2020, now()->year - 5)),
        ]);
    }
}
