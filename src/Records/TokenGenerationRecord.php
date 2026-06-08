<?php

declare(strict_types=1);

namespace Kani\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Token generation configuration record.
 */
final class TokenGenerationRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $length,
        public readonly string $hash_algorithm,
        public readonly ?int $expiration_minutes,
    ) {}
}
