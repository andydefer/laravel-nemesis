<?php

declare(strict_types=1);

namespace Kani\Nemesis\Data;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpVo\Enums\HttpStatusCode;
use Kani\Nemesis\Enums\ErrorCode;

/**
 * Data Transfer Object for standardized error responses.
 *
 * Provides a consistent structure for all error responses returned by the
 * Nemesis authentication system, ensuring predictable JSON formatting
 * and proper HTTP status codes.
 */
final class ErrorResponseData extends AbstractData
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly string $message,
        public readonly HttpStatusCode $status,
        public readonly ?StrictDataObject $details = null,
    ) {}
}
