<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantRedirectToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'token',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
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
     * Get the user that owns the token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant associated with the token.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the token is valid (not expired).
     */
    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Find a valid (non-expired) token by its string value.
     */
    public static function findValidToken(string $token): ?self
    {
        return static::where('token', $token)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Delete all expired tokens.
     */
    public static function deleteExpired(): int
    {
        return static::where('expires_at', '<=', now())->delete();
    }
}
