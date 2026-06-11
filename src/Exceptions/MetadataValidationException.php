<?php

declare(strict_types=1);

namespace Kani\Nemesis\Exceptions;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\PhpVo\Enums\HttpStatusCode;
use InvalidArgumentException;
use Kani\Nemesis\Data\ErrorResponseData;
use Kani\Nemesis\Enums\ErrorCode;
use Throwable;

/**
 * Exception thrown when token metadata validation fails.
 *
 * This exception is used throughout the metadata management system to indicate
 * that metadata does not meet security or format requirements. It carries an
 * ErrorCode enum and additional details for consistent API error responses.
 */
final class MetadataValidationException extends InvalidArgumentException
{
    private readonly ErrorCode $errorCode;

    private readonly ?StrictDataObject $details;

    /**
     * Constructor.
     *
     * @param  ErrorCode  $errorCode  The error code enum
     * @param  string  $message  Human-readable error message
     * @param  StrictDataObject|null  $details  Additional error details
     * @param  Throwable|null  $previous  Previous exception for chaining
     */
    public function __construct(
        ErrorCode $errorCode,
        string $message,
        ?StrictDataObject $details = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    /**
     * Get the error code enum.
     */
    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    /**
     * Get the error code as a string.
     */
    public function getErrorCodeString(): string
    {
        return $this->errorCode->value;
    }

    /**
     * Get additional error details.
     */
    public function getDetails(): ?StrictDataObject
    {
        return $this->details;
    }

    /**
     * Check if the exception has additional details.
     */
    public function hasDetails(): bool
    {
        return $this->details !== null;
    }

    /**
     * Convert the exception to an ErrorResponseData DTO.
     */
    public function toErrorResponse(): ErrorResponseData
    {
        return new ErrorResponseData(
            errorCode: $this->errorCode,
            message: $this->getMessage(),
            status: $this->getHttpStatusCode(),
            details: $this->details,
        );
    }

    /**
     * Get the HTTP status code for this exception.
     */
    private function getHttpStatusCode(): HttpStatusCode
    {
        return match ($this->errorCode) {
            ErrorCode::METADATA_SIZE_EXCEEDED,
            ErrorCode::METADATA_NESTING_TOO_DEEP,
            ErrorCode::METADATA_TOO_MANY_KEYS,
            ErrorCode::METADATA_INVALID_KEY,
            ErrorCode::METADATA_INVALID_VALUE,
            ErrorCode::METADATA_KEY_TOO_LONG => HttpStatusCode::BAD_REQUEST,
            default => HttpStatusCode::INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * Convert the exception to a JSON response array.
     */
    public function toArray(): array
    {
        $data = [
            'errorCode' => $this->errorCode->value,
            'message' => $this->getMessage(),
            'status' => $this->getHttpStatusCode()->value,
        ];

        if ($this->hasDetails()) {
            $data['details'] = $this->details->toArray();
        }

        return $data;
    }

    /**
     * Convert the exception to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Create an exception for metadata size exceeded.
     */
    public static function sizeExceeded(int $size, int $maxSize): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_SIZE_EXCEEDED,
            message: sprintf('Metadata size (%d bytes) exceeds maximum allowed (%d bytes)', $size, $maxSize),
            details: new StrictDataObject([
                'size' => $size,
                'max_size' => $maxSize,
                'size_mb' => round($size / 1024 / 1024, 2),
                'max_mb' => round($maxSize / 1024 / 1024, 2),
            ])
        );
    }

    /**
     * Create an exception for nesting depth exceeded.
     */
    public static function nestingTooDeep(int $depth, int $maxDepth): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_NESTING_TOO_DEEP,
            message: sprintf('Metadata nesting depth (%d) exceeds maximum allowed (%d)', $depth, $maxDepth),
            details: new StrictDataObject([
                'current_depth' => $depth,
                'max_depth' => $maxDepth,
            ])
        );
    }

    /**
     * Create an exception for too many keys.
     */
    public static function tooManyKeys(int $keyCount, int $maxKeys): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_TOO_MANY_KEYS,
            message: sprintf('Metadata contains %d keys, maximum allowed is %d', $keyCount, $maxKeys),
            details: new StrictDataObject([
                'key_count' => $keyCount,
                'max_keys' => $maxKeys,
            ])
        );
    }

    /**
     * Create an exception for invalid key type.
     */
    public static function invalidKeyType(string $keyType): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_INVALID_KEY,
            message: sprintf('Metadata key must be string or int, %s given', $keyType),
            details: new StrictDataObject([
                'key_type' => $keyType,
                'allowed_types' => ['string', 'int'],
            ])
        );
    }

    /**
     * Create an exception for key too long.
     */
    public static function keyTooLong(string $key, int $length, int $maxLength): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_KEY_TOO_LONG,
            message: sprintf('Metadata key exceeds maximum length of %d characters. Got %d characters.', $maxLength, $length),
            details: new StrictDataObject([
                'key' => substr($key, 0, 50),
                'length' => $length,
                'max_length' => $maxLength,
            ])
        );
    }

    /**
     * Create an exception for invalid value type.
     */
    public static function invalidValueType(?string $key, string $valueType): self
    {
        $context = $key !== null ? sprintf(' for key "%s"', $key) : '';

        return new self(
            errorCode: ErrorCode::METADATA_INVALID_VALUE,
            message: sprintf('Metadata value%s must be scalar, array, or null, %s given', $context, $valueType),
            details: new StrictDataObject([
                'key' => $key,
                'value_type' => $valueType,
                'allowed_types' => ['scalar', 'array', 'null'],
            ])
        );
    }
}
