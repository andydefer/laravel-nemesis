<?php

// src/Models/NemesisToken.php

declare(strict_types=1);

namespace Kani\Nemesis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

/**
 * Model representing an authentication token for multi-model authentication.
 *
 * Supports token-based authentication with features like:
 * - Expiration management
 * - Ability-based permissions (RBAC)
 * - Origin/CORS restrictions
 * - Soft delete for revocation with audit trail
 *
 * @note Metadata management is handled by MetadataManager service
 */
class NemesisToken extends Model
{
    use SoftDeletes;

    protected $table = 'nemesis_tokens';

    protected $fillable = [
        'token_hash',
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
     *
     * @return MorphTo The polymorphic relationship
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if the token has expired.
     *
     * @return bool True if expired, false otherwise (null expiration means never expires)
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the token is revoked (soft deleted).
     *
     * @return bool True if revoked, false otherwise
     */
    public function isRevoked(): bool
    {
        return $this->trashed();
    }

    /**
     * Check if the token is still valid (not expired and not revoked).
     *
     * @return bool True if valid, false otherwise
     */
    public function isValid(): bool
    {
        if ($this->isExpired()) {
            return false;
        }
        return !$this->trashed();
    }

    /**
     * Revoke the token (soft delete).
     *
     * @return bool True if revoked, false otherwise
     */
    public function revoke(): bool
    {
        return $this->delete();
    }

    /**
     * Restore a revoked token.
     *
     * @return bool True if restored, false otherwise
     */
    public function restoreRevoked(): bool
    {
        return $this->restore();
    }

    /**
     * Check if the token has a specific ability.
     *
     * @param  string  $ability  The ability to check
     * @return bool True if token has the ability, false otherwise (null abilities means unrestricted)
     */
    public function can(string $ability): bool
    {
        if ($this->abilities === null) {
            return true;
        }

        return in_array($ability, $this->abilities);
    }

    /**
     * Check if the token has all specified abilities.
     *
     * @param  array<int, string>  $abilities  Array of abilities to check
     * @return bool True if token has all abilities, false otherwise
     */
    public function canAll(array $abilities): bool
    {
        if ($this->abilities === null) {
            return true;
        }

        foreach ($abilities as $ability) {
            if (! in_array($ability, $this->abilities)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the token can be used from a specific origin.
     *
     * @param  string|null  $origin  The origin URL to check (e.g., 'https://example.com')
     * @return bool True if origin is allowed, false otherwise
     */
    public function canUseFromOrigin(?string $origin): bool
    {
        if ($origin === null) {
            return true;
        }

        if ($this->allowed_origins === null || empty($this->allowed_origins)) {
            return true;
        }

        $normalizedOrigin = rtrim($origin, '/');

        foreach ($this->allowed_origins as $allowedOrigin) {
            $normalizedAllowed = rtrim($allowedOrigin, '/');

            if ($this->isWildcardMatch($normalizedOrigin, $normalizedAllowed)) {
                return true;
            }

            if (strcasecmp($normalizedOrigin, $normalizedAllowed) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the token can be used from the current request's origin.
     *
     * @param  Request|null  $request  The HTTP request (uses current request if null)
     * @return bool True if origin is allowed, false otherwise
     */
    public function canUseFromCurrentRequest(?Request $request = null): bool
    {
        $request = $request ?? request();
        $origin = $request->headers->get('Origin');

        return $this->canUseFromOrigin($origin);
    }

    /**
     * Update the last used timestamp of the token.
     *
     * @return self Returns the token instance for method chaining
     */
    public function updateLastUsed(): self
    {
        $this->update(['last_used_at' => now()]);

        return $this;
    }

    /**
     * Force expire the token immediately.
     *
     * @return self Returns the token instance for method chaining
     */
    public function forceExpire(): self
    {
        $this->expires_at = now()->subSecond();
        $this->save();

        return $this;
    }

    /**
     * Force expire the token by setting expiration in the past.
     *
     * @param  int  $minutes  Number of minutes in the past to set expiration
     * @return self Returns the token instance for method chaining
     */
    public function forceExpireByMinutes(int $minutes): self
    {
        $this->expires_at = now()->subMinutes($minutes);
        $this->save();

        return $this;
    }

    /**
     * Add an allowed origin to the token.
     *
     * @param  string  $origin  The origin to allow (e.g., 'https://example.com')
     * @return self Returns the token instance for method chaining
     */
    public function addAllowedOrigin(string $origin): self
    {
        $origins = $this->allowed_origins ?? [];

        if (! in_array($origin, $origins)) {
            $origins[] = $origin;
            $this->update(['allowed_origins' => $origins]);
        }

        return $this;
    }

    /**
     * Remove an allowed origin from the token.
     *
     * @param  string  $origin  The origin to remove
     * @return self Returns the token instance for method chaining
     */
    public function removeAllowedOrigin(string $origin): self
    {
        $origins = $this->allowed_origins ?? [];

        $key = array_search($origin, $origins, true);
        if ($key !== false) {
            unset($origins[$key]);
            $this->update(['allowed_origins' => array_values($origins)]);
        }

        return $this;
    }

    /**
     * Set the allowed origins (replaces all existing origins).
     *
     * @param  array<int, string>|null  $origins  Array of allowed origins or null to allow all
     * @return self Returns the token instance for method chaining
     */
    public function setAllowedOrigins(?array $origins): self
    {
        $this->update(['allowed_origins' => $origins]);

        return $this;
    }

    // Dans src/Models/NemesisToken.php, ajouter ces méthodes après les méthodes d'origins

    // ============================================================================
    // Metadata Methods
    // ============================================================================

    /**
     * Get a metadata value by key.
     *
     * @param string $key The metadata key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed The metadata value or default
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if a metadata key exists.
     *
     * This method allows distinguishing between:
     * - Key exists with null value → returns true
     * - Key does not exist → returns false
     *
     * @param string $key The metadata key to check
     * @return bool True if the key exists, false otherwise
     */
    public function hasMetadata(string $key): bool
    {
        return is_array($this->metadata) && array_key_exists($key, $this->metadata);
    }

    /**
     * Set a metadata value.
     *
     * @param string $key The metadata key
     * @param mixed $value The value to store (use null to keep key with null value)
     * @return self Returns the token instance for method chaining
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->update(['metadata' => $metadata]);

        return $this;
    }

    /**
     * Remove a metadata key.
     *
     * @param string $key The metadata key to remove
     * @return self Returns the token instance for method chaining
     */
    public function removeMetadata(string $key): self
    {
        $metadata = $this->metadata ?? [];

        if (array_key_exists($key, $metadata)) {
            unset($metadata[$key]);
            $this->update(['metadata' => $metadata === [] ? null : $metadata]);
        }

        return $this;
    }

    /**
     * Get all metadata.
     *
     * @return array|null All metadata or null if none exists
     */
    public function getAllMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Merge metadata with existing.
     *
     * @param array<string, mixed> $metadata Metadata to merge
     * @return self Returns the token instance for method chaining
     */
    public function mergeMetadata(array $metadata): self
    {
        $existing = $this->metadata ?? [];
        $merged = array_merge($existing, $metadata);

        return $this->setAllMetadata($merged);
    }

    /**
     * Set all metadata (replaces existing).
     *
     * @param array<string, mixed>|null $metadata New metadata or null to clear
     * @return self Returns the token instance for method chaining
     */
    public function setAllMetadata(?array $metadata): self
    {
        $this->update(['metadata' => $metadata]);

        return $this;
    }

    /**
     * Clear all metadata.
     *
     * @return self Returns the token instance for method chaining
     */
    public function clearMetadata(): self
    {
        return $this->setAllMetadata(null);
    }

    /**
     * Check if a wildcard pattern matches the origin.
     *
     * @param  string  $origin  The actual origin
     * @param  string  $pattern  The pattern to match against
     * @return bool True if pattern matches, false otherwise
     */
    private function isWildcardMatch(string $origin, string $pattern): bool
    {
        if (strpos($pattern, '*') === false) {
            return false;
        }

        $escapedPattern = preg_quote($pattern, '/');
        $regexPattern = str_replace('\\*', '.*', $escapedPattern);
        $regex = '/^' . $regexPattern . '$/i';

        return preg_match($regex, $origin) === 1;
    }
}
