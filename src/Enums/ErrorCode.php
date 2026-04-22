<?php
// src/Enums/ErrorCode.php

declare(strict_types=1);

namespace Kani\Nemesis\Enums;

enum ErrorCode: string
{
    case MISSING_TOKEN = 'MISSING_TOKEN';
    case INVALID_TOKEN = 'INVALID_TOKEN';
    case TOKEN_EXPIRED = 'TOKEN_EXPIRED';
    case INSUFFICIENT_PERMISSIONS = 'INSUFFICIENT_PERMISSIONS';

    public function httpStatusCode(): int
    {
        return match ($this) {
            self::MISSING_TOKEN => 401,
            self::INVALID_TOKEN => 401,
            self::TOKEN_EXPIRED => 401,
            self::INSUFFICIENT_PERMISSIONS => 403,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::MISSING_TOKEN => 'Token not provided',
            self::INVALID_TOKEN => 'Invalid token',
            self::TOKEN_EXPIRED => 'Token has expired',
            self::INSUFFICIENT_PERMISSIONS => 'Insufficient permissions',
        };
    }
}
