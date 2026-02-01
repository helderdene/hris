<?php

namespace App\Enums;

/**
 * Roles that a user can have within a tenant.
 *
 * Note: Super Admin is platform-level and stored in users.is_super_admin,
 * not in this enum.
 */
enum TenantUserRole: string
{
    case Admin = 'admin';
    case HrManager = 'hr_manager';
    case HrStaff = 'hr_staff';
    case HrConsultant = 'hr_consultant';
    case Supervisor = 'supervisor';
    case Employee = 'employee';

    /**
     * Get a human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::HrManager => 'HR Manager',
            self::HrStaff => 'HR Staff',
            self::HrConsultant => 'HR Consultant',
            self::Supervisor => 'Supervisor',
            self::Employee => 'Employee',
        };
    }

    /**
     * Get the permissions for this role.
     *
     * Stub implementation - full implementation in Task Group 3.
     *
     * @return array<string>
     */
    public function permissions(): array
    {
        // Stub: will be implemented in Task Group 3 with Permission enum
        return [];
    }

    /**
     * Get all available roles as an array of values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a given value is a valid role.
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }

    /**
     * Try to create a role from a string value.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
