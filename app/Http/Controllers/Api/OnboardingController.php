<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignOnboardingItemRequest;
use App\Http\Requests\CompleteOnboardingItemRequest;
use App\Http\Requests\SkipOnboardingItemRequest;
use App\Models\OnboardingChecklistItem;
use App\Models\User;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    /**
     * Mark an onboarding item as complete.
     */
    public function completeItem(CompleteOnboardingItemRequest $request, string $tenant, OnboardingChecklistItem $item): JsonResponse|RedirectResponse
    {
        $data = $request->validated();

        $item = $this->onboardingService->completeItem(
            $item,
            $request->user(),
            $data
        );

        if ($request->inertia()) {
            return back();
        }

        return response()->json([
            'message' => 'Item marked as complete.',
            'item' => [
                'id' => $item->id,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'status_color' => $item->status->color(),
                'completed_at' => $item->completed_at?->format('M d, Y H:i'),
                'completed_by' => $item->completedByUser?->name,
                'notes' => $item->notes,
                'equipment_details' => $item->equipment_details,
            ],
            'checklist_progress' => $item->checklist->progress_percentage,
        ]);
    }

    /**
     * Skip an optional onboarding item.
     */
    public function skipItem(SkipOnboardingItemRequest $request, string $tenant, OnboardingChecklistItem $item): JsonResponse|RedirectResponse
    {
        // Only non-required items can be skipped
        if ($item->is_required) {
            if ($request->inertia()) {
                return back()->withErrors(['item' => 'Required items cannot be skipped.']);
            }

            return response()->json([
                'message' => 'Required items cannot be skipped.',
            ], 422);
        }

        $item = $this->onboardingService->skipItem(
            $item,
            $request->user(),
            $request->validated('reason')
        );

        if ($request->inertia()) {
            return back();
        }

        return response()->json([
            'message' => 'Item skipped.',
            'item' => [
                'id' => $item->id,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'status_color' => $item->status->color(),
                'notes' => $item->notes,
            ],
            'checklist_progress' => $item->checklist->progress_percentage,
        ]);
    }

    /**
     * Assign an onboarding item to a specific user.
     */
    public function assignItem(AssignOnboardingItemRequest $request, string $tenant, OnboardingChecklistItem $item): JsonResponse|RedirectResponse
    {
        $assignee = User::findOrFail($request->validated('assigned_to'));

        $item = $this->onboardingService->assignItem($item, $assignee);

        if ($request->inertia()) {
            return back();
        }

        return response()->json([
            'message' => 'Item assigned successfully.',
            'item' => [
                'id' => $item->id,
                'assigned_to' => [
                    'id' => $item->assignee->id,
                    'name' => $item->assignee->name,
                ],
            ],
        ]);
    }

    /**
     * Mark an onboarding item as in progress.
     */
    public function startItem(Request $request, string $tenant, OnboardingChecklistItem $item): JsonResponse|RedirectResponse
    {
        $item = $this->onboardingService->startItem($item);

        if ($request->inertia()) {
            return back();
        }

        return response()->json([
            'message' => 'Item started.',
            'item' => [
                'id' => $item->id,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'status_color' => $item->status->color(),
            ],
        ]);
    }
}
