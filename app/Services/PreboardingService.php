<?php

namespace App\Services;

use App\Enums\PreboardingItemStatus;
use App\Enums\PreboardingStatus;
use App\Models\Document;
use App\Models\Offer;
use App\Models\PreboardingChecklist;
use App\Models\PreboardingChecklistItem;
use App\Models\PreboardingTemplate;
use App\Notifications\PreboardingCompleted;
use App\Notifications\PreboardingCreated;
use App\Notifications\PreboardingItemRejected;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing the preboarding lifecycle.
 */
class PreboardingService
{
    public function __construct(
        protected DocumentStorageService $documentStorageService
    ) {}

    /**
     * Create a preboarding checklist from a template for an accepted offer.
     */
    public function createFromTemplate(Offer $offer, ?PreboardingTemplate $template = null): PreboardingChecklist
    {
        if (! $template) {
            $template = PreboardingTemplate::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
        }

        return DB::transaction(function () use ($offer, $template) {
            $checklist = PreboardingChecklist::create([
                'job_application_id' => $offer->job_application_id,
                'offer_id' => $offer->id,
                'status' => PreboardingStatus::Pending,
                'deadline' => $offer->start_date?->subDays(3),
                'created_by' => auth()->id(),
            ]);

            if ($template) {
                foreach ($template->items()->orderBy('sort_order')->get() as $templateItem) {
                    PreboardingChecklistItem::create([
                        'preboarding_checklist_id' => $checklist->id,
                        'preboarding_template_item_id' => $templateItem->id,
                        'type' => $templateItem->type,
                        'name' => $templateItem->name,
                        'description' => $templateItem->description,
                        'is_required' => $templateItem->is_required,
                        'sort_order' => $templateItem->sort_order,
                        'status' => PreboardingItemStatus::Pending,
                        'document_category_id' => $templateItem->document_category_id,
                    ]);
                }
            }

            // Notify the new hire
            $candidate = $offer->jobApplication->candidate;
            if ($candidate->email) {
                \Illuminate\Support\Facades\Notification::route('mail', $candidate->email)
                    ->notify(new PreboardingCreated($checklist));
            }

            return $checklist->fresh('items');
        });
    }

    /**
     * Submit an item (upload document, fill form value, or acknowledge).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function submitItem(PreboardingChecklistItem $item, array $data): PreboardingChecklistItem
    {
        $allowed = $item->status->allowedTransitions();
        if (! in_array(PreboardingItemStatus::Submitted, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This item cannot be submitted in its current state.',
            ]);
        }

        return DB::transaction(function () use ($item, $data) {
            $updateData = [
                'status' => PreboardingItemStatus::Submitted,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ];

            if ($item->type->value === 'document_upload' && isset($data['file'])) {
                /** @var UploadedFile $file */
                $file = $data['file'];
                $tenant = tenant();
                $storedFile = $this->documentStorageService->store(
                    $file,
                    $tenant->slug,
                );

                $document = Document::create([
                    'document_category_id' => $item->document_category_id,
                    'name' => $item->name,
                    'original_filename' => $storedFile['original_filename'],
                    'stored_filename' => $storedFile['stored_filename'],
                    'file_path' => $storedFile['file_path'],
                    'mime_type' => $storedFile['mime_type'],
                    'file_size' => $storedFile['file_size'],
                ]);

                $updateData['document_id'] = $document->id;
            } elseif ($item->type->value === 'form_field') {
                $updateData['form_value'] = $data['form_value'] ?? null;
            }
            // Acknowledgment type just needs status change

            $item->update($updateData);

            // Update checklist status to in_progress if still pending
            $checklist = $item->checklist;
            if ($checklist->status === PreboardingStatus::Pending) {
                $checklist->update(['status' => PreboardingStatus::InProgress]);
            }

            return $item->fresh();
        });
    }

    /**
     * Approve a submitted item.
     *
     * @throws ValidationException
     */
    public function approveItem(PreboardingChecklistItem $item): PreboardingChecklistItem
    {
        $allowed = $item->status->allowedTransitions();
        if (! in_array(PreboardingItemStatus::Approved, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This item cannot be approved in its current state.',
            ]);
        }

        return DB::transaction(function () use ($item) {
            $item->update([
                'status' => PreboardingItemStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);

            $this->recalculateStatus($item->checklist);

            return $item->fresh();
        });
    }

    /**
     * Reject a submitted item with a reason.
     *
     * @throws ValidationException
     */
    public function rejectItem(PreboardingChecklistItem $item, string $reason): PreboardingChecklistItem
    {
        $allowed = $item->status->allowedTransitions();
        if (! in_array(PreboardingItemStatus::Rejected, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This item cannot be rejected in its current state.',
            ]);
        }

        return DB::transaction(function () use ($item, $reason) {
            $item->update([
                'status' => PreboardingItemStatus::Rejected,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
                'rejection_reason' => $reason,
            ]);

            // Notify the new hire
            $checklist = $item->checklist->load('offer.jobApplication.candidate');
            $candidate = $checklist->offer->jobApplication->candidate;
            if ($candidate->email) {
                \Illuminate\Support\Facades\Notification::route('mail', $candidate->email)
                    ->notify(new PreboardingItemRejected($item));
            }

            return $item->fresh();
        });
    }

    /**
     * Recalculate and update the overall checklist status.
     */
    public function recalculateStatus(PreboardingChecklist $checklist): void
    {
        $requiredItems = $checklist->items()->where('is_required', true)->get();

        if ($requiredItems->isEmpty()) {
            return;
        }

        $allApproved = $requiredItems->every(fn ($item) => $item->status === PreboardingItemStatus::Approved);

        if ($allApproved) {
            $checklist->update([
                'status' => PreboardingStatus::Completed,
                'completed_at' => now(),
            ]);

            // Notify HR
            $checklist->load('offer.jobApplication.candidate');
            $creator = $checklist->creator;
            if ($creator) {
                $creator->notify(new PreboardingCompleted($checklist));
            }
        }
    }
}
