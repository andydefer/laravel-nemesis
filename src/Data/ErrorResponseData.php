<?php

declare(strict_types=1);

namespace Kani\Nemesis\Data;

use JsonSerializable;
use Kani\Nemesis\Enums\ErrorCode;

/**
 * Data Transfer Object for standardized error responses.
 *
 * Provides a consistent structure for all error responses returned by the
 * Nemesis authentication system, ensuring predictable JSON formatting
 * and proper HTTP status codes.
 *
 * @package Kani\Nemesis\Data
 */
final class ErrorResponseData implements JsonSerializable
{
    /**
     * Private constructor to enforce factory method usage.
     *
     * @param ErrorCode $errorCode The error code enum
     * @param string $message Human-readable error message
     * @param int $status HTTP status code
     * @param array<string, mixed>|null $details Additional error details
     */
    private function __construct(
        public readonly ErrorCode $errorCode,
        public readonly string $message,
        public readonly int $status,
        public readonly ?array $details = null
    ) {}

    /**
     * Create an error response from an error code.
     *
     * @param ErrorCode $errorCode The error code
     * @param array<string, mixed>|null $details Optional additional details
     * @return self The error response DTO
     */
    public static function fromErrorCode(ErrorCode $errorCode, ?array $details = null): self
    {
        return new self(
            errorCode: $errorCode,
            message: $errorCode->message(),
            status: $errorCode->httpStatusCode(),
            details: $details
        );
    }

    /**
     * Create an error response from a raw array.
     *
     * Handles invalid error codes gracefully by falling back to INVALID_TOKEN.
     *
     * @param array<string, mixed> $data The error data
     * @return self The error response DTO
     */
    public static function fromArray(array $data): self
    {
        $errorCode = self::resolveErrorCode($data);

        return new self(
            errorCode: $errorCode,
            message: $data['message'] ?? $errorCode->message(),
            status: $data['status'] ?? $errorCode->httpStatusCode(),
            details: $data['details'] ?? null
        );
    }

    /**
     * Resolve the error code from array data.
     *
     * @param array<string, mixed> $data The error data
     * @return ErrorCode The resolved error code
     */
    private static function resolveErrorCode(array $data): ErrorCode
    {
        if ($data['errorCode'] instanceof ErrorCode) {
            return $data['errorCode'];
        }

        return ErrorCode::tryFrom($data['errorCode']) ?? ErrorCode::INVALID_TOKEN;
    }

    /**
     * Convert the error response to an array.
     *
     * @return array<string, mixed> The array representation
     */
    public function toArray(): array
    {
        $data = [
            'errorCode' => $this->errorCode->value,
            'message' => $this->message,
            'status' => $this->status,
        ];

        if ($this->details !== null && !empty($this->details)) {
            $data['details'] = $this->details;
        }

        return $data;
    }

    /**
     * Convert the error response to JSON string.
     *
     * @return string The JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Convert the object to JSON-serializable format.
     *
     * @return array<string, mixed> JSON-serializable representation
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get the error code value.
     *
     * @return string The error code string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode->value;
    }

    /**
     * Get the error message.
     *
     * @return string The error message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int The HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * Get additional error details.
     *
     * @return array<string, mixed>|null The error details
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    /**
     * Check if the response has additional details.
     *
     * @return bool True if details exist
     */
    public function hasDetails(): bool
    {
        return $this->details !== null && !empty($this->details);
    }

    /**
     * Create an error response for a missing token.
     *
     * @return self Error response for missing token
     */
    public static function missingToken(): self
    {
        return self::fromErrorCode(ErrorCode::MISSING_TOKEN);
    }

    /**
     * Create an error response for an invalid token.
     *
     * @return self Error response for invalid token
     */
    public static function invalidToken(): self
    {
        return self::fromErrorCode(ErrorCode::INVALID_TOKEN);
    }

    /**
     * Create an error response for an expired token.
     *
     * @return self Error response for expired token
     */
    public static function tokenExpired(): self
    {
        return self::fromErrorCode(ErrorCode::TOKEN_EXPIRED);
    }

    /**
     * Create an error response for insufficient permissions.
     *
     * @param string|null $requiredAbility The required ability that was missing
     * @return self Error response for insufficient permissions
     */
    public static function insufficientPermissions(?string $requiredAbility = null): self
    {
        $details = $requiredAbility !== null ? ['required_ability' => $requiredAbility] : null;

        return self::fromErrorCode(ErrorCode::INSUFFICIENT_PERMISSIONS, $details);
    }

    /**
     * Create an error response for a disallowed origin.
     *
     * @param string|null $origin The origin that was not allowed
     * @return self Error response for origin not allowed
     */
    public static function originNotAllowed(?string $origin = null): self
    {
        $details = $origin !== null ? ['origin' => $origin] : null;

        return self::fromErrorCode(ErrorCode::ORIGIN_NOT_ALLOWED, $details);
    }

    /**
     * Create an error response for an invalid authenticatable model.
     *
     * @param string|null $modelClass The model class that failed
     * @param string|null $expectedInterface The expected interface
     * @return self Error response for invalid authenticatable model
     */
    public static function invalidAuthenticatableModel(
        ?string $modelClass = null,
        ?string $expectedInterface = null
    ): self {
        $details = [];

        if ($modelClass !== null) {
            $details['model_class'] = $modelClass;
        }

        if ($expectedInterface !== null) {
            $details['expected_interface'] = $expectedInterface;
        }

        $details['message'] = 'Authenticatable model must implement MustNemesis interface';

        return self::fromErrorCode(ErrorCode::INVALID_AUTHENTICATABLE_MODEL, $details);
    }
}
