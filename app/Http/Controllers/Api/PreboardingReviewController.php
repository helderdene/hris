<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConvertToEmployeeAction;
use App\Enums\PreboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectPreboardingItemRequest;
use App\Models\PreboardingChecklist;
use App\Models\PreboardingChecklistItem;
use App\Services\PreboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PreboardingReviewController extends Controller
{
    public function __construct(
        protected PreboardingService $preboardingService
    ) {}

    /**
     * Approve a preboarding checklist item.
     */
    public function approve(PreboardingChecklistItem $item): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $item = $this->preboardingService->approveItem($item);

        return response()->json([
            'message' => 'Item approved successfully.',
            'item' => [
                'id' => $item->id,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'reviewed_at' => $item->reviewed_at?->format('M d, Y H:i'),
            ],
        ]);
    }

    /**
     * Reject a preboarding checklist item.
     */
    public function reject(RejectPreboardingItemRequest $request, PreboardingChecklistItem $item): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $item = $this->preboardingService->rejectItem($item, $request->validated('rejection_reason'));

        return response()->json([
            'message' => 'Item rejected.',
            'item' => [
                'id' => $item->id,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'rejection_reason' => $item->rejection_reason,
                'reviewed_at' => $item->reviewed_at?->format('M d, Y H:i'),
            ],
        ]);
    }

    /**
     * Convert a completed preboarding checklist to an employee record.
     */
    public function convertToEmployee(PreboardingChecklist $checklist): \Illuminate\Http\RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        // Validate checklist is completed
        if ($checklist->status !== PreboardingStatus::Completed) {
            abort(422, 'Only completed preboarding checklists can be converted to employees.');
        }

        $action = new ConvertToEmployeeAction;
        $employee = $action->execute($checklist);

        return redirect()->route('employees.show', ['employee' => $employee->id])
            ->with('success', 'Employee created successfully.');
    }
}
