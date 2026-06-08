<?php
// src/Models/NemesisToken.php

declare(strict_types=1);

namespace Kani\Nemesis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Model representing an authentication token - PURE DATA CONTAINER ONLY.
 * 
 * NO business logic whatsoever.
 * ONLY:
 * - Relationships
 * - Getters for computed properties
 * 
 * All capabilities and business logic are in NemesisService.
 */
final class NemesisToken extends Model
{
    use SoftDeletes;

    protected $table = 'nemesis_tokens';

    protected $fillable = [
        'token_hash',
        'tokenable_type',
        'tokenable_id',
        'name',
        'source',
        'abilities',
        'metadata',
        'allowed_origins',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'metadata' => 'array',
        'allowed_origins' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = ['token_hash'];

    /**
     * Get the parent authenticatable model (polymorphic relation).
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    /**
     * Check if token is revoked (soft deleted).
     */
    public function isRevoked(): bool
    {
        return $this->trashed();
    }

    /**
     * Check if token is valid (not expired and not revoked).
     */
    public function isValid(): bool
    {
        if ($this->isExpired()) {
            return false;
        }
        return !$this->trashed();
    }
}
