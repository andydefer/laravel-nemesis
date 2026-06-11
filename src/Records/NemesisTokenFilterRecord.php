<?php

// src/Records/NemesisTokenFilterRecord.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class NemesisTokenFilterRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?string $token_hash = null,
        public readonly ?string $tokenable_type = null,
        public readonly ?int $tokenable_id = null,
        public readonly ?string $name = null,
        public readonly ?string $source = null,
        public readonly ?StringTypedCollection $abilities = null,
        public readonly ?string $origin = null,
        public readonly ?bool $is_expired = null,
        public readonly ?bool $is_revoked = null,
        public readonly ?DateTimeVO $created_before = null,
    ) {}
}
