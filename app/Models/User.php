<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Authorization\RolePermissions;
use App\Enums\Permission;
use App\Enums\TenantUserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the database connection for the model.
     *
     * Ensures User always queries the main database, not the tenant database.
     * Falls back to default for SQLite/testing.
     */
    public function getConnectionName(): ?string
    {
        $defaultConnection = config('database.default');

        if ($defaultConnection === 'sqlite') {
            return null;
        }

        return 'mysql';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * Get the tenants that the user belongs to.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot([
                'role',
                'invited_at',
                'invitation_accepted_at',
                'invitation_token',
                'invitation_expires_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get the user's role in a specific tenant.
     */
    public function getRoleInTenant(Tenant $tenant): ?TenantUserRole
    {
        $tenantUser = $this->tenants()
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($tenantUser === null) {
            return null;
        }

        $role = $tenantUser->pivot->role;

        // Handle both string and already-casted enum values
        if ($role instanceof TenantUserRole) {
            return $role;
        }

        return TenantUserRole::tryFrom($role);
    }

    /**
     * Check if the user is an admin in a specific tenant.
     */
    public function isAdminInTenant(Tenant $tenant): bool
    {
        $role = $this->getRoleInTenant($tenant);

        return $role === TenantUserRole::Admin;
    }

    /**
     * Check if the user has a specific permission in the current tenant context.
     *
     * Super Admins bypass all permission checks and always return true.
     */
    public function hasPermission(Permission $permission): bool
    {
        // Super Admin bypasses all permission checks
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Get the current tenant context
        $tenant = tenant();

        if ($tenant === null) {
            return false;
        }

        return $this->hasPermissionInTenant($permission, $tenant);
    }

    /**
     * Check if the user has a specific permission in a specific tenant.
     *
     * Super Admins bypass all permission checks and always return true.
     */
    public function hasPermissionInTenant(Permission $permission, Tenant $tenant): bool
    {
        // Super Admin bypasses all permission checks
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Get the user's role in the specified tenant
        $role = $this->getRoleInTenant($tenant);

        if ($role === null) {
            return false;
        }

        return RolePermissions::roleHasPermission($role, $permission);
    }

    /**
     * Check if the user can view employees.
     */
    public function canViewEmployees(): bool
    {
        return $this->hasPermission(Permission::EmployeesView);
    }

    /**
     * Check if the user can create employees.
     */
    public function canCreateEmployees(): bool
    {
        return $this->hasPermission(Permission::EmployeesCreate);
    }

    /**
     * Check if the user can edit employees.
     */
    public function canEditEmployees(): bool
    {
        return $this->hasPermission(Permission::EmployeesEdit);
    }

    /**
     * Check if the user can delete employees.
     */
    public function canDeleteEmployees(): bool
    {
        return $this->hasPermission(Permission::EmployeesDelete);
    }

    /**
     * Check if the user can view payroll.
     */
    public function canViewPayroll(): bool
    {
        return $this->hasPermission(Permission::PayrollView);
    }

    /**
     * Check if the user can process payroll.
     */
    public function canProcessPayroll(): bool
    {
        return $this->hasPermission(Permission::PayrollProcess);
    }

    /**
     * Check if the user can view leaves.
     */
    public function canViewLeaves(): bool
    {
        return $this->hasPermission(Permission::LeavesView);
    }

    /**
     * Check if the user can approve leaves.
     */
    public function canApproveLeaves(): bool
    {
        return $this->hasPermission(Permission::LeavesApprove);
    }

    /**
     * Check if the user can view attendance.
     */
    public function canViewAttendance(): bool
    {
        return $this->hasPermission(Permission::AttendanceView);
    }

    /**
     * Check if the user can view reports.
     */
    public function canViewReports(): bool
    {
        return $this->hasPermission(Permission::ReportsView);
    }

    /**
     * Check if the user can manage settings.
     */
    public function canManageSettings(): bool
    {
        return $this->hasPermission(Permission::SettingsManage);
    }

    /**
     * Check if the user can manage users.
     */
    public function canManageUsers(): bool
    {
        return $this->hasPermission(Permission::UsersManage);
    }
}
