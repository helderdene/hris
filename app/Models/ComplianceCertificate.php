<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ComplianceCertificate model for completion certificates.
 *
 * Stores certificate details, validity periods, and file references
 * for completed compliance training.
 */
class ComplianceCertificate extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ComplianceCertificateFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'compliance_assignment_id',
        'certificate_number',
        'issued_date',
        'valid_until',
        'final_score',
        'file_path',
        'file_name',
        'metadata',
        'is_revoked',
        'revocation_reason',
        'revoked_at',
        'revoked_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'valid_until' => 'date',
            'final_score' => 'decimal:2',
            'metadata' => 'array',
            'is_revoked' => 'boolean',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Get the assignment this certificate belongs to.
     */
    public function complianceAssignment(): BelongsTo
    {
        return $this->belongsTo(ComplianceAssignment::class);
    }

    /**
     * Get the employee who revoked this certificate.
     */
    public function revokedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'revoked_by');
    }

    /**
     * Scope to get only valid (non-revoked, non-expired) certificates.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_revoked', false)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            });
    }

    /**
     * Scope to get revoked certificates.
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('is_revoked', true);
    }

    /**
     * Scope to get expired certificates.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('is_revoked', false)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', now());
    }

    /**
     * Scope to get certificates expiring soon.
     */
    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->valid()
            ->whereNotNull('valid_until')
            ->where('valid_until', '<=', now()->addDays($days))
            ->where('valid_until', '>=', now());
    }

    /**
     * Check if the certificate is valid.
     */
    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->valid_until && now()->startOfDay()->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the certificate is expired.
     */
    public function isExpired(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        return $this->valid_until && now()->startOfDay()->gt($this->valid_until);
    }

    /**
     * Check if the certificate is expiring soon.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->isValid() || ! $this->valid_until) {
            return false;
        }

        return $this->valid_until->gte(now())
            && $this->valid_until->lte(now()->addDays($days));
    }

    /**
     * Get the number of days until expiration.
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (! $this->valid_until) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->valid_until, false);
    }

    /**
     * Revoke the certificate.
     */
    public function revoke(Employee $revokedBy, string $reason): bool
    {
        $this->is_revoked = true;
        $this->revocation_reason = $reason;
        $this->revoked_by = $revokedBy->id;
        $this->revoked_at = now();

        return $this->save();
    }

    /**
     * Generate a unique certificate number.
     */
    public static function generateCertificateNumber(): string
    {
        $prefix = 'CT';
        $year = now()->format('Y');
        $sequence = static::query()
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }
}
