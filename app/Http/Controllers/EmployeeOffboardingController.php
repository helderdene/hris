<?php

namespace App\Http\Controllers;

use App\Enums\DocumentRequestStatus;
use App\Enums\DocumentRequestType;
use App\Enums\EmploymentStatus;
use App\Enums\OffboardingStepStatus;
use App\Http\Controllers\Api\DocumentRequestAdminController;
use App\Http\Requests\ClearanceItemRequest;
use App\Http\Requests\StartOffboardingRequest;
use App\Http\Requests\UpdateDocumentRequestRequest;
use App\Http\Requests\UpdateOffboardingRequest;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeOffboarding;
use App\Models\OffboardingClearanceItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeOffboardingController extends Controller
{
    /**
     * List offboardings across the organization.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-employees');

        $query = EmployeeOffboarding::query()
            ->with('employee.department')
            ->withCount([
                'clearanceItems',
                'clearanceItems as cleared_items_count' => fn ($query) => $query->whereNotNull('cleared_at'),
            ])
            ->latest('id');

        match ($request->input('status')) {
            'in_progress' => $query->whereNull('separated_at'),
            'separated' => $query->whereNotNull('separated_at'),
            default => null,
        };

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', fn ($employees) => $employees
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_number', 'like', "%{$search}%"));
        }

        $offboardings = $query->paginate(25)->withQueryString();

        return Inertia::render('Offboarding/Index', [
            'offboardings' => [
                'data' => $offboardings->map(fn (EmployeeOffboarding $offboarding) => [
                    'id' => $offboarding->id,
                    'employee_id' => $offboarding->employee_id,
                    'employee_name' => $offboarding->employee?->full_name,
                    'employee_number' => $offboarding->employee?->employee_number,
                    'department' => $offboarding->employee?->department?->name,
                    'separation_type_label' => $offboarding->separation_type->label(),
                    'last_working_date' => $offboarding->last_working_date->format('M d, Y'),
                    'is_separated' => $offboarding->separated_at !== null,
                    'has_exit_interview' => $offboarding->exit_interview_date !== null,
                    'clearance_items_count' => $offboarding->clearance_items_count,
                    'cleared_items_count' => $offboarding->cleared_items_count,
                    'final_pay_status' => $offboarding->final_pay_status->value,
                    'coe_status' => $offboarding->coe_status->value,
                ])->all(),
                'links' => $offboardings->linkCollection()->toArray(),
                'total' => $offboardings->total(),
            ],
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
            ],
            'summary' => [
                'in_progress' => EmployeeOffboarding::query()->whereNull('separated_at')->count(),
                'separated' => EmployeeOffboarding::query()->whereNotNull('separated_at')->count(),
                'final_pay_pending' => EmployeeOffboarding::query()->where('final_pay_status', '!=', OffboardingStepStatus::Completed)->count(),
                'coe_pending' => EmployeeOffboarding::query()->where('coe_status', '!=', OffboardingStepStatus::Completed)->count(),
            ],
        ]);
    }

    /**
     * Start offboarding for an employee. The employee stays active until separated.
     */
    public function start(StartOffboardingRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        abort_if($employee->currentOffboarding() !== null, 409, 'This employee already has an offboarding record.');

        $isActive = $employee->employment_status === EmploymentStatus::Active;

        $offboarding = EmployeeOffboarding::startFor($employee, [
            ...$request->validated(),
            'separated_at' => $isActive ? null : now(),
        ]);

        if ($isActive) {
            $offboarding->notifyEmployee('Offboarding Started', 'HR has started your offboarding. Track your clearance, final pay and COE here.');
        }

        return redirect()->back()->with('success', 'Offboarding started.');
    }

    /**
     * Update separation details, exit interview, final pay or COE status.
     */
    public function update(UpdateOffboardingRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        $offboarding = $this->currentOffboardingOrFail($employee);

        // Once separated, type and last day mirror the employee record; a linked COE request owns the COE status.
        $offboarding->update($request->safe()->except([
            ...($offboarding->separated_at ? ['separation_type', 'last_working_date'] : []),
            ...($offboarding->coeDocumentRequest ? ['coe_status', 'coe_issued_date'] : []),
        ]));

        if ($offboarding->wasChanged('final_pay_status') && $offboarding->final_pay_status === OffboardingStepStatus::Completed) {
            $offboarding->notifyEmployee('Final Pay Released', 'Your final pay has been released.');
        }

        return redirect()->back()->with('success', 'Offboarding updated.');
    }

    /**
     * Cancel an offboarding that has not been finalised, e.g. a withdrawn resignation.
     */
    public function destroy(Employee $employee): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        $offboarding = $this->currentOffboardingOrFail($employee);

        abort_if($offboarding->separated_at !== null, 409, 'A finalised offboarding cannot be cancelled.');

        $offboarding->delete();

        return redirect()->back()->with('success', 'Offboarding cancelled.');
    }

    /**
     * Create a COE document request for the employee; it links itself to the offboarding.
     */
    public function requestCoe(Employee $employee): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        $offboarding = $this->currentOffboardingOrFail($employee);

        abort_if($offboarding->coeDocumentRequest !== null, 409, 'A COE request is already linked.');

        // The request appears in the employee's own document requests, so it must not reveal an undisclosed separation.
        abort_unless($offboarding->isVisibleToEmployee(), 409, 'A COE request can be created once the employee is separated.');

        $offboarding->update(['coe_document_request_id' => null]);

        DocumentRequest::create([
            'employee_id' => $employee->id,
            'document_type' => DocumentRequestType::Coe,
            'status' => DocumentRequestStatus::Pending,
            'notes' => 'Requested by HR during offboarding.',
        ]);

        return redirect()->back()->with('success', 'COE request created.');
    }

    /**
     * Advance the linked COE request through the regular document-request flow.
     */
    public function updateCoeRequest(UpdateDocumentRequestRequest $request, Employee $employee, DocumentRequestAdminController $documentRequests): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        $documentRequest = $this->currentOffboardingOrFail($employee)->coeDocumentRequest ?? abort(404);

        $documentRequests->update($request, $documentRequest);

        return redirect()->back()->with('success', 'COE request updated.');
    }

    /**
     * Add a clearance item.
     */
    public function storeClearanceItem(ClearanceItemRequest $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        $this->currentOffboardingOrFail($employee)->clearanceItems()->create($request->safe()->only(['name', 'remarks']));

        return redirect()->back();
    }

    /**
     * Mark a clearance item cleared or pending, or edit its remarks.
     */
    public function updateClearanceItem(ClearanceItemRequest $request, Employee $employee, OffboardingClearanceItem $item): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        abort_unless($item->employee_offboarding_id === $this->currentOffboardingOrFail($employee)->id, 404);

        $attributes = $request->safe()->only(['name', 'remarks']);

        if ($request->has('is_cleared')) {
            $isCleared = $request->boolean('is_cleared');
            $attributes['cleared_at'] = $isCleared ? now() : null;
            $attributes['cleared_by'] = $isCleared ? $request->user()->id : null;
        }

        $item->update($attributes);

        if ($item->wasChanged('cleared_at') && $item->cleared_at && $item->offboarding->clearanceItems()->whereNull('cleared_at')->doesntExist()) {
            $item->offboarding->notifyEmployee('Clearance Complete', 'All your clearance items have been cleared.');
        }

        return redirect()->back();
    }

    /**
     * Remove a clearance item.
     */
    public function destroyClearanceItem(Employee $employee, OffboardingClearanceItem $item): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        abort_unless($item->employee_offboarding_id === $this->currentOffboardingOrFail($employee)->id, 404);

        $item->delete();

        return redirect()->back();
    }

    /**
     * Get the employee's current offboarding or fail with a 404.
     */
    protected function currentOffboardingOrFail(Employee $employee): EmployeeOffboarding
    {
        return $employee->currentOffboarding() ?? abort(404);
    }
}
