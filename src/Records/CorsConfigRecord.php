<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * CORS configuration record.
 */
final class CorsConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $allow_credentials,
        public readonly int $max_age,
        public readonly bool $expose_token_info,
    ) {}
}
