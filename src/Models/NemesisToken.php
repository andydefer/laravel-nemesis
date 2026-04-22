<?php
// src/Models/NemesisToken.php

namespace Kani\Nemesis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NemesisToken extends Model
{
    protected $table = 'nemesis_tokens';

    protected $fillable = [
        'token',
        'name',
        'source',
        'abilities',
        'metadata',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'metadata' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = ['token'];

    /**
     * Get the owning tokenable model
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if token has ability
     */
    public function can(string $ability): bool
    {
        if ($this->abilities === null) {
            return true;
        }

        return in_array($ability, $this->abilities);
    }

    /**
     * Check if token has all abilities
     */
    public function canAll(array $abilities): bool
    {
        if ($this->abilities === null) {
            return true;
        }

        foreach ($abilities as $ability) {
            if (!in_array($ability, $this->abilities)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if token is valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(): self
    {
        $this->update(['last_used_at' => now()]);

        return $this;
    }

    /**
     * Get metadata value
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set metadata value
     */
    public function setMetadata(string $key, $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->update(['metadata' => $metadata]);

        return $this;
    }
}
