<?php
// src/Data/ErrorResponseData.php

declare(strict_types=1);

namespace Kani\Nemesis\Data;

use Kani\Nemesis\Enums\ErrorCode;

class ErrorResponseData
{
    private function __construct(
        public readonly ErrorCode $errorCode,
        public readonly string $message,
        public readonly int $status,
        public readonly ?array $details = null
    ) {}

    public static function fromErrorCode(ErrorCode $errorCode, ?array $details = null): self
    {
        return new self(
            errorCode: $errorCode,
            message: $errorCode->message(),
            status: $errorCode->httpStatusCode(),
            details: $details
        );
    }

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

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
