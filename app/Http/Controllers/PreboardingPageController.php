<?php

namespace App\Http\Controllers;

use App\Enums\PreboardingItemStatus;
use App\Enums\PreboardingStatus;
use App\Models\Employee;
use App\Models\PreboardingChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PreboardingPageController extends Controller
{
    /**
     * Display the list of all preboarding checklists for HR.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = PreboardingChecklist::query()
            ->with([
                'jobApplication.candidate',
                'offer',
                'items',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('jobApplication.candidate', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $checklists = $query->paginate(25);

        return Inertia::render('Preboarding/Index', [
            'checklists' => [
                'data' => $checklists->map(fn ($checklist) => [
                    'id' => $checklist->id,
                    'status' => $checklist->status->value,
                    'status_label' => $checklist->status->label(),
                    'status_color' => $checklist->status->color(),
                    'deadline' => $checklist->deadline?->format('M d, Y'),
                    'completed_at' => $checklist->completed_at?->format('M d, Y'),
                    'progress_percentage' => $checklist->progress_percentage,
                    'candidate_name' => $checklist->jobApplication?->candidate?->full_name,
                    'candidate_email' => $checklist->jobApplication?->candidate?->email,
                    'position_title' => $checklist->offer?->position_title,
                    'total_items' => $checklist->items->count(),
                    'approved_items' => $checklist->items->where('status', PreboardingItemStatus::Approved)->count(),
                    'created_at' => $checklist->created_at?->format('M d, Y'),
                ])->toArray(),
                'links' => $checklists->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $checklists->currentPage(),
                    'last_page' => $checklists->lastPage(),
                    'total' => $checklists->total(),
                ],
            ],
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
            ],
            'statuses' => PreboardingStatus::options(),
        ]);
    }

    /**
     * Display a specific preboarding checklist for HR review.
     */
    public function show(string $tenant, PreboardingChecklist $checklist): Response
    {
        Gate::authorize('can-manage-organization');

        $checklist->load([
            'jobApplication.candidate',
            'offer',
            'items.document',
            'items.documentCategory',
        ]);

        // Check if an employee record exists for this candidate
        $candidateEmail = $checklist->jobApplication?->candidate?->email;
        $employee = $candidateEmail ? Employee::where('email', $candidateEmail)->first() : null;

        return Inertia::render('Preboarding/Show', [
            'checklist' => [
                'id' => $checklist->id,
                'status' => $checklist->status->value,
                'status_label' => $checklist->status->label(),
                'status_color' => $checklist->status->color(),
                'deadline' => $checklist->deadline?->format('M d, Y'),
                'completed_at' => $checklist->completed_at?->format('M d, Y H:i'),
                'progress_percentage' => $checklist->progress_percentage,
                'candidate_name' => $checklist->jobApplication?->candidate?->full_name,
                'candidate_email' => $checklist->jobApplication?->candidate?->email,
                'position_title' => $checklist->offer?->position_title,
                'start_date' => $checklist->offer?->start_date?->format('M d, Y'),
                'created_at' => $checklist->created_at?->format('M d, Y'),
                'employee_id' => $employee?->id,
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
                    'document' => $item->document ? [
                        'id' => $item->document->id,
                        'name' => $item->document->name,
                        'original_filename' => $item->document->original_filename,
                        'mime_type' => $item->document->mime_type,
                        'url' => $item->document->getUrl(),
                    ] : null,
                    'document_category' => $item->documentCategory?->name,
                ])->toArray(),
            ],
            'itemStatuses' => PreboardingItemStatus::options(),
        ]);
    }
}
