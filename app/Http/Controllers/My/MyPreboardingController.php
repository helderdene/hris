<?php

namespace App\Http\Controllers\My;

use App\Enums\PreboardingItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PreboardingChecklist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyPreboardingController extends Controller
{
    /**
     * Display the new hire's preboarding checklist.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $checklist = null;

        if ($user) {
            // First try to find via employee record (for existing employees)
            $employee = Employee::where('user_id', $user->id)->first();
            $emailToMatch = $employee?->email ?? $user->email;

            // Find checklist via job application linked to candidate with matching email
            $checklist = PreboardingChecklist::query()
                ->whereHas('jobApplication', function ($q) use ($emailToMatch) {
                    $q->whereHas('candidate', function ($cq) use ($emailToMatch) {
                        $cq->where('email', $emailToMatch);
                    });
                })
                ->with(['items' => function ($q) {
                    $q->orderBy('sort_order');
                }, 'offer'])
                ->latest()
                ->first();
        }

        $checklistData = null;
        if ($checklist) {
            $checklistData = [
                'id' => $checklist->id,
                'status' => $checklist->status->value,
                'status_label' => $checklist->status->label(),
                'status_color' => $checklist->status->color(),
                'deadline' => $checklist->deadline?->format('M d, Y'),
                'completed_at' => $checklist->completed_at?->format('M d, Y H:i'),
                'progress_percentage' => $checklist->progress_percentage,
                'items' => $checklist->items->map(fn ($item) => [
                    'id' => $item->id,
                    'type' => $item->type->value,
                    'type_label' => $item->type->label(),
                    'name' => $item->name,
                    'description' => $item->description,
                    'is_required' => $item->is_required,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'status_color' => $item->status->color(),
                    'form_value' => $item->form_value,
                    'rejection_reason' => $item->rejection_reason,
                    'submitted_at' => $item->submitted_at?->format('M d, Y H:i'),
                    'reviewed_at' => $item->reviewed_at?->format('M d, Y H:i'),
                    'document_id' => $item->document_id,
                ])->toArray(),
            ];
        }

        return Inertia::render('My/Preboarding/Index', [
            'checklist' => $checklistData,
            'itemStatuses' => PreboardingItemStatus::options(),
        ]);
    }
}
