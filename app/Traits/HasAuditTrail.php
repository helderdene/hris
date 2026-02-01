<?php

namespace App\Traits;

use App\Enums\AuditAction;
use App\Models\AuditLog;

/**
 * Trait for automatically auditing Eloquent model changes.
 *
 * Hooks into model events (created, updated, deleted) to create audit logs
 * with before/after values, user information, and request metadata.
 *
 * Sensitive attributes like passwords and tokens are automatically filtered.
 */
trait HasAuditTrail
{
    /**
     * Attributes that should never be logged.
     *
     * @var array<string>
     */
    protected static array $auditExcludedAttributes = [
        'password',
        'remember_token',
        'api_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Boot the trait.
     */
    public static function bootHasAuditTrail(): void
    {
        // Skip auditing for AuditLog model to prevent infinite recursion
        if (static::class === AuditLog::class) {
            return;
        }

        static::created(function ($model) {
            $model->logAudit(AuditAction::Created);
        });

        static::updated(function ($model) {
            $model->logAudit(AuditAction::Updated);
        });

        static::deleted(function ($model) {
            $model->logAudit(AuditAction::Deleted);
        });
    }

    /**
     * Create an audit log entry for this model.
     */
    protected function logAudit(AuditAction $action): void
    {
        // Get the request for metadata
        $request = request();

        // Determine old and new values based on action
        $oldValues = null;
        $newValues = null;

        switch ($action) {
            case AuditAction::Created:
                $newValues = $this->filterSensitiveAttributes($this->getAttributes());
                break;

            case AuditAction::Updated:
                $oldValues = $this->filterSensitiveAttributes($this->getOriginal());
                $newValues = $this->filterSensitiveAttributes($this->getChanges());
                // Only log if there are actual changes (after filtering)
                if (empty($newValues)) {
                    return;
                }
                break;

            case AuditAction::Deleted:
                $oldValues = $this->filterSensitiveAttributes($this->getOriginal());
                break;
        }

        AuditLog::create([
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'action' => $action,
            'user_id' => auth()->id(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr($request->userAgent(), 0, 255) : null,
        ]);
    }

    /**
     * Filter out sensitive attributes from the values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    protected function filterSensitiveAttributes(array $values): ?array
    {
        if (empty($values)) {
            return null;
        }

        // Combine static excluded attributes with any model-specific exclusions
        $excluded = array_merge(
            static::$auditExcludedAttributes,
            $this->getAuditExcludedAttributes()
        );

        $filtered = array_diff_key($values, array_flip($excluded));

        return empty($filtered) ? null : $filtered;
    }

    /**
     * Get model-specific attributes to exclude from audit logs.
     * Override this method in your model to exclude additional attributes.
     *
     * @return array<string>
     */
    protected function getAuditExcludedAttributes(): array
    {
        return [];
    }

    /**
     * Get all audit logs for this model.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AuditLog>
     */
    public function auditLogs()
    {
        return AuditLog::where('auditable_type', static::class)
            ->where('auditable_id', $this->getKey())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get the latest audit log for this model.
     */
    public function latestAuditLog(): ?AuditLog
    {
        return AuditLog::where('auditable_type', static::class)
            ->where('auditable_id', $this->getKey())
            ->latest()
            ->first();
    }
}
