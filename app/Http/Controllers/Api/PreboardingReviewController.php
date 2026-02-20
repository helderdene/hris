<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConvertToEmployeeAction;
use App\Enums\PreboardingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectPreboardingItemRequest;
use App\Models\PreboardingChecklist;
use App\Models\PreboardingChecklistItem;
use App\Services\PreboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class PreboardingReviewController extends Controller
{
    public function __construct(
        protected PreboardingService $preboardingService
    ) {}

    /**
     * Approve a preboarding checklist item.
     */
    public function approve(PreboardingChecklistItem $item): RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $this->preboardingService->approveItem($item);

        return back();
    }

    /**
     * Reject a preboarding checklist item.
     */
    public function reject(RejectPreboardingItemRequest $request, PreboardingChecklistItem $item): RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $this->preboardingService->rejectItem($item, $request->validated('rejection_reason'));

        return back();
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
