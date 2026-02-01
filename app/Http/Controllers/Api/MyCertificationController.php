<?php

namespace App\Http\Controllers\Api;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCertificationRequest;
use App\Http\Requests\UpdateCertificationRequest;
use App\Http\Resources\CertificationResource;
use App\Models\Certification;
use App\Models\Employee;
use App\Notifications\CertificationSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class MyCertificationController extends Controller
{
    /**
     * Display a listing of the authenticated employee's certifications.
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $employee = $this->getEmployee();

        if (! $employee) {
            return response()->json([
                'message' => 'No employee profile found for the authenticated user.',
            ], 404);
        }

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

        return CertificationResource::collection($query->get());
    }

    /**
     * Store a newly created certification.
     */
    public function store(StoreCertificationRequest $request): JsonResponse
    {
        $employee = $this->getEmployee();

        if (! $employee) {
            return response()->json([
                'message' => 'No employee profile found for the authenticated user.',
            ], 404);
        }

        $certification = Certification::create([
            ...$request->validated(),
            'employee_id' => $employee->id,
            'status' => CertificationStatus::Draft,
            'created_by' => Auth::id(),
        ]);

        $certification->load(['certificationType', 'files']);

        return (new CertificationResource($certification))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified certification.
     */
    public function show(string $tenant, Certification $certification): CertificationResource|JsonResponse
    {
        $employee = $this->getEmployee();

        if (! $employee || $certification->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Certification not found.',
            ], 404);
        }

        $certification->load(['certificationType', 'files']);

        return new CertificationResource($certification);
    }

    /**
     * Update the specified certification.
     */
    public function update(
        UpdateCertificationRequest $request,
        string $tenant,
        Certification $certification
    ): CertificationResource|JsonResponse {
        $employee = $this->getEmployee();

        if (! $employee || $certification->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Certification not found.',
            ], 404);
        }

        if (! $certification->can_be_edited) {
            return response()->json([
                'message' => 'Only draft certifications can be edited.',
            ], 422);
        }

        $certification->update($request->validated());
        $certification->load(['certificationType', 'files']);

        return new CertificationResource($certification);
    }

    /**
     * Remove the specified certification.
     */
    public function destroy(string $tenant, Certification $certification): JsonResponse
    {
        $employee = $this->getEmployee();

        if (! $employee || $certification->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Certification not found.',
            ], 404);
        }

        if (! $certification->can_be_edited) {
            return response()->json([
                'message' => 'Only draft certifications can be deleted.',
            ], 422);
        }

        // Delete associated files
        foreach ($certification->files as $file) {
            $file->delete();
        }

        $certification->delete();

        return response()->json([
            'message' => 'Certification deleted successfully.',
        ]);
    }

    /**
     * Submit the certification for approval.
     */
    public function submit(string $tenant, Certification $certification): JsonResponse
    {
        $employee = $this->getEmployee();

        if (! $employee || $certification->employee_id !== $employee->id) {
            return response()->json([
                'message' => 'Certification not found.',
            ], 404);
        }

        if (! $certification->files()->exists()) {
            return response()->json([
                'message' => 'Please upload at least one certificate file before submitting.',
            ], 422);
        }

        if (! $certification->can_be_submitted) {
            return response()->json([
                'message' => 'This certification cannot be submitted.',
            ], 422);
        }

        $certification->submit();

        // Send notification to HR managers
        $this->notifyHrManagers($certification);

        $certification->load(['certificationType', 'files']);

        return response()->json([
            'message' => 'Certification submitted for approval.',
            'data' => new CertificationResource($certification),
        ]);
    }

    /**
     * Get certification statistics for the employee.
     */
    public function statistics(): JsonResponse
    {
        $employee = $this->getEmployee();

        if (! $employee) {
            return response()->json([
                'message' => 'No employee profile found for the authenticated user.',
            ], 404);
        }

        $baseQuery = Certification::forEmployee($employee);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->active()->count(),
            'draft' => (clone $baseQuery)->where('status', CertificationStatus::Draft)->count(),
            'pending_approval' => (clone $baseQuery)->pendingApproval()->count(),
            'expiring_soon' => (clone $baseQuery)->expiringWithin(30)->count(),
            'expired' => (clone $baseQuery)->where('status', CertificationStatus::Expired)->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Get the employee associated with the authenticated user.
     */
    protected function getEmployee(): ?Employee
    {
        return Employee::where('user_id', Auth::id())->first();
    }

    /**
     * Notify HR managers about a submitted certification.
     */
    protected function notifyHrManagers(Certification $certification): void
    {
        // Get users with HR role
        $hrUsers = \App\Models\User::whereHas('tenants', function ($query) {
            $query->where('tenant_id', tenant()?->id)
                ->where('role', 'admin');
        })->get();

        foreach ($hrUsers as $hrUser) {
            $hrUser->notify(new CertificationSubmitted($certification));
        }
    }
}
