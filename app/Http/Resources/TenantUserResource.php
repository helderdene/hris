<?php

namespace App\Http\Resources;

use App\Enums\TenantUserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read \App\Models\User $resource
 */
class TenantUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pivot = $this->resource->pivot;

        // Get the role from pivot
        $role = $pivot->role;

        // Handle both enum and string values for role
        if ($role instanceof TenantUserRole) {
            $roleValue = $role->value;
            $roleLabel = $role->label();
        } else {
            $roleEnum = TenantUserRole::tryFrom($role);
            $roleValue = $role;
            $roleLabel = $roleEnum?->label() ?? 'Member';
        }

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'role' => $roleValue,
            'role_label' => $roleLabel,
            'invited_at' => $pivot->invited_at?->toISOString(),
            'invitation_accepted_at' => $pivot->invitation_accepted_at?->toISOString(),
        ];
    }
}
