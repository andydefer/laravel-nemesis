<?php

declare(strict_types=1);

namespace Kani\Nemesis\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use Kani\Nemesis\Enums\ErrorCode;

/**
 * Record representing the authentication result data.
 * 
 * @package Kani\Nemesis\Records
 */
final class AuthenticationResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $success,
        public readonly ?ErrorCode $error_code,
        public readonly ?NemesisTokenRecord $token_record,
        public readonly ?StrictDataObject $additional_data,
    ) {}
}
