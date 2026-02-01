<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'primary_color',
        'timezone',
        'business_info',
        'payroll_settings',
        'leave_defaults',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_info' => 'array',
            'payroll_settings' => 'array',
            'leave_defaults' => 'array',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->timezone)) {
                $tenant->timezone = 'Asia/Manila';
            }
        });
    }

    /**
     * Get the database connection for the model.
     * Uses 'platform' connection in production/MySQL environments,
     * but falls back to default for SQLite/testing.
     */
    public function getConnectionName(): ?string
    {
        $defaultConnection = config('database.default');

        if ($defaultConnection === 'sqlite') {
            return null;
        }

        return 'platform';
    }

    /**
     * Get the database schema name for this tenant.
     */
    public function getDatabaseName(): string
    {
        return 'kasamahr_tenant_'.$this->slug;
    }

    /**
     * Check if the slug is URL-safe.
     */
    public static function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    /**
     * Get the users that belong to the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
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
     * Get the double holiday pay rate from payroll settings.
     *
     * Returns the configured double holiday rate percentage, or 300% as the default.
     */
    public function getDoubleHolidayRate(): int
    {
        return $this->payroll_settings['double_holiday_rate'] ?? 300;
    }
}
