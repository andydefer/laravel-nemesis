<?php

// src/Records/NemesisTokenRecord.php

declare(strict_types=1);

namespace Kani\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

final class NemesisTokenRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $tokenable_type = null,
        public readonly ?int $tokenable_id = null,
        public readonly ?string $token_hash = null,
        public readonly ?string $name = null,
        public readonly ?string $source = null,
        public readonly ?StringTypedCollection $abilities = null,
        public readonly ?StrictDataObject $metadata = null,
        public readonly ?StringTypedCollection $allowed_origins = null,
        public readonly ?DateTimeVO $last_used_at = null,
        public readonly ?DateTimeVO $expires_at = null,
        public readonly ?DateTimeVO $created_at = null,
        public readonly ?DateTimeVO $updated_at = null,
        public readonly ?DateTimeVO $deleted_at = null,
    ) {}
}
