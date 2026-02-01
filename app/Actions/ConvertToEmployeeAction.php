<?php

namespace App\Actions;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OnboardingTemplate;
use App\Models\Position;
use App\Models\PreboardingChecklist;
use App\Models\User;
use App\Models\WorkLocation;
use App\Services\OnboardingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts a completed preboarding checklist into an Employee record.
 *
 * Pre-fills employee data from the Candidate and Offer records.
 */
class ConvertToEmployeeAction
{
    /**
     * Convert a preboarding checklist to an employee.
     *
     * @throws ValidationException
     */
    public function execute(PreboardingChecklist $checklist): Employee
    {
        // Load required relationships
        $checklist->load(['offer', 'jobApplication.candidate']);

        $offer = $checklist->offer;
        $candidate = $checklist->jobApplication->candidate;

        // Check if employee already exists for this candidate
        $existingEmployee = Employee::where('email', $candidate->email)->first();
        if ($existingEmployee) {
            throw ValidationException::withMessages([
                'employee' => 'An employee with this email already exists.',
            ]);
        }

        return DB::transaction(function () use ($offer, $candidate) {
            // Find the user account created during hiring
            $user = User::where('email', $candidate->email)->first();

            // Try to find matching department, position, and work location
            $departmentId = $this->findDepartmentId($offer->department);
            $positionId = $this->findPositionId($offer->position_title);
            $workLocationId = $this->findWorkLocationId($offer->work_location);

            // Map employment type from offer to enum
            $employmentType = $this->mapEmploymentType($offer->employment_type);

            // Build address array from candidate data
            $address = null;
            if ($candidate->address || $candidate->city || $candidate->state) {
                $address = [
                    'street' => $candidate->address,
                    'city' => $candidate->city,
                    'state' => $candidate->state,
                    'zip_code' => $candidate->zip_code,
                    'country' => $candidate->country,
                ];
            }

            // Create the employee
            $employee = Employee::create([
                'user_id' => $user?->id,
                'employee_number' => $this->generateEmployeeNumber(),

                // Personal info from candidate
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'date_of_birth' => $candidate->date_of_birth,
                'address' => $address,

                // Employment details from offer
                'department_id' => $departmentId,
                'position_id' => $positionId,
                'work_location_id' => $workLocationId,
                'employment_type' => $employmentType,
                'employment_status' => EmploymentStatus::Active,
                'hire_date' => $offer->start_date,
                'basic_salary' => $offer->salary,
                'pay_frequency' => $offer->salary_frequency,
            ]);

            // Create onboarding checklist for the new employee
            $this->createOnboardingChecklist($employee);

            return $employee->fresh(['department', 'position', 'workLocation']);
        });
    }

    /**
     * Find a department by name.
     */
    protected function findDepartmentId(?string $departmentName): ?int
    {
        if (! $departmentName) {
            return null;
        }

        $department = Department::where('name', 'like', "%{$departmentName}%")->first();

        return $department?->id;
    }

    /**
     * Find a position by title.
     */
    protected function findPositionId(?string $positionTitle): ?int
    {
        if (! $positionTitle) {
            return null;
        }

        $position = Position::where('title', 'like', "%{$positionTitle}%")->first();

        return $position?->id;
    }

    /**
     * Find a work location by name.
     */
    protected function findWorkLocationId(?string $workLocationName): ?int
    {
        if (! $workLocationName) {
            return null;
        }

        $workLocation = WorkLocation::where('name', 'like', "%{$workLocationName}%")->first();

        return $workLocation?->id;
    }

    /**
     * Map offer employment type string to EmploymentType enum.
     */
    protected function mapEmploymentType(?string $offerType): EmploymentType
    {
        if (! $offerType) {
            return EmploymentType::Probationary;
        }

        return match (strtolower($offerType)) {
            'regular', 'full_time', 'full-time', 'fulltime', 'permanent' => EmploymentType::Regular,
            'probationary', 'probation' => EmploymentType::Probationary,
            'contractual', 'contract', 'contractor' => EmploymentType::Contractual,
            'consultant', 'consulting' => EmploymentType::Consultant,
            'intern', 'internship' => EmploymentType::Intern,
            'project_based', 'project-based', 'projectbased' => EmploymentType::ProjectBased,
            default => EmploymentType::Probationary,
        };
    }

    /**
     * Generate a unique employee number.
     */
    protected function generateEmployeeNumber(): string
    {
        $year = date('Y');
        $lastEmployee = Employee::withTrashed()
            ->where('employee_number', 'like', "EMP-{$year}-%")
            ->orderByRaw('CAST(SUBSTRING(employee_number, -4) AS UNSIGNED) DESC')
            ->first();

        if ($lastEmployee) {
            $lastNumber = (int) substr($lastEmployee->employee_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('EMP-%s-%04d', $year, $nextNumber);
    }

    /**
     * Create an onboarding checklist for the new employee.
     */
    protected function createOnboardingChecklist(Employee $employee): void
    {
        // Only create if there's an active onboarding template
        $hasTemplate = OnboardingTemplate::query()
            ->where('is_active', true)
            ->exists();

        if ($hasTemplate) {
            app(OnboardingService::class)->createFromEmployee($employee);
        }
    }
}
