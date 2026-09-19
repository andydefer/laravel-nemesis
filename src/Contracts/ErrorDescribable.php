<?php

// src/Contracts/ErrorDescribable.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts;

use AndyDefer\Actions\Http\ResponseFactory;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Nemesis\Datas\ErrorResponseData;
use AndyDefer\PhpVo\Enums\HttpStatusCode;

/**
 * Contract for error codes that can describe themselves.
 *
 * Any error code enum implementing this interface exposes a consistent API to:
 * - resolve the associated HTTP status code,
 * - provide a technical message (for API consumers / logs),
 * - provide a user-facing label,
 * - serialize itself into a normalized ErrorResponseData payload,
 * - build a JSON ResponseFactory ready to be returned from an action.
 */
interface ErrorDescribable
{
    /**
     * Get the HTTP status code associated with this error.
     */
    public function getHttpStatusCode(): HttpStatusCode;

    /**
     * Get the technical error message.
     */
    public function getMessage(): string;

    /**
     * Get the human-readable label.
     */
    public function getLabel(): string;

    /**
     * Build the ErrorResponseData payload for this error.
     *
     * @param  string|null  $message  Override the default message.
     * @param  array<string, mixed>|StrictAssociative|StrictDataObject|null  $errors  Optional contextual errors.
     */
    public function toResponseData(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ErrorResponseData;

    /**
     * Build a JSON ResponseFactory for this error code.
     *
     * @param  string|null  $message  Override the default message.
     * @param  array<string, mixed>|StrictAssociative|StrictDataObject|null  $errors  Optional contextual errors.
     */
    public function toJsonResponseFactory(
        ?string $message = null,
        array|StrictAssociative|StrictDataObject|null $errors = null,
    ): ResponseFactory;
}
