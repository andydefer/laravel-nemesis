<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Cleanup configuration record.
 */
final class CleanupConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $auto_cleanup,
        public readonly int $frequency,
        public readonly int $keep_expired_for_days,
    ) {}
}
