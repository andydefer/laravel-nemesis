<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\PhpVo\Enums\HttpStatusCode;

/**
 * Response data for error responses.
 *
 * Contains error message, HTTP status code, error code,
 * and optional additional details.
 */
final class ErrorResponseData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly HttpStatusCode $status,
        public readonly string $errorCode,
        public readonly ?StrictAssociative $errors = null,
    ) {}
}
