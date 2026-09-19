<?php

// src/Enums/ErrorCode.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Enums;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Contracts\ErrorDescribable;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\PhpVo\Enums\HttpStatusCode;

/**
 * Error codes for the Nemesis authentication system.
 *
 * Defines all possible error responses with their corresponding
 * HTTP status codes and user-friendly messages. This enum ensures
 * consistent error handling across the entire package.
 */
enum ErrorCode: string implements ErrorDescribable
{
    // ============================================================================
    // Authentication Errors (HTTP 401)
    // ============================================================================

    case MISSING_TOKEN = 'MISSING_TOKEN';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case AUTHENTICATABLE_NOT_FOUND = 'AUTHENTICATABLE_NOT_FOUND';

    // ============================================================================
    // Authorization Errors (HTTP 403)
    // ============================================================================

    case INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS';
    case ORIGIN_NOT_ALLOWED = 'ORIGIN_NOT_ALLOWED';
    case EMAIL_NOT_VERIFIED = 'EMAIL_NOT_VERIFIED';

    // ============================================================================
    // Client Errors (HTTP 400)
    // ============================================================================

    case ALREADY_AUTHENTICATED = 'ALREADY_AUTHENTICATED';

    // ============================================================================
    // Server Configuration Errors (HTTP 500)
    // ============================================================================

    case INVALID_AUTHENTICATABLE_MODEL = 'INVALID_AUTHENTICATABLE_MODEL';
    case MODEL_MISSING_EMAIL_VERIFIED_AT = 'MODEL_MISSING_EMAIL_VERIFIED_AT';

    // ============================================================================
    // Metadata Validation Errors (HTTP 400)
    // ============================================================================

    case METADATA_SIZE_EXCEEDED = 'METADATA_SIZE_EXCEEDED';
    case METADATA_NESTING_TOO_DEEP = 'METADATA_NESTING_TOO_DEEP';
    case METADATA_TOO_MANY_KEYS = 'METADATA_TOO_MANY_KEYS';
    case METADATA_INVALID_KEY = 'METADATA_INVALID_KEY';
    case METADATA_INVALID_VALUE = 'METADATA_INVALID_VALUE';
    case METADATA_KEY_TOO_LONG = 'METADATA_KEY_TOO_LONG';

    // ============================================================================
    // ErrorDescribable
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function getHttpStatusCode(): HttpStatusCode
    {
        return match ($this) {
            self::MISSING_TOKEN,
            self::INVALID_TOKEN,
            self::TOKEN_EXPIRED,
            self::AUTHENTICATABLE_NOT_FOUND => HttpStatusCode::UNAUTHORIZED,

            self::INSUFFICIENT_PERMISSIONS,
            self::ORIGIN_NOT_ALLOWED,
            self::EMAIL_NOT_VERIFIED => HttpStatusCode::FORBIDDEN,

            self::ALREADY_AUTHENTICATED,
            self::METADATA_SIZE_EXCEEDED,
            self::METADATA_NESTING_TOO_DEEP,
            self::METADATA_TOO_MANY_KEYS,
            self::METADATA_INVALID_KEY,
            self::METADATA_INVALID_VALUE,
            self::METADATA_KEY_TOO_LONG => HttpStatusCode::BAD_REQUEST,

            self::INVALID_AUTHENTICATABLE_MODEL,
            self::MODEL_MISSING_EMAIL_VERIFIED_AT => HttpStatusCode::INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getMessage(): string
    {
        return match ($this) {
            self::MISSING_TOKEN => 'Token not provided',
            self::INVALID_TOKEN => 'Invalid token',
            self::TOKEN_EXPIRED => 'Token has expired',
            self::AUTHENTICATABLE_NOT_FOUND => 'User not found',

            self::INSUFFICIENT_PERMISSIONS => 'Insufficient permissions',
            self::ORIGIN_NOT_ALLOWED => 'This origin is not allowed',
            self::EMAIL_NOT_VERIFIED => 'Email not verified. Please verify your email address.',

            self::ALREADY_AUTHENTICATED => 'Already authenticated',

            self::INVALID_AUTHENTICATABLE_MODEL => 'Authenticatable model is invalid or misconfigured',
            self::MODEL_MISSING_EMAIL_VERIFIED_AT => 'Model must have email_verified_at field',

            self::METADATA_SIZE_EXCEEDED => 'Metadata size exceeds maximum allowed (64KB)',
            self::METADATA_NESTING_TOO_DEEP => 'Metadata nesting depth exceeds maximum allowed (5 levels)',
            self::METADATA_TOO_MANY_KEYS => 'Metadata contains too many keys (max 100)',
            self::METADATA_INVALID_KEY => 'Metadata key must be a string or integer',
            self::METADATA_INVALID_VALUE => 'Metadata value must be a scalar, array, or null',
            self::METADATA_KEY_TOO_LONG => 'Metadata key exceeds maximum length (255 characters)',
        };
    }

    /**
     * {@inheritDoc}
     */
    public function getLabel(): string
    {
        return $this->getMessage();
    }

    /**
     * {@inheritDoc}
     */
    public function toResponseData(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ErrorResponseData {
        return ErrorResponseData::from([
            'errorCode' => $this,
            'message' => $message ?? $this->getMessage(),
            'status' => $this->getHttpStatusCode(),
            'errors' => $errors,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function toJsonResponseFactory(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ResponseFactory {
        return ResponseFactory::json(
            $this->toResponseData($message, $errors),
            $this->getHttpStatusCode(),
        );
    }
}
