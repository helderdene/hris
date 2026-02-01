<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * AuditLog model for tracking changes to tenant-scoped models.
 *
 * Stores before/after values as JSON for comprehensive audit trails.
 * Does NOT use the HasAuditTrail trait to prevent infinite recursion.
 */
class AuditLog extends TenantModel
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action',
        'user_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Get the user name from the main database connection.
     */
    public function getUserNameAttribute(): ?string
    {
        if (! $this->user_id) {
            return null;
        }

        return User::find($this->user_id)?->name;
    }

    /**
     * Get a short version of the model type (class name only).
     */
    public function getModelNameAttribute(): string
    {
        return class_basename($this->auditable_type);
    }

    /**
     * Scope to filter by auditable type.
     */
    public function scopeForModel(Builder $query, string $modelClass): Builder
    {
        return $query->where('auditable_type', $modelClass);
    }

    /**
     * Scope to filter by action type.
     */
    public function scopeForAction(Builder $query, AuditAction $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Get the list of unique auditable types for filtering.
     *
     * @return array<string>
     */
    public static function getAuditableTypes(): array
    {
        return static::query()
            ->select('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->toArray();
    }
}
