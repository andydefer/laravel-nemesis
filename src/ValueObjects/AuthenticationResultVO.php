<?php

declare(strict_types=1);

namespace Kani\Nemesis\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use InvalidArgumentException;
use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Records\AuthenticationResultRecord;
use Kani\Nemesis\Records\NemesisTokenRecord;

/**
 * Result of an authentication attempt.
 * 
 * Pure Value Object - immutable, self-validating, no identity.
 * 
 * States:
 * - Success: success = true, error_code = null, token_record provided
 * - Failure: success = false, error_code provided, token_record = null
 */
final class AuthenticationResultVO extends AbstractValueObject
{
    public function __construct(
        public readonly bool $success,
        public readonly ?ErrorCode $error_code,
        public readonly ?NemesisTokenRecord $token_record,
        public readonly ?StrictDataObject $additional_data,
    ) {
        $this->validate();
    }

    /**
     * Validate the internal consistency of the result.
     */
    private function validate(): void
    {
        if ($this->success) {
            // Success requires token record
            if ($this->token_record === null) {
                throw new InvalidArgumentException('Token record is required for successful authentication');
            }
            if ($this->error_code !== null) {
                throw new InvalidArgumentException('Error code must be null for successful authentication');
            }
        } else {
            // Failure requires error code
            if ($this->error_code === null) {
                throw new InvalidArgumentException('Error code is required for failed authentication');
            }
            if ($this->token_record !== null) {
                throw new InvalidArgumentException('Token record must be null for failed authentication');
            }
        }
    }

    /**
     * Convert the Value Object to a Record.
     */
    public function getValue(): AuthenticationResultRecord
    {
        return AuthenticationResultRecord::from([
            'success' => $this->success,
            'error_code' => $this->error_code,
            'token_record' => $this->token_record,
            'additional_data' => $this->additional_data,
        ]);
    }

    /**
     * Check if the result represents a successful authentication.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the result represents a failed authentication.
     */
    public function isFailure(): bool
    {
        return !$this->success;
    }

    /**
     * Get the error code (null if success).
     */
    public function getErrorCode(): ?ErrorCode
    {
        return $this->error_code;
    }

    /**
     * Get the token record (null if failure).
     */
    public function getTokenRecord(): ?NemesisTokenRecord
    {
        return $this->token_record;
    }

    /**
     * Get additional data (null if success or no data).
     */
    public function getAdditionalData(): ?StrictDataObject
    {
        return $this->additional_data;
    }
}
