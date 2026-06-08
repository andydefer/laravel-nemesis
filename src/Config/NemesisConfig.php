<?php

declare(strict_types=1);

namespace Kani\Nemesis\Config;

use AndyDefer\DomainStructures\Abstracts\AbstractConfig;
use Kani\Nemesis\Records\CleanupConfigRecord;
use Kani\Nemesis\Records\CorsConfigRecord;
use Kani\Nemesis\Records\MiddlewareConfigRecord;
use Kani\Nemesis\Records\TokenGenerationRecord;

/**
 * Configuration for the Nemesis token authentication system.
 *
 * This immutable configuration class provides all settings for:
 * - Token generation (length, hash algorithm, expiration)
 * - Middleware behavior (parameter names, headers, security)
 * - CORS validation and headers
 * - Automatic cleanup of expired tokens
 *
 * All values are hardcoded or loaded from environment variables.
 * No properties, no state, only methods.
 */
final class NemesisConfig extends AbstractConfig
{
    // ============================================================================
    // Token Generation Configuration
    // ============================================================================

    /**
     * Length of generated tokens in bytes.
     * Longer tokens are more secure but larger in size.
     * Recommended: 64 or higher for production.
     */
    public function getTokenLength(): int
    {
        return (int) (getenv('NEMESIS_TOKEN_LENGTH') ?: 64);
    }

    /**
     * Hash algorithm used to hash tokens before storage.
     * Supported: 'sha256', 'sha512', 'md5' (not recommended).
     * Recommended: 'sha256' or 'sha512' for production.
     */
    public function getHashAlgorithm(): string
    {
        $algorithm = getenv('NEMESIS_HASH_ALGORITHM') ?: 'sha256';

        return in_array($algorithm, hash_algos(), true) ? $algorithm : 'sha256';
    }

    /**
     * Token expiration time in minutes.
     * Set to null for tokens that never expire (not recommended for production).
     * Default: 60 minutes (1 hour)
     */
    public function getExpiration(): ?int
    {
        $expiration = getenv('NEMESIS_EXPIRATION');

        if ($expiration === false || $expiration === '' || $expiration === 'null') {
            return null;
        }

        $value = (int) $expiration;

        return $value > 0 ? $value : 60;
    }

    // ============================================================================
    // Middleware Configuration
    // ============================================================================

    /**
     * Parameter name used to inject the authenticated model into the route.
     * Access via: $request->nemesisAuth
     */
    public function getParameterName(): string
    {
        return getenv('NEMESIS_PARAMETER_NAME') ?: 'nemesisAuth';
    }

    /**
     * Header name that contains the bearer token.
     * Standard is 'Authorization' with 'Bearer ' prefix.
     */
    public function getTokenHeader(): string
    {
        return getenv('NEMESIS_TOKEN_HEADER') ?: 'Authorization';
    }

    /**
     * Enable security headers on successful responses.
     * Adds X-Frame-Options, X-XSS-Protection, X-Content-Type-Options, Referrer-Policy.
     */
    public function getSecurityHeaders(): bool
    {
        $value = getenv('NEMESIS_SECURITY_HEADERS');

        if ($value === false) {
            return true;
        }

        return $value === 'true';
    }

    /**
     * Enable CORS origin validation.
     * When enabled, tokens can restrict which origins can use them.
     */
    public function getValidateOrigin(): bool
    {
        $value = getenv('NEMESIS_VALIDATE_ORIGIN');

        if ($value === false) {
            return true;
        }

        return $value === 'true';
    }

    /**
     * Check if using a custom header instead of standard Bearer token.
     */
    public function isUsingCustomHeader(): bool
    {
        $header = $this->getTokenHeader();

        return $header !== 'Authorization';
    }

    // ============================================================================
    // CORS Configuration
    // ============================================================================

    /**
     * Whether to allow credentials (cookies, authorization headers) in CORS requests.
     */
    public function getAllowCredentials(): bool
    {
        $value = getenv('NEMESIS_CORS_ALLOW_CREDENTIALS');

        if ($value === false) {
            return true;
        }

        return $value === 'true';
    }

    /**
     * Maximum age (in seconds) for preflight requests caching.
     * Default: 86400 (24 hours)
     */
    public function getMaxAge(): int
    {
        return (int) (getenv('NEMESIS_CORS_MAX_AGE') ?: 86400);
    }

    /**
     * Whether to expose token information in CORS responses.
     * When true, adds 'X-Token-Expires-At' and 'X-Token-Abilities' headers.
     */
    public function getExposeTokenInfo(): bool
    {
        $value = getenv('NEMESIS_CORS_EXPOSE_TOKEN_INFO');

        return $value === 'true';
    }

    // ============================================================================
    // Cleanup Configuration
    // ============================================================================

    /**
     * Whether to automatically clean expired tokens.
     * Recommended: true for production to keep database size manageable.
     */
    public function getAutoCleanup(): bool
    {
        $value = getenv('NEMESIS_AUTO_CLEANUP');

        if ($value === false) {
            return true;
        }

        return $value === 'true';
    }

    /**
     * Frequency of cleanup in minutes.
     * Uses Laravel's scheduling system.
     * Default: 60 minutes (run every hour)
     */
    public function getCleanupFrequency(): int
    {
        return (int) (getenv('NEMESIS_CLEANUP_FREQUENCY') ?: 60);
    }

    /**
     * Delete tokens older than this many days after expiration.
     * Keep for audit purposes before permanent deletion.
     * Default: 30 days
     */
    public function getKeepExpiredForDays(): int
    {
        return (int) (getenv('NEMESIS_KEEP_EXPIRED_DAYS') ?: 30);
    }

    // ============================================================================
    // Helper Methods - Questions
    // ============================================================================

    /**
     * Check if tokens should expire (has expiration set).
     */
    public function shouldExpire(): bool
    {
        return $this->getExpiration() !== null;
    }

    /**
     * Check if cleanup is enabled and has valid configuration.
     */
    public function shouldCleanup(): bool
    {
        return $this->getAutoCleanup() && $this->getCleanupFrequency() > 0;
    }

    /**
     * Check if CORS is enabled (origin validation active).
     */
    public function isCorsEnabled(): bool
    {
        return $this->getValidateOrigin();
    }

    // ============================================================================
    // Record Getters - Using ::from() for automatic hydration
    // ============================================================================

    /**
     * Get token generation configuration as a Record.
     * Uses ::from() for automatic hydration.
     */
    public function getTokenGenerationRecord(): TokenGenerationRecord
    {
        return TokenGenerationRecord::from([
            'length' => $this->getTokenLength(),
            'hash_algorithm' => $this->getHashAlgorithm(),
            'expiration_minutes' => $this->getExpiration(),
        ]);
    }

    /**
     * Get middleware configuration as a Record.
     * Uses ::from() for automatic hydration.
     */
    public function getMiddlewareConfigRecord(): MiddlewareConfigRecord
    {
        return MiddlewareConfigRecord::from([
            'parameter_name' => $this->getParameterName(),
            'token_header' => $this->getTokenHeader(),
            'security_headers' => $this->getSecurityHeaders(),
            'validate_origin' => $this->getValidateOrigin(),
        ]);
    }

    /**
     * Get CORS configuration as a Record.
     * Uses ::from() for automatic hydration.
     */
    public function getCorsConfigRecord(): CorsConfigRecord
    {
        return CorsConfigRecord::from([
            'allow_credentials' => $this->getAllowCredentials(),
            'max_age' => $this->getMaxAge(),
            'expose_token_info' => $this->getExposeTokenInfo(),
        ]);
    }

    /**
     * Get cleanup configuration as a Record.
     * Uses ::from() for automatic hydration.
     */
    public function getCleanupConfigRecord(): CleanupConfigRecord
    {
        return CleanupConfigRecord::from([
            'auto_cleanup' => $this->getAutoCleanup(),
            'frequency' => $this->getCleanupFrequency(),
            'keep_expired_for_days' => $this->getKeepExpiredForDays(),
        ]);
    }
}
