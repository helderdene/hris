<?php

namespace App\Notifications;

use App\Authorization\RolePermissions;
use App\Enums\Permission;
use App\Enums\TenantUserRole;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;

/**
 * In-app notification for offboarding events (resignation filed, clearance, final pay).
 */
class OffboardingNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $url
    ) {}

    /**
     * Get the users in the current tenant whose role can manage employees.
     *
     * @return Collection<int, User>
     */
    public static function hrRecipients(): Collection
    {
        $roles = collect(TenantUserRole::cases())
            ->filter(fn (TenantUserRole $role) => collect([
                Permission::EmployeesView,
                Permission::EmployeesCreate,
                Permission::EmployeesEdit,
                Permission::EmployeesDelete,
            ])->every(fn (Permission $permission) => RolePermissions::roleHasPermission($role, $permission)))
            ->map(fn (TenantUserRole $role) => $role->value)
            ->all();

        return User::query()
            ->whereHas('tenants', fn ($query) => $query
                ->where('tenant_id', tenant()?->id)
                ->whereIn('role', $roles))
            ->get();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'offboarding',
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
