<?php

// src/Records/CleanupStatisticsRecord.php

declare(strict_types=1);

namespace Kani\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class CleanupStatisticsRecord extends AbstractRecord
{
    public function __construct(
        public readonly int $expired,
        public readonly int $old,
        public readonly int $total,
    ) {}
}
