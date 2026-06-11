<?php
// src/Records/TokenConfigRecord.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class TokenConfigRecord extends AbstractRecord
{

    public function __construct(
        public readonly int $token_length,
        public readonly string $hash_algorithm,
        public readonly ?int $expiration_minutes,
    ) {}
}
