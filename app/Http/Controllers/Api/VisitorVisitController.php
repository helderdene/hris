<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveVisitorVisitRequest;
use App\Http\Requests\CheckInVisitorRequest;
use App\Http\Requests\RejectVisitorVisitRequest;
use App\Http\Requests\StoreVisitorVisitRequest;
use App\Http\Requests\UpdateVisitorVisitRequest;
use App\Http\Resources\VisitorVisitResource;
use App\Models\VisitorVisit;
use App\Services\Visitor\VisitorCheckInService;
use App\Services\Visitor\VisitorRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class VisitorVisitController extends Controller
{
    /**
     * Display a listing of visitor visits.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = VisitorVisit::query()
            ->with(['visitor', 'workLocation', 'hostEmployee'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('work_location_id')) {
            $query->where('work_location_id', $request->input('work_location_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('expected_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expected_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $query->whereHas('visitor', function ($q) use ($request) {
                $q->search($request->input('search'));
            });
        }

        $visits = $query->paginate($request->integer('per_page', 25));

        return VisitorVisitResource::collection($visits);
    }

    /**
     * Store a newly created visitor visit (admin pre-registration).
     */
    public function store(StoreVisitorVisitRequest $request, VisitorRegistrationService $registrationService): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $visitor = \App\Models\Visitor::findOrFail($request->validated('visitor_id'));

        $visit = $registrationService->preRegister($visitor, $request->validated(), $request->user());

        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return (new VisitorVisitResource($visit))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified visitor visit.
     */
    public function show(VisitorVisit $visit): VisitorVisitResource
    {
        Gate::authorize('can-manage-organization');

        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return new VisitorVisitResource($visit);
    }

    /**
     * Update the specified visitor visit.
     */
    public function update(UpdateVisitorVisitRequest $request, VisitorVisit $visit): VisitorVisitResource
    {
        Gate::authorize('can-manage-organization');

        $visit->update($request->validated());
        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return new VisitorVisitResource($visit);
    }

    /**
     * Remove the specified visitor visit.
     */
    public function destroy(VisitorVisit $visit): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $visit->delete();

        return response()->json([
            'message' => 'Visitor visit deleted successfully.',
        ]);
    }

    /**
     * Approve a pending visitor visit.
     */
    public function approve(ApproveVisitorVisitRequest $request, VisitorVisit $visit, VisitorRegistrationService $registrationService): VisitorVisitResource
    {
        Gate::authorize('can-manage-organization');

        $visit = $registrationService->adminApprove($visit, $request->user());
        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return new VisitorVisitResource($visit);
    }

    /**
     * Reject a pending visitor visit.
     */
    public function reject(RejectVisitorVisitRequest $request, VisitorVisit $visit, VisitorRegistrationService $registrationService): VisitorVisitResource
    {
        Gate::authorize('can-manage-organization');

        $visit = $registrationService->reject($visit, $request->user(), $request->validated('reason'));
        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return new VisitorVisitResource($visit);
    }

    /**
     * Manually check in a visitor.
     */
    public function checkIn(CheckInVisitorRequest $request, VisitorVisit $visit, VisitorCheckInService $checkInService): VisitorVisitResource
    {
        Gate::authorize('can-manage-organization');

        $visit = $checkInService->checkInManual($visit, $request->user(), $request->validated('badge_number'));
        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return new VisitorVisitResource($visit);
    }

    /**
     * Check out a visitor.
     */
    public function checkOut(VisitorVisit $visit, VisitorCheckInService $checkInService): VisitorVisitResource
    {
        Gate::authorize('can-manage-organization');

        $visit = $checkInService->checkOut($visit, request()->user());
        $visit->load(['visitor', 'workLocation', 'hostEmployee']);

        return new VisitorVisitResource($visit);
    }

    /**
     * Resend QR code to visitor.
     */
    public function resendQrCode(VisitorVisit $visit, VisitorRegistrationService $registrationService): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $registrationService->resendConfirmationEmail($visit);

        return response()->json([
            'message' => 'QR code resent successfully.',
        ]);
    }

    /**
     * Export visitor visits as CSV.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        Gate::authorize('can-manage-organization');

        $query = VisitorVisit::query()
            ->with(['visitor', 'workLocation', 'hostEmployee'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('expected_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expected_at', '<=', $request->input('date_to'));
        }

        $visits = $query->get();

        return response()->streamDownload(function () use ($visits) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Visitor Name', 'Email', 'Company', 'Purpose', 'Host',
                'Location', 'Status', 'Expected At', 'Checked In', 'Checked Out',
                'Check-in Method',
            ]);

            foreach ($visits as $visit) {
                fputcsv($handle, [
                    $visit->visitor?->full_name,
                    $visit->visitor?->email,
                    $visit->visitor?->company,
                    $visit->purpose,
                    $visit->hostEmployee?->full_name,
                    $visit->workLocation?->name,
                    $visit->status?->label(),
                    $visit->expected_at?->format('Y-m-d H:i'),
                    $visit->checked_in_at?->format('Y-m-d H:i'),
                    $visit->checked_out_at?->format('Y-m-d H:i'),
                    $visit->check_in_method?->label(),
                ]);
            }

            fclose($handle);
        }, 'visitor-visits-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
