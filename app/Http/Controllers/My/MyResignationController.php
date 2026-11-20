<?php

namespace App\Http\Controllers\My;

use App\Enums\EmploymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileResignationRequest;
use App\Models\Employee;
use App\Models\EmployeeOffboarding;
use App\Models\OffboardingClearanceItem;
use App\Notifications\OffboardingNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee self-service resignation filing and offboarding status.
 */
class MyResignationController extends Controller
{
    /**
     * Show the resignation form, or the employee's offboarding progress.
     */
    public function index(Request $request): Response
    {
        $employee = $this->employee($request);
        $offboarding = $employee?->currentOffboarding();

        if (! $offboarding?->isVisibleToEmployee()) {
            $offboarding = null;
        }

        return Inertia::render('My/Resignation/Index', [
            'hasEmployeeProfile' => $employee !== null,
            'canFile' => $employee?->employment_status === EmploymentStatus::Active,
            'offboarding' => $offboarding ? [
                'separation_type_label' => $offboarding->separation_type->label(),
                'filed_date' => $offboarding->filed_date?->toDateString(),
                'last_working_date' => $offboarding->last_working_date->toDateString(),
                'reason' => $offboarding->reason,
                'is_separated' => $offboarding->separated_at !== null,
                'final_pay_status' => $offboarding->final_pay_status->value,
                'final_pay_released_date' => $offboarding->final_pay_released_date?->toDateString(),
                'coe_status' => $offboarding->coe_status->value,
                'coe_issued_date' => $offboarding->coe_issued_date?->toDateString(),
                'clearance_items' => $offboarding->clearanceItems
                    ->map(fn (OffboardingClearanceItem $item) => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'is_cleared' => $item->cleared_at !== null,
                    ])
                    ->all(),
            ] : null,
        ]);
    }

    /**
     * File a resignation for the signed-in employee and notify HR.
     */
    public function store(FileResignationRequest $request): RedirectResponse
    {
        $employee = $this->employee($request);

        // One vague message for every refusal, so it never reveals an offboarding HR has not disclosed.
        if (! $employee || $employee->employment_status !== EmploymentStatus::Active || $employee->currentOffboarding()) {
            throw ValidationException::withMessages([
                'last_working_date' => 'Your resignation could not be filed online. Please contact HR.',
            ]);
        }

        EmployeeOffboarding::startFor($employee, [
            ...$request->validated(),
            'separation_type' => EmploymentStatus::Resigned,
            'filed_date' => now()->toDateString(),
        ]);

        Notification::send(OffboardingNotification::hrRecipients(), new OffboardingNotification(
            'Resignation Filed',
            "{$employee->full_name} filed a resignation effective {$request->date('last_working_date')->format('M j, Y')}.",
            "/employees/{$employee->id}?tab=offboarding",
        ));

        return redirect()->back()->with('success', 'Your resignation has been filed.');
    }

    /**
     * Get the employee profile linked to the signed-in user.
     */
    protected function employee(Request $request): ?Employee
    {
        return Employee::query()->where('user_id', $request->user()->id)->first();
    }
}
