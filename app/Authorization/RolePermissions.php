<?php

namespace App\Authorization;

use App\Enums\Permission;
use App\Enums\TenantUserRole;

/**
 * Configuration class defining permission sets for each role.
 *
 * This class maps roles to their allowed permissions, providing a central
 * location for authorization configuration without requiring database queries.
 */
class RolePermissions
{
    /**
     * Permission sets for each role.
     *
     * @var array<string, array<Permission>>
     */
    private static array $rolePermissions = [];

    /**
     * Get the permissions for a given role.
     *
     * @return array<Permission>
     */
    public static function getPermissionsForRole(TenantUserRole $role): array
    {
        return match ($role) {
            TenantUserRole::Admin => self::getAdminPermissions(),
            TenantUserRole::HrManager => self::getHrManagerPermissions(),
            TenantUserRole::HrStaff => self::getHrStaffPermissions(),
            TenantUserRole::HrConsultant => self::getHrConsultantPermissions(),
            TenantUserRole::Supervisor => self::getSupervisorPermissions(),
            TenantUserRole::Employee => self::getEmployeePermissions(),
        };
    }

    /**
     * Check if a role has a specific permission.
     */
    public static function roleHasPermission(TenantUserRole $role, Permission $permission): bool
    {
        return in_array($permission, self::getPermissionsForRole($role), true);
    }

    /**
     * Get all roles that have a specific permission.
     *
     * @return array<TenantUserRole>
     */
    public static function getRolesWithPermission(Permission $permission): array
    {
        $roles = [];

        foreach (TenantUserRole::cases() as $role) {
            if (self::roleHasPermission($role, $permission)) {
                $roles[] = $role;
            }
        }

        return $roles;
    }

    /**
     * Admin: All permissions
     *
     * Full tenant access, manages users/roles/settings, invites new users.
     *
     * @return array<Permission>
     */
    private static function getAdminPermissions(): array
    {
        return Permission::cases();
    }

    /**
     * HR Manager: All except SettingsManage, UsersManage
     *
     * Oversees HR operations, views all employee data, approval authority, access to reports.
     * Has organization management permission for departments, positions, grades, locations.
     * Has holidays management permission for Philippine holiday calendar.
     *
     * @return array<Permission>
     */
    private static function getHrManagerPermissions(): array
    {
        return [
            Permission::EmployeesView,
            Permission::EmployeesCreate,
            Permission::EmployeesEdit,
            Permission::EmployeesDelete,
            Permission::PayrollView,
            Permission::PayrollProcess,
            Permission::LeavesView,
            Permission::LeavesApprove,
            Permission::AttendanceView,
            Permission::ReportsView,
            Permission::OrganizationManage,
            Permission::HolidaysManage,
            Permission::RecruitmentView,
            Permission::RecruitmentManage,
            Permission::OffersCreate,
            Permission::OffersApprove,
            Permission::OfferTemplatesManage,
            Permission::TrainingView,
            Permission::TrainingManage,
        ];
    }

    /**
     * HR Staff: Day-to-day HR operations
     *
     * Employees (view/create/edit), Payroll (view/process), Leaves (view/approve), Attendance (view)
     * Also has holidays management permission for Philippine holiday calendar.
     *
     * @return array<Permission>
     */
    private static function getHrStaffPermissions(): array
    {
        return [
            Permission::EmployeesView,
            Permission::EmployeesCreate,
            Permission::EmployeesEdit,
            Permission::PayrollView,
            Permission::PayrollProcess,
            Permission::LeavesView,
            Permission::LeavesApprove,
            Permission::AttendanceView,
            Permission::HolidaysManage,
            Permission::RecruitmentView,
            Permission::RecruitmentManage,
            Permission::OffersCreate,
            Permission::OffersApprove,
            Permission::OfferTemplatesManage,
            Permission::TrainingView,
            Permission::TrainingManage,
        ];
    }

    /**
     * HR Consultant: Cross-tenant capable, consulting access level
     *
     * Same permissions as HR Staff for consistency.
     *
     * @return array<Permission>
     */
    private static function getHrConsultantPermissions(): array
    {
        return self::getHrStaffPermissions();
    }

    /**
     * Supervisor: Limited to direct reports only (requires org hierarchy)
     *
     * Employees (view - direct reports only), Leaves (approve - direct reports), Attendance (view - direct reports)
     * Note: Actual direct report scoping is handled at the gate/query level, not here.
     *
     * @return array<Permission>
     */
    private static function getSupervisorPermissions(): array
    {
        return [
            Permission::EmployeesView,
            Permission::LeavesApprove,
            Permission::AttendanceView,
            Permission::RecruitmentView,
            Permission::OffersCreate,
            Permission::TrainingView,
        ];
    }

    /**
     * Employee: Self-service only (no module permissions)
     *
     * Views own payslips/DTR/leave balances, files leave requests.
     * Self-service access is handled separately, not through module permissions.
     *
     * @return array<Permission>
     */
    private static function getEmployeePermissions(): array
    {
        return [
            Permission::TrainingView,
        ];
    }

    /**
     * Get a summary of all role permissions for documentation/debugging.
     *
     * @return array<string, array<string>>
     */
    public static function getAllRolePermissions(): array
    {
        $summary = [];

        foreach (TenantUserRole::cases() as $role) {
            $summary[$role->value] = array_map(
                fn (Permission $permission) => $permission->value(),
                self::getPermissionsForRole($role)
            );
        }

        return $summary;
    }
}
