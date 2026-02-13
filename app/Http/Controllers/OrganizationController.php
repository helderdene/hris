<?php

namespace App\Http\Controllers;

use App\Enums\AccrualMethod;
use App\Enums\DeviceStatus;
use App\Enums\EmploymentType;
use App\Enums\GenderRestriction;
use App\Enums\HolidayType;
use App\Enums\JobLevel;
use App\Enums\LeaveCategory;
use App\Enums\LocationType;
use App\Enums\SyncStatus;
use App\Http\Resources\BiometricDeviceResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\DepartmentTreeResource;
use App\Http\Resources\HolidayResource;
use App\Http\Resources\LeaveTypeResource;
use App\Http\Resources\PositionResource;
use App\Http\Resources\SalaryGradeResource;
use App\Http\Resources\WorkLocationResource;
use App\Models\BiometricDevice;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\WorkLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    /**
     * Display the departments index page.
     */
    public function departmentsIndex(): Response
    {
        Gate::authorize('can-manage-organization');

        // Get all departments for the flat list
        $departments = Department::query()
            ->with(['parent', 'children', 'departmentHead'])
            ->orderBy('name')
            ->get();

        // Build hierarchical tree from the flat list (unlimited depth, no extra query)
        $departmentTree = $this->buildDepartmentTree($departments);

        return Inertia::render('Organization/Departments/Index', [
            'departments' => DepartmentResource::collection($departments),
            'departmentTree' => DepartmentTreeResource::collection($departmentTree),
        ]);
    }

    /**
     * Display the organization chart page.
     */
    public function orgChart(): Response
    {
        Gate::authorize('can-manage-organization');

        // Get all departments with hierarchy and department heads for org chart
        $departments = Department::query()
            ->with(['parent', 'children', 'departmentHead'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/OrgChart/Index', [
            'departments' => DepartmentResource::collection($departments),
        ]);
    }

    /**
     * Display the positions index page.
     */
    public function positionsIndex(): Response
    {
        Gate::authorize('can-manage-organization');

        $positions = Position::query()
            ->with(['salaryGrade'])
            ->orderBy('title')
            ->get();

        $salaryGrades = SalaryGrade::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/Positions/Index', [
            'positions' => PositionResource::collection($positions),
            'salaryGrades' => SalaryGradeResource::collection($salaryGrades),
            'jobLevels' => $this->getJobLevelOptions(),
            'employmentTypes' => $this->getEmploymentTypeOptions(),
        ]);
    }

    /**
     * Display the salary grades index page.
     */
    public function salaryGradesIndex(): Response
    {
        Gate::authorize('can-manage-organization');

        $salaryGrades = SalaryGrade::query()
            ->with(['steps' => fn ($q) => $q->orderBy('step_number')])
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/SalaryGrades/Index', [
            'salaryGrades' => SalaryGradeResource::collection($salaryGrades),
        ]);
    }

    /**
     * Display the work locations index page.
     */
    public function locationsIndex(): Response
    {
        Gate::authorize('can-manage-organization');

        $locations = WorkLocation::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/Locations/Index', [
            'locations' => WorkLocationResource::collection($locations),
            'locationTypes' => $this->getLocationTypeOptions(),
        ]);
    }

    /**
     * Display the biometric devices index page.
     */
    public function devicesIndex(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = BiometricDevice::query()
            ->with('workLocation')
            ->orderBy('name');

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Apply work location filter
        if ($request->filled('work_location_id')) {
            $query->where('work_location_id', $request->input('work_location_id'));
        }

        $devices = $query->withCount([
            'employeeDeviceSyncs as synced_count' => fn ($q) => $q->where('status', SyncStatus::Synced),
            'employeeDeviceSyncs as pending_count' => fn ($q) => $q->where('status', SyncStatus::Pending),
            'employeeDeviceSyncs as failed_count' => fn ($q) => $q->where('status', SyncStatus::Failed),
        ])->get();

        // Get status counts using DB-level aggregation (unfiltered)
        $statusCounts = BiometricDevice::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(case when status = ? then 1 end) as online', [DeviceStatus::Online->value])
            ->selectRaw('count(case when status = ? then 1 end) as offline', [DeviceStatus::Offline->value])
            ->first();

        // Get active work locations for filter dropdown
        $workLocations = WorkLocation::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        // Pre-fetch employee counts per location in a single query
        $locationEmployeeCounts = Employee::active()
            ->whereIn('work_location_id', $devices->pluck('work_location_id')->unique()->filter())
            ->groupBy('work_location_id')
            ->selectRaw('work_location_id, count(*) as count')
            ->pluck('count', 'work_location_id');

        // Build sync meta from pre-fetched data
        $deviceSyncMeta = [];
        foreach ($devices as $device) {
            $deviceSyncMeta[$device->id] = [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'total_employees' => $locationEmployeeCounts[$device->work_location_id] ?? 0,
                'synced_count' => $device->synced_count,
                'pending_count' => $device->pending_count,
                'failed_count' => $device->failed_count,
            ];
        }

        return Inertia::render('Organization/Devices/Index', [
            'devices' => BiometricDeviceResource::collection($devices),
            'workLocations' => WorkLocationResource::collection($workLocations),
            'statusCounts' => $statusCounts,
            'deviceSyncMeta' => $deviceSyncMeta,
            'filters' => [
                'status' => $request->input('status'),
                'work_location_id' => $request->input('work_location_id'),
            ],
        ]);
    }

    /**
     * Display the holidays index page.
     */
    public function holidaysIndex(): Response
    {
        Gate::authorize('can-manage-organization');

        $holidays = Holiday::query()
            ->with('workLocation')
            ->orderBy('date')
            ->get();

        $workLocations = WorkLocation::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/Holidays/Index', [
            'holidays' => HolidayResource::collection($holidays),
            'holidayTypes' => $this->getHolidayTypeOptions(),
            'workLocations' => WorkLocationResource::collection($workLocations),
        ]);
    }

    /**
     * Display the leave types index page.
     */
    public function leaveTypesIndex(): Response
    {
        Gate::authorize('can-manage-organization');

        $leaveTypes = LeaveType::query()
            ->orderBy('leave_category')
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/LeaveTypes/Index', [
            'leaveTypes' => LeaveTypeResource::collection($leaveTypes),
            'leaveCategories' => $this->getLeaveCategoryOptions(),
            'accrualMethods' => $this->getAccrualMethodOptions(),
            'genderRestrictions' => $this->getGenderRestrictionOptions(),
            'employmentTypes' => $this->getEmploymentTypeOptions(),
        ]);
    }

    /**
     * Get job level options as array for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getJobLevelOptions(): array
    {
        return array_map(
            fn (JobLevel $level) => [
                'value' => $level->value,
                'label' => $level->label(),
            ],
            JobLevel::cases()
        );
    }

    /**
     * Get employment type options as array for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getEmploymentTypeOptions(): array
    {
        return array_map(
            fn (EmploymentType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            EmploymentType::cases()
        );
    }

    /**
     * Get location type options as array for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getLocationTypeOptions(): array
    {
        return array_map(
            fn (LocationType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            LocationType::cases()
        );
    }

    /**
     * Get holiday type options as array for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getHolidayTypeOptions(): array
    {
        return array_map(
            fn (HolidayType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            HolidayType::cases()
        );
    }

    /**
     * Get leave category options as array for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getLeaveCategoryOptions(): array
    {
        return array_map(
            fn (LeaveCategory $category) => [
                'value' => $category->value,
                'label' => $category->label(),
            ],
            LeaveCategory::cases()
        );
    }

    /**
     * Get accrual method options as array for frontend.
     *
     * @return array<int, array{value: string, label: string, shortLabel: string}>
     */
    private function getAccrualMethodOptions(): array
    {
        return array_map(
            fn (AccrualMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
                'shortLabel' => $method->shortLabel(),
            ],
            AccrualMethod::cases()
        );
    }

    /**
     * Get gender restriction options as array for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getGenderRestrictionOptions(): array
    {
        return array_map(
            fn (GenderRestriction $restriction) => [
                'value' => $restriction->value,
                'label' => $restriction->label(),
            ],
            GenderRestriction::cases()
        );
    }

    /**
     * Build a hierarchical tree from a flat department collection.
     *
     * Supports unlimited depth by grouping departments by parent_id
     * and recursively setting the children relation in memory.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Department>  $departments
     * @return \Illuminate\Support\Collection<int, Department>
     */
    private function buildDepartmentTree(\Illuminate\Database\Eloquent\Collection $departments): \Illuminate\Support\Collection
    {
        $grouped = $departments->groupBy(fn (Department $d) => $d->parent_id ?? 'root');

        $buildLevel = function (string|int $parentKey) use ($grouped, &$buildLevel): \Illuminate\Support\Collection {
            $nodes = $grouped->get($parentKey, collect())->sortBy('name')->values();

            foreach ($nodes as $node) {
                $node->setRelation('children', $buildLevel($node->id));
            }

            return $nodes;
        };

        return $buildLevel('root');
    }
}
