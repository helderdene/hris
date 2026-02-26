<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMyProfileRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyProfileController extends Controller
{
    /**
     * Display the employee's profile.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->with(['department', 'position', 'workLocation', 'supervisor'])
            ->first();

        $profilePhoto = $employee?->getProfilePhoto();

        return Inertia::render('My/Profile', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'last_name' => $employee->last_name,
                'suffix' => $employee->suffix,
                'initials' => $employee->initials,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'date_of_birth' => $employee->date_of_birth?->format('M d, Y'),
                'age' => $employee->age,
                'gender' => $employee->gender,
                'civil_status' => $employee->civil_status,
                'nationality' => $employee->nationality,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'work_location' => $employee->workLocation?->name,
                'supervisor' => $employee->supervisor?->full_name,
                'hire_date' => $employee->hire_date?->format('M d, Y'),
                'employment_type' => $employee->employment_type?->value,
                'employment_status' => $employee->employment_status?->value,
                'years_of_service' => $employee->years_of_service,
                'address' => $employee->address,
                'emergency_contact' => $employee->emergency_contact,
                'tin' => $employee->tin,
                'sss_number' => $employee->sss_number,
                'philhealth_number' => $employee->philhealth_number,
                'pagibig_number' => $employee->pagibig_number,
                'umid' => $employee->umid,
                'passport_number' => $employee->passport_number,
                'drivers_license' => $employee->drivers_license,
                'profile_photo_url' => $profilePhoto?->file_path
                    ? asset('storage/'.$profilePhoto->file_path)
                    : null,
            ] : null,
        ]);
    }

    /**
     * Update the employee's editable profile fields.
     */
    public function update(UpdateMyProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->firstOrFail();

        $employee->update($request->validated());

        return back()->with('success', 'Profile updated successfully.');
    }
}
