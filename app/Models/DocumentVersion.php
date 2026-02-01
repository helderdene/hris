<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DocumentVersion model for tracking document version history.
 *
 * Extends TenantModel for multi-tenant database isolation.
 * Each document can have multiple versions with incrementing version numbers.
 */
class DocumentVersion extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DocumentVersionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'document_id',
        'version_number',
        'stored_filename',
        'file_path',
        'file_size',
        'mime_type',
        'version_notes',
        'uploaded_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size' => 'integer',
        ];
    }

    /**
     * Get the document this version belongs to.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user who uploaded this version.
     * Uses platform database connection since users are in the platform database.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')
            ->withDefault(['name' => 'Unknown User']);
    }

    /**
     * Get the uploaded by user from the platform database.
     * This is a workaround since users are in a different database.
     */
    public function getUploadedByUserAttribute(): ?User
    {
        if (! $this->uploaded_by) {
            return null;
        }

        return User::on('mysql')->find($this->uploaded_by);
    }
}
