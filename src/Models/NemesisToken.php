<?php

declare(strict_types=1);

namespace Kani\Nemesis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

/**
 * Model representing an authentication token for multi-model authentication.
 *
 * Supports token-based authentication with features like:
 * - Expiration management
 * - Ability-based permissions (RBAC)
 * - Origin/CORS restrictions
 * - Metadata storage for additional token data
 * - Multi-source token management (web, mobile, API, etc.)
 */
class NemesisToken extends Model
{
    protected $table = 'nemesis_tokens';

    protected $fillable = [
        'token',
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
    ];

    protected $hidden = ['token'];

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
     * Check if the token is still valid (not expired).
     *
     * @return bool True if valid, false otherwise
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Check if the token has a specific ability.
     *
     * @param string $ability The ability to check
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
     * @param array<int, string> $abilities Array of abilities to check
     * @return bool True if token has all abilities, false otherwise
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
     * Check if the token can be used from a specific origin.
     *
     * @param string|null $origin The origin URL to check (e.g., 'https://example.com')
     * @return bool True if origin is allowed, false otherwise
     */
    public function canUseFromOrigin(?string $origin): bool
    {
        // If no origin provided, allow by default (non-browser requests like API calls)
        if ($origin === null) {
            return true;
        }

        // If allowed_origins is null or empty array, allow all origins
        if ($this->allowed_origins === null || empty($this->allowed_origins)) {
            return true;
        }

        // Normalize the origin for comparison (remove trailing slashes)
        $normalizedOrigin = rtrim($origin, '/');

        foreach ($this->allowed_origins as $allowedOrigin) {
            $normalizedAllowed = rtrim($allowedOrigin, '/');

            // Support wildcard subdomains (*.example.com)
            if ($this->isWildcardMatch($normalizedOrigin, $normalizedAllowed)) {
                return true;
            }

            // Case-insensitive exact match for domains
            if (strcasecmp($normalizedOrigin, $normalizedAllowed) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the token can be used from the current request's origin.
     *
     * @param Request|null $request The HTTP request (uses current request if null)
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
     * Set a metadata value.
     *
     * @param string $key The metadata key
     * @param mixed $value The value to store
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
     * @param int $minutes Number of minutes in the past to set expiration
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
     * @param string $origin The origin to allow (e.g., 'https://example.com')
     * @return self Returns the token instance for method chaining
     */
    public function addAllowedOrigin(string $origin): self
    {
        $origins = $this->allowed_origins ?? [];

        if (!in_array($origin, $origins)) {
            $origins[] = $origin;
            $this->update(['allowed_origins' => $origins]);
        }

        return $this;
    }

    /**
     * Remove an allowed origin from the token.
     *
     * @param string $origin The origin to remove
     * @return self Returns the token instance for method chaining
     */
    public function removeAllowedOrigin(string $origin): self
    {
        $origins = $this->allowed_origins ?? [];

        $key = array_search($origin, $origins);
        if ($key !== false) {
            unset($origins[$key]);
            $this->update(['allowed_origins' => array_values($origins)]);
        }

        return $this;
    }

    /**
     * Set the allowed origins (replaces all existing origins).
     *
     * @param array<int, string>|null $origins Array of allowed origins or null to allow all
     * @return self Returns the token instance for method chaining
     */
    public function setAllowedOrigins(?array $origins): self
    {
        $this->update(['allowed_origins' => $origins]);

        return $this;
    }

    /**
     * Check if a wildcard pattern matches the origin.
     *
     * Supports patterns like:
     * - *.example.com matches subdomain.example.com
     * - https://*.example.com matches https://subdomain.example.com
     *
     * @param string $origin The actual origin
     * @param string $pattern The pattern to match against
     * @return bool True if pattern matches, false otherwise
     */
    private function isWildcardMatch(string $origin, string $pattern): bool
    {
        // Check if pattern contains wildcard
        if (strpos($pattern, '*') === false) {
            return false;
        }

        // Escape regex special characters except the wildcard
        $escapedPattern = preg_quote($pattern, '/');

        // Replace the escaped wildcard (\*) with .* to match any characters
        $regexPattern = str_replace('\\*', '.*', $escapedPattern);

        // Add case-insensitive flag
        $regex = '/^' . $regexPattern . '$/i';

        return preg_match($regex, $origin) === 1;
    }
}
