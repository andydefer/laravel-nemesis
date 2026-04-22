<?php

declare(strict_types=1);

namespace Kani\Nemesis\Enums;

/**
 * Error codes for the Nemesis authentication system.
 *
 * Defines all possible error responses with their corresponding
 * HTTP status codes and user-friendly messages.
 */
enum ErrorCode: string
{
    case MISSING_TOKEN = 'MISSING_TOKEN';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS';
    case ORIGIN_NOT_ALLOWED = 'ORIGIN_NOT_ALLOWED';

    /**
     * Get the HTTP status code for this error.
     *
     * @return int The HTTP status code
     */
    public function httpStatusCode(): int
    {
        return match ($this) {
            self::MISSING_TOKEN => 401,
            self::INVALID_TOKEN => 401,
            self::TOKEN_EXPIRED => 401,
            self::INSUFFICIENT_PERMISSIONS => 403,
            self::ORIGIN_NOT_ALLOWED => 403,
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
            self::MISSING_TOKEN => 'Token not provided',
            self::INVALID_TOKEN => 'Invalid token',
            self::TOKEN_EXPIRED => 'Token has expired',
            self::INSUFFICIENT_PERMISSIONS => 'Insufficient permissions',
            self::ORIGIN_NOT_ALLOWED => 'This origin is not allowed',
        };
    }
}
