<?php

declare(strict_types=1);

namespace Kani\Nemesis\Data;

use Kani\Nemesis\Enums\ErrorCode;

/**
 * Data transfer object for error responses.
 *
 * Provides a consistent structure for all error responses
 * returned by the Nemesis authentication system.
 */
final class ErrorResponseData
{
    /**
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
     * Create an error response from an array.
     *
     * @param array<string, mixed> $data The error data
     * @return self The error response DTO
     */
    public static function fromArray(array $data): self
    {
        $errorCode = $data['errorCode'] instanceof ErrorCode
            ? $data['errorCode']
            : ErrorCode::from($data['errorCode']);

        return new self(
            errorCode: $errorCode,
            message: $data['message'] ?? $errorCode->message(),
            status: $data['status'] ?? $errorCode->httpStatusCode(),
            details: $data['details'] ?? null
        );
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

        if ($this->details !== null) {
            $data['details'] = $this->details;
        }

        return $data;
    }

    /**
     * Convert the error response to JSON.
     *
     * @return string The JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
