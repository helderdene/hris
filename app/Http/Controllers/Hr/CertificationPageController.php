<?php

namespace App\Http\Controllers\Hr;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CertificationResource;
use App\Http\Resources\CertificationTypeResource;
use App\Models\Certification;
use App\Models\CertificationType;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for rendering the HR Certifications management page.
 */
class CertificationPageController extends Controller
{
    /**
     * Display the HR certifications overview page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = Certification::query()
            ->with(['employee', 'certificationType', 'files'])
            ->orderByDesc('created_at');

        // Apply filters
        if ($request->filled('status')) {
            $status = CertificationStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('certification_type_id')) {
            $query->where('certification_type_id', $request->input('certification_type_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('expiry_from')) {
            $query->whereDate('expiry_date', '>=', $request->input('expiry_from'));
        }

        if ($request->filled('expiry_to')) {
            $query->whereDate('expiry_date', '<=', $request->input('expiry_to'));
        }

        // Get statistics
        $statistics = $this->getStatistics();

        // Get filter options
        $certificationTypes = CertificationTypeResource::collection(
            CertificationType::active()->orderBy('name')->get()
        );

        $employees = Employee::query()
            ->whereHas('certifications')
            ->select('id', 'first_name', 'last_name', 'employee_number')
            ->orderBy('last_name')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->full_name,
                'employee_number' => $e->employee_number,
            ]);

        $statuses = CertificationStatus::options();

        return Inertia::render('Hr/Certifications/Index', [
            'certifications' => CertificationResource::collection($query->paginate(15)),
            'certificationTypes' => $certificationTypes,
            'employees' => $employees,
            'statuses' => $statuses,
            'statistics' => $statistics,
            'filters' => [
                'status' => $request->input('status'),
                'certification_type_id' => $request->input('certification_type_id'),
                'employee_id' => $request->input('employee_id'),
                'search' => $request->input('search'),
                'expiry_from' => $request->input('expiry_from'),
                'expiry_to' => $request->input('expiry_to'),
            ],
        ]);
    }

    /**
     * Get certification statistics.
     *
     * @return array<string, int>
     */
    protected function getStatistics(): array
    {
        return [
            'total_active' => Certification::active()->count(),
            'pending_approval' => Certification::pendingApproval()->count(),
            'expiring_soon' => Certification::expiringWithin(30)->count(),
            'expired' => Certification::where('status', CertificationStatus::Expired)->count(),
        ];
    }
}
