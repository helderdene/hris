<?php

namespace App\Authorization;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Shared rule for records that belong to one employee (leave, overtime, loans):
 * the employee themselves and HR may act on them; assigned approvers may read them.
 */
class EmployeeRecordAccess
{
    /**
     * Whether the user may manage any employee's records.
     */
    public static function isHr(?User $user): bool
    {
        return $user !== null && Gate::forUser($user)->allows('can-manage-organization');
    }

    /**
     * Whether the user runs performance management: Admin and the HR roles.
     */
    public static function managesPerformance(?User $user): bool
    {
        return $user !== null && Gate::forUser($user)->allows('can-manage-performance');
    }

    /**
     * Whether the user may create a record for this employee: their own profile, or anyone's for HR.
     */
    public static function canFileFor(?User $user, mixed $employeeId): bool
    {
        if ($user === null) {
            return false;
        }

        return self::isHr($user)
            || Employee::query()->whereKey($employeeId)->where('user_id', $user->id)->exists();
    }

    /**
     * Whether the user may act on a record. It must have an `employee` relation and,
     * when $includeApprovers is set, an `approvals` relation with `approverEmployee`.
     */
    public static function canAccess(?User $user, Model $record, bool $includeApprovers = false): bool
    {
        if ($user === null) {
            return false;
        }

        if (self::isHr($user) || $record->employee?->user_id === $user->id) {
            return true;
        }

        return $includeApprovers && $record->approvals()
            ->whereHas('approverEmployee', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }
}
