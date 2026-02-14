<?php

namespace App\Http\Controllers\Api;

use App\Enums\CertificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CertificationResource;
use App\Models\Certification;
use App\Notifications\CertificationApproved;
use App\Notifications\CertificationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CertificationController extends Controller
{
    /**
     * Display a listing of all certifications (HR view).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = Certification::query()
            ->with(['employee', 'certificationType', 'files'])
            ->orderByDesc('created_at');

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

        if ($request->filled('expiry_from')) {
            $query->whereDate('expiry_date', '>=', $request->input('expiry_from'));
        }

        if ($request->filled('expiry_to')) {
            $query->whereDate('expiry_date', '<=', $request->input('expiry_to'));
        }

        if ($request->boolean('expiring_soon')) {
            $query->expiringWithin(30);
        }

        $perPage = $request->input('per_page', 15);
        $certifications = $query->paginate($perPage);

        return CertificationResource::collection($certifications);
    }

    /**
     * Display the specified certification.
     */
    public function show(Certification $certification): CertificationResource
    {
        Gate::authorize('can-manage-organization');

        $certification->load(['employee', 'certificationType', 'files']);

        return new CertificationResource($certification);
    }

    /**
     * Approve a certification.
     */
    public function approve(Certification $certification): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        if ($certification->status !== CertificationStatus::PendingApproval) {
            return response()->json([
                'message' => 'Only certifications pending approval can be approved.',
            ], 422);
        }

        $certification->approve(Auth::id());

        // Send notification to the employee
        if ($certification->employee?->user) {
            $certification->employee->user->notify(new CertificationApproved($certification));
        }

        $certification->load(['employee', 'certificationType', 'files']);

        return response()->json([
            'message' => 'Certification approved successfully.',
            'data' => new CertificationResource($certification),
        ]);
    }

    /**
     * Reject a certification.
     */
    public function reject(Request $request, Certification $certification): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($certification->status !== CertificationStatus::PendingApproval) {
            return response()->json([
                'message' => 'Only certifications pending approval can be rejected.',
            ], 422);
        }

        $certification->reject(Auth::id(), $request->input('reason'));

        // Send notification to the employee
        if ($certification->employee?->user) {
            $certification->employee->user->notify(new CertificationRejected($certification));
        }

        $certification->load(['employee', 'certificationType', 'files']);

        return response()->json([
            'message' => 'Certification rejected successfully.',
            'data' => new CertificationResource($certification),
        ]);
    }

    /**
     * Revoke an active certification.
     */
    public function revoke(Request $request, Certification $certification): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($certification->status !== CertificationStatus::Active) {
            return response()->json([
                'message' => 'Only active certifications can be revoked.',
            ], 422);
        }

        $certification->revoke(Auth::id(), $request->input('reason'));

        $certification->load(['employee', 'certificationType', 'files']);

        return response()->json([
            'message' => 'Certification revoked successfully.',
            'data' => new CertificationResource($certification),
        ]);
    }

    /**
     * Get certification statistics.
     */
    public function statistics(): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $stats = [
            'total_active' => Certification::active()->count(),
            'pending_approval' => Certification::pendingApproval()->count(),
            'expiring_within_30_days' => Certification::expiringWithin(30)->count(),
            'expiring_within_60_days' => Certification::expiringWithin(60)->count(),
            'expiring_within_90_days' => Certification::expiringWithin(90)->count(),
            'expired' => Certification::where('status', CertificationStatus::Expired)->count(),
        ];

        return response()->json($stats);
    }
}
