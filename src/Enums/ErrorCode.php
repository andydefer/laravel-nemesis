<?php

declare(strict_types=1);

namespace Kani\Nemesis\Enums;

/**
 * Error codes for the Nemesis authentication system.
 *
 * Defines all possible error responses with their corresponding
 * HTTP status codes and user-friendly messages. This enum ensures
 * consistent error handling across the entire package.
 *
 * @package Kani\Nemesis\Enums
 */
enum ErrorCode: string
{
    // ============================================================================
    // Authentication Errors (HTTP 401)
    // ============================================================================

    /**
     * No token was provided in the request.
     */
    case MISSING_TOKEN = 'MISSING_TOKEN';

    /**
     * The provided token is invalid (not found in database).
     */
    case INVALID_TOKEN = 'INVALID_TOKEN';

    /**
     * The token has expired and is no longer valid.
     */
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';

    // ============================================================================
    // Authorization Errors (HTTP 403)
    // ============================================================================

    /**
     * The token lacks the required ability/permission.
     */
    case INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS';

    /**
     * The request origin is not allowed for this token.
     */
    case ORIGIN_NOT_ALLOWED = 'ORIGIN_NOT_ALLOWED';

    // ============================================================================
    // Server Configuration Errors (HTTP 500)
    // ============================================================================

    /**
     * The authenticatable model does not implement the required interface.
     */
    case INVALID_AUTHENTICATABLE_MODEL = 'INVALID_AUTHENTICATABLE_MODEL';

    // ============================================================================
    // Metadata Validation Errors (HTTP 400)
    // ============================================================================

    /**
     * Metadata size exceeds the maximum allowed (64KB).
     */
    case METADATA_SIZE_EXCEEDED = 'METADATA_SIZE_EXCEEDED';

    /**
     * Metadata nesting depth exceeds the maximum allowed (5 levels).
     */
    case METADATA_NESTING_TOO_DEEP = 'METADATA_NESTING_TOO_DEEP';

    /**
     * Metadata contains too many keys (max 100).
     */
    case METADATA_TOO_MANY_KEYS = 'METADATA_TOO_MANY_KEYS';

    /**
     * Metadata key type is invalid (must be string or int).
     */
    case METADATA_INVALID_KEY = 'METADATA_INVALID_KEY';

    /**
     * Metadata value type is invalid (must be scalar, array, or null).
     */
    case METADATA_INVALID_VALUE = 'METADATA_INVALID_VALUE';

    /**
     * Metadata key exceeds the maximum length (255 characters).
     */
    case METADATA_KEY_TOO_LONG = 'METADATA_KEY_TOO_LONG';

    // ============================================================================
    // Methods
    // ============================================================================

    /**
     * Get the HTTP status code for this error.
     *
     * @return int The HTTP status code
     */
    public function httpStatusCode(): int
    {
        return match ($this) {
            // Authentication errors
            self::MISSING_TOKEN,
            self::INVALID_TOKEN,
            self::TOKEN_EXPIRED => 401,

            // Authorization errors
            self::INSUFFICIENT_PERMISSIONS,
            self::ORIGIN_NOT_ALLOWED => 403,

            // Server configuration error
            self::INVALID_AUTHENTICATABLE_MODEL => 500,

            // Metadata validation errors
            self::METADATA_SIZE_EXCEEDED,
            self::METADATA_NESTING_TOO_DEEP,
            self::METADATA_TOO_MANY_KEYS,
            self::METADATA_INVALID_KEY,
            self::METADATA_INVALID_VALUE,
            self::METADATA_KEY_TOO_LONG => 400,
        };
    }

    /**
     * Get the user-friendly error message.
     *
     * @return string The error message
     */
    public function message(): string
    {
        return match ($this) {
            // Authentication errors
            self::MISSING_TOKEN => 'Token not provided',
            self::INVALID_TOKEN => 'Invalid token',
            self::TOKEN_EXPIRED => 'Token has expired',

            // Authorization errors
            self::INSUFFICIENT_PERMISSIONS => 'Insufficient permissions',
            self::ORIGIN_NOT_ALLOWED => 'This origin is not allowed',

            // Server configuration error
            self::INVALID_AUTHENTICATABLE_MODEL => 'Authenticatable model is invalid or misconfigured',

            // Metadata validation errors
            self::METADATA_SIZE_EXCEEDED => 'Metadata size exceeds maximum allowed (64KB)',
            self::METADATA_NESTING_TOO_DEEP => 'Metadata nesting depth exceeds maximum allowed (5 levels)',
            self::METADATA_TOO_MANY_KEYS => 'Metadata contains too many keys (max 100)',
            self::METADATA_INVALID_KEY => 'Metadata key must be a string or integer',
            self::METADATA_INVALID_VALUE => 'Metadata value must be a scalar, array, or null',
            self::METADATA_KEY_TOO_LONG => 'Metadata key exceeds maximum length (255 characters)',
        };
    }

    /**
     * Check if the error is an authentication error (HTTP 401).
     *
     * @return bool True if authentication error, false otherwise
     */
    public function isAuthenticationError(): bool
    {
        return in_array($this->httpStatusCode(), [401], true);
    }

    /**
     * Check if the error is an authorization error (HTTP 403).
     *
     * @return bool True if authorization error, false otherwise
     */
    public function isAuthorizationError(): bool
    {
        return in_array($this->httpStatusCode(), [403], true);
    }

    /**
     * Check if the error is a client error (HTTP 400).
     *
     * @return bool True if client error, false otherwise
     */
    public function isClientError(): bool
    {
        return $this->httpStatusCode() === 400;
    }

    /**
     * Check if the error is a server error (HTTP 500).
     *
     * @return bool True if server error, false otherwise
     */
    public function isServerError(): bool
    {
        return $this->httpStatusCode() === 500;
    }

    /**
     * Get the error category as a string.
     *
     * @return string The error category (auth, authorization, client, server)
     */
    public function getCategory(): string
    {
        return match ($this->httpStatusCode()) {
            401 => 'authentication',
            403 => 'authorization',
            400 => 'client',
            500 => 'server',
            default => 'unknown',
        };
    }

    /**
     * Check if the error is recoverable by the client.
     *
     * @return bool True if the error is recoverable
     */
    public function isRecoverable(): bool
    {
        return match ($this->httpStatusCode()) {
            401, 403 => true,  // Client can retry with different credentials
            400 => true,       // Client can fix the request
            500 => false,      // Server error, not recoverable by client
            default => false,
        };
    }
}
