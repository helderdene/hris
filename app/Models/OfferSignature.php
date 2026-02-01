<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Electronic signature captured for an offer letter.
 */
class OfferSignature extends TenantModel
{
    /** @use HasFactory<\Database\Factories\OfferSignatureFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'offer_id',
        'signer_type',
        'signer_name',
        'signer_email',
        'signature_data',
        'ip_address',
        'user_agent',
        'signed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    /**
     * Get the offer this signature belongs to.
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
