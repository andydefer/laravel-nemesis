<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Contracts\Services;

use AndyDefer\Nemesis\Exceptions\MetadataValidationException;

/**
 * Interface for metadata validation and sanitization.
 *
 * Provides methods to validate, sanitize, and process structured metadata
 * with security constraints including size limits, nesting depth limits,
 * key count limits, and type validation.
 */
interface MetadataValidatorInterface
{
    /**
     * Validate metadata structure.
     *
     * Performs validation checks:
     * - Empty/null validation
     * - Total serialized size
     * - Nesting depth
     * - Number of keys
     * - Key types and lengths
     * - Value types
     *
     * @param  array|null  $metadata  The metadata to validate
     * @return array|null The validated metadata or null if empty
     *
     * @throws MetadataValidationException When validation fails
     */
    public function validate(?array $metadata): ?array;

    /**
     * Check if metadata is valid without throwing exception.
     *
     * @param  array|null  $metadata  The metadata to validate
     * @return bool True if metadata is valid, false otherwise
     */
    public function isValid(?array $metadata): bool;

    /**
     * Sanitize metadata by removing null values and empty arrays.
     *
     * Recursively cleans the metadata structure:
     * - Removes entries with null values
     * - Removes empty arrays
     * - Returns null for completely empty metadata
     *
     * @param  array|null  $metadata  The metadata to sanitize
     * @return array|null Sanitized metadata or null if empty
     */
    public function sanitize(?array $metadata): ?array;

    /**
     * Validate and sanitize metadata in one operation.
     *
     * @param  array|null  $metadata  The metadata to validate and sanitize
     * @return array|null Validated and sanitized metadata, or null if empty
     *
     * @throws MetadataValidationException When validation fails
     */
    public function process(?array $metadata): ?array;

    /**
     * Get size of metadata in bytes (JSON serialized).
     *
     * @param  array|null  $metadata  The metadata to measure
     * @return int Size in bytes, or 0 if metadata is null/empty
     */
    public function getSize(?array $metadata): int;

    /**
     * Get nesting depth of metadata.
     *
     * @param  array  $metadata  The metadata to analyze
     * @return int Maximum nesting depth
     */
    public function getNestingDepth(array $metadata): int;
}
