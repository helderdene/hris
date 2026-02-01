<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Reports\BirReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API controller for employee self-service BIR 2316 certificate access.
 *
 * Allows authenticated employees to view and download their own
 * BIR 2316 certificates.
 */
class EmployeeBir2316Controller extends Controller
{
    public function __construct(protected BirReportService $reportService) {}

    /**
     * List available BIR 2316 certificates for the current user's employee.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee($request);

        if (! $employee) {
            return response()->json([
                'message' => 'No employee profile linked to your account.',
            ], 404);
        }

        $certificates = $this->reportService->getEmployee2316Certificates($employee->id);

        return response()->json([
            'certificates' => $certificates->map(fn ($cert) => [
                'id' => $cert->id,
                'tax_year' => $cert->tax_year,
                'generated_at' => $cert->generated_at?->format('F j, Y g:i A'),
                'has_data' => ! empty($cert->compensation_data),
            ]),
        ]);
    }

    /**
     * Download the BIR 2316 certificate for a specific year.
     */
    public function download(Request $request, int $year): StreamedResponse|JsonResponse
    {
        $employee = $this->getAuthenticatedEmployee($request);

        if (! $employee) {
            return response()->json([
                'message' => 'No employee profile linked to your account.',
            ], 404);
        }

        // Validate year
        if ($year < 2020 || $year > now()->year) {
            return response()->json([
                'message' => 'Invalid tax year.',
            ], 422);
        }

        try {
            $result = $this->reportService->generate2316ForEmployee(
                employeeId: $employee->id,
                year: $year
            );

            return response()->streamDownload(
                function () use ($result) {
                    echo $result['content'];
                },
                $result['filename'],
                [
                    'Content-Type' => $result['contentType'],
                    'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
                    'X-Filename' => $result['filename'],
                    'Access-Control-Expose-Headers' => 'Content-Disposition, X-Filename',
                ]
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get the employee associated with the authenticated user.
     */
    protected function getAuthenticatedEmployee(Request $request): ?Employee
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return Employee::query()
            ->where('user_id', $user->id)
            ->first();
    }
}
