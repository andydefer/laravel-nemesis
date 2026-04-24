<?php

declare(strict_types=1);

namespace Kani\Nemesis\Exceptions;

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
 *
 * @package Kani\Nemesis\Exceptions
 */
final class MetadataValidationException extends InvalidArgumentException
{
    /**
     * The error code enum.
     *
     * @var ErrorCode
     */
    private readonly ErrorCode $errorCode;

    /**
     * Additional error details providing context about the validation failure.
     *
     * @var array<string, mixed>|null
     */
    private readonly ?array $details;

    /**
     * Constructor.
     *
     * @param ErrorCode $errorCode The error code enum
     * @param string $message Human-readable error message
     * @param array<string, mixed>|null $details Additional error details (key, length, limit, etc.)
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        ErrorCode $errorCode,
        string $message,
        ?array $details = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    /**
     * Get the error code enum.
     *
     * @return ErrorCode The error code enum
     */
    public function getErrorCode(): ErrorCode
    {
        return $this->errorCode;
    }

    /**
     * Get the error code as a string.
     *
     * @return string The error code string value
     */
    public function getErrorCodeString(): string
    {
        return $this->errorCode->value;
    }

    /**
     * Get the HTTP status code for this exception.
     *
     * @return int The HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->errorCode->httpStatusCode();
    }

    /**
     * Get additional error details.
     *
     * @return array<string, mixed>|null The error details or null if none
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * Check if the exception has additional details.
     *
     * @return bool True if details exist, false otherwise
     */
    public function hasDetails(): bool
    {
        return $this->details !== null && !empty($this->details);
    }

    /**
     * Convert the exception to an ErrorResponseData DTO.
     *
     * @return ErrorResponseData The error response DTO
     */
    public function toErrorResponse(): ErrorResponseData
    {
        return ErrorResponseData::fromErrorCode(
            errorCode: $this->errorCode,
            details: $this->details
        );
    }

    /**
     * Convert the exception to a JSON response array.
     *
     * @return array<string, mixed> The JSON-serializable error array
     */
    public function toArray(): array
    {
        $data = [
            'errorCode' => $this->errorCode->value,
            'message' => $this->getMessage(),
            'status' => $this->getHttpStatusCode(),
        ];

        if ($this->hasDetails()) {
            $data['details'] = $this->details;
        }

        return $data;
    }

    /**
     * Convert the exception to a JSON string.
     *
     * @return string The JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Create an exception for metadata size exceeded.
     *
     * @param int $size The attempted size in bytes
     * @param int $maxSize The maximum allowed size in bytes
     * @return self The exception instance
     */
    public static function sizeExceeded(int $size, int $maxSize): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_SIZE_EXCEEDED,
            message: sprintf('Metadata size (%d bytes) exceeds maximum allowed (%d bytes)', $size, $maxSize),
            details: [
                'size' => $size,
                'max_size' => $maxSize,
                'size_mb' => round($size / 1024, 2),
                'max_mb' => round($maxSize / 1024, 2),
            ]
        );
    }

    /**
     * Create an exception for nesting depth exceeded.
     *
     * @param int $depth The attempted depth
     * @param int $maxDepth The maximum allowed depth
     * @return self The exception instance
     */
    public static function nestingTooDeep(int $depth, int $maxDepth): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_NESTING_TOO_DEEP,
            message: sprintf('Metadata nesting depth (%d) exceeds maximum allowed (%d)', $depth, $maxDepth),
            details: [
                'current_depth' => $depth,
                'max_depth' => $maxDepth,
            ]
        );
    }

    /**
     * Create an exception for too many keys.
     *
     * @param int $keyCount The number of keys attempted
     * @param int $maxKeys The maximum allowed number of keys
     * @return self The exception instance
     */
    public static function tooManyKeys(int $keyCount, int $maxKeys): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_TOO_MANY_KEYS,
            message: sprintf('Metadata contains %d keys, maximum allowed is %d', $keyCount, $maxKeys),
            details: [
                'key_count' => $keyCount,
                'max_keys' => $maxKeys,
            ]
        );
    }

    /**
     * Create an exception for invalid key type.
     *
     * @param string $keyType The type of key attempted
     * @return self The exception instance
     */
    public static function invalidKeyType(string $keyType): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_INVALID_KEY,
            message: sprintf('Metadata key must be string or int, %s given', $keyType),
            details: [
                'key_type' => $keyType,
                'allowed_types' => ['string', 'int'],
            ]
        );
    }

    /**
     * Create an exception for key too long.
     *
     * @param string $key The key that was too long (truncated for display)
     * @param int $length The length of the key
     * @param int $maxLength The maximum allowed length
     * @return self The exception instance
     */
    public static function keyTooLong(string $key, int $length, int $maxLength): self
    {
        return new self(
            errorCode: ErrorCode::METADATA_KEY_TOO_LONG,
            message: sprintf('Metadata key exceeds maximum length of %d characters. Got %d characters.', $maxLength, $length),
            details: [
                'key' => substr($key, 0, 50),
                'length' => $length,
                'max_length' => $maxLength,
            ]
        );
    }

    /**
     * Create an exception for invalid value type.
     *
     * @param string|null $key The key name (optional)
     * @param string $valueType The type of value attempted
     * @return self The exception instance
     */
    public static function invalidValueType(?string $key, string $valueType): self
    {
        $context = $key !== null ? sprintf(' for key "%s"', $key) : '';

        return new self(
            errorCode: ErrorCode::METADATA_INVALID_VALUE,
            message: sprintf('Metadata value%s must be scalar, array, or null, %s given', $context, $valueType),
            details: [
                'key' => $key,
                'value_type' => $valueType,
                'allowed_types' => ['scalar', 'array', 'null'],
            ]
        );
    }
}
