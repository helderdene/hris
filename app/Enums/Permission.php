<?php

namespace App\Enums;

/**
 * Module-based permissions for the authorization system.
 *
 * Each permission corresponds to a specific action within a module.
 * Permissions are assigned to roles via the RolePermissions configuration class.
 */
enum Permission: string
{
    // Employees module
    case EmployeesView = 'employees_view';
    case EmployeesCreate = 'employees_create';
    case EmployeesEdit = 'employees_edit';
    case EmployeesDelete = 'employees_delete';

    // Payroll module
    case PayrollView = 'payroll_view';
    case PayrollProcess = 'payroll_process';

    // Leaves module
    case LeavesView = 'leaves_view';
    case LeavesApprove = 'leaves_approve';

    // Attendance module
    case AttendanceView = 'attendance_view';

    // Reports module
    case ReportsView = 'reports_view';

    // Settings module
    case SettingsManage = 'settings_manage';

    // Users module
    case UsersManage = 'users_manage';

    // Organization module
    case OrganizationManage = 'organization_manage';

    // Holidays module
    case HolidaysManage = 'holidays_manage';

    // Recruitment module
    case RecruitmentView = 'recruitment_view';
    case RecruitmentManage = 'recruitment_manage';
    case OffersCreate = 'offers_create';
    case OffersApprove = 'offers_approve';
    case OfferTemplatesManage = 'offer_templates_manage';

    // Training module
    case TrainingView = 'training_view';
    case TrainingManage = 'training_manage';

    // Audit Logs module
    case AuditLogsView = 'audit_logs_view';

    /**
     * Get the dot-notation string representation of the permission.
     *
     * Example: EmployeesView returns 'employees.view'
     */
    public function value(): string
    {
        return match ($this) {
            self::EmployeesView => 'employees.view',
            self::EmployeesCreate => 'employees.create',
            self::EmployeesEdit => 'employees.edit',
            self::EmployeesDelete => 'employees.delete',
            self::PayrollView => 'payroll.view',
            self::PayrollProcess => 'payroll.process',
            self::LeavesView => 'leaves.view',
            self::LeavesApprove => 'leaves.approve',
            self::AttendanceView => 'attendance.view',
            self::ReportsView => 'reports.view',
            self::SettingsManage => 'settings.manage',
            self::UsersManage => 'users.manage',
            self::OrganizationManage => 'organization.manage',
            self::HolidaysManage => 'holidays.manage',
            self::RecruitmentView => 'recruitment.view',
            self::RecruitmentManage => 'recruitment.manage',
            self::OffersCreate => 'offers.create',
            self::OffersApprove => 'offers.approve',
            self::OfferTemplatesManage => 'offer_templates.manage',
            self::TrainingView => 'training.view',
            self::TrainingManage => 'training.manage',
            self::AuditLogsView => 'audit_logs.view',
        };
    }

    /**
     * Get a human-readable label for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::EmployeesView => 'View Employees',
            self::EmployeesCreate => 'Create Employees',
            self::EmployeesEdit => 'Edit Employees',
            self::EmployeesDelete => 'Delete Employees',
            self::PayrollView => 'View Payroll',
            self::PayrollProcess => 'Process Payroll',
            self::LeavesView => 'View Leaves',
            self::LeavesApprove => 'Approve Leaves',
            self::AttendanceView => 'View Attendance',
            self::ReportsView => 'View Reports',
            self::SettingsManage => 'Manage Settings',
            self::UsersManage => 'Manage Users',
            self::OrganizationManage => 'Manage Organization',
            self::HolidaysManage => 'Manage Holidays',
            self::RecruitmentView => 'View Recruitment',
            self::RecruitmentManage => 'Manage Recruitment',
            self::OffersCreate => 'Create Offers',
            self::OffersApprove => 'Approve Offers',
            self::OfferTemplatesManage => 'Manage Offer Templates',
            self::TrainingView => 'View Training',
            self::TrainingManage => 'Manage Training',
            self::AuditLogsView => 'View Audit Logs',
        };
    }

    /**
     * Get the module name for this permission.
     */
    public function module(): string
    {
        return match ($this) {
            self::EmployeesView, self::EmployeesCreate, self::EmployeesEdit, self::EmployeesDelete => 'employees',
            self::PayrollView, self::PayrollProcess => 'payroll',
            self::LeavesView, self::LeavesApprove => 'leaves',
            self::AttendanceView => 'attendance',
            self::ReportsView => 'reports',
            self::SettingsManage => 'settings',
            self::UsersManage => 'users',
            self::OrganizationManage => 'organization',
            self::HolidaysManage => 'holidays',
            self::RecruitmentView, self::RecruitmentManage => 'recruitment',
            self::OffersCreate, self::OffersApprove => 'offers',
            self::OfferTemplatesManage => 'offer_templates',
            self::TrainingView, self::TrainingManage => 'training',
            self::AuditLogsView => 'audit_logs',
        };
    }

    /**
     * Get all permissions for a specific module.
     *
     * @return array<Permission>
     */
    public static function forModule(string $module): array
    {
        return array_filter(
            self::cases(),
            fn (Permission $permission) => $permission->module() === $module
        );
    }

    /**
     * Get all permission values as an array.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_map(
            fn (Permission $permission) => $permission->value(),
            self::cases()
        );
    }

    /**
     * Try to create a permission from its dot-notation value.
     */
    public static function fromValue(string $value): ?self
    {
        foreach (self::cases() as $permission) {
            if ($permission->value() === $value) {
                return $permission;
            }
        }

        return null;
    }
}
