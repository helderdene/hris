<?php

namespace App\Http\Controllers\My;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CertificationResource;
use App\Http\Resources\CertificationTypeResource;
use App\Models\Certification;
use App\Models\CertificationType;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for rendering the My Certifications page (employee self-service).
 */
class MyCertificationPageController extends Controller
{
    /**
     * Display the employee's certifications page.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $certifications = collect();
        $statistics = [
            'total' => 0,
            'active' => 0,
            'draft' => 0,
            'pending_approval' => 0,
            'expiring_soon' => 0,
        ];

        if ($employee) {
            $query = Certification::query()
                ->forEmployee($employee)
                ->with(['certificationType', 'files'])
                ->orderByDesc('created_at');

            if ($request->filled('status')) {
                $status = CertificationStatus::tryFrom($request->input('status'));
                if ($status) {
                    $query->where('status', $status);
                }
            }

            $certifications = $query->get();
            $statistics = $this->getStatistics($employee->id);
        }

        // Get certification types for the form
        $certificationTypes = CertificationTypeResource::collection(
            CertificationType::active()->orderBy('name')->get()
        );

        $statuses = CertificationStatus::options();

        return Inertia::render('My/Certifications/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'certifications' => CertificationResource::collection($certifications),
            'certificationTypes' => $certificationTypes,
            'statuses' => $statuses,
            'statistics' => $statistics,
            'filters' => [
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Get certification statistics for the employee.
     *
     * @return array<string, int>
     */
    protected function getStatistics(int $employeeId): array
    {
        $baseQuery = Certification::forEmployee($employeeId);

        return [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->active()->count(),
            'draft' => (clone $baseQuery)->where('status', CertificationStatus::Draft)->count(),
            'pending_approval' => (clone $baseQuery)->pendingApproval()->count(),
            'expiring_soon' => (clone $baseQuery)->expiringWithin(30)->count(),
        ];
    }
}
