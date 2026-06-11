<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Middleware configuration record.
 */
final class MiddlewareConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $parameter_name,
        public readonly string $token_header,
        public readonly bool $security_headers,
        public readonly bool $validate_origin,
    ) {}
}
