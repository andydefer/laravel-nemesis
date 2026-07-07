<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Services;

use AndyDefer\Nemesis\Contracts\Services\MetadataValidatorInterface;
use AndyDefer\Nemesis\Exceptions\MetadataValidationException;

/**
 * Service for validating and sanitizing structured metadata.
 *
 * Handles validation of metadata arrays with security constraints including:
 * - Size limits (max 64KB)
 * - Nesting depth limits (max 5 levels)
 * - Key count limits (max 100 keys)
 * - Key length limits (max 255 characters)
 * - Type validation for keys and values
 * - Recursive sanitization (removes null values and empty arrays)
 */
final class MetadataValidatorService implements MetadataValidatorInterface
{
    /**
     * Maximum allowed size of metadata in bytes (64KB).
     */
    private const MAX_METADATA_SIZE = 65536;

    /**
     * Maximum nesting depth for metadata arrays.
     */
    private const MAX_NESTING_DEPTH = 5;

    /**
     * Maximum number of keys in metadata.
     */
    private const MAX_KEYS = 100;

    /**
     * Maximum length for a metadata key in characters.
     */
    private const MAX_KEY_LENGTH = 255;

    // ============================================================================
    // Public API Methods
    // ============================================================================

    /**
     * {@inheritDoc}
     */
    public function validate(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $this->validateTotalSize($metadata);
        $this->validateNestingDepth($metadata);
        $this->validateKeyCount($metadata);
        $this->validateAllKeysAndValues($metadata);

        return $metadata;
    }

    /**
     * {@inheritDoc}
     */
    public function isValid(?array $metadata): bool
    {
        try {
            $this->validate($metadata);

            return true;
        } catch (MetadataValidationException) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function sanitize(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
                if ($value === null || $value === []) {
                    continue;
                }
            }

            $sanitized[$key] = $value;
        }

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * {@inheritDoc}
     */
    public function process(?array $metadata): ?array
    {
        $validated = $this->validate($metadata);

        return $this->sanitize($validated);
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(?array $metadata): int
    {
        if ($metadata === null || $metadata === []) {
            return 0;
        }

        return strlen(json_encode($metadata));
    }

    /**
     * {@inheritDoc}
     */
    public function getNestingDepth(array $metadata, int $currentDepth = 1): int
    {
        $maxDepth = $currentDepth;

        foreach ($metadata as $value) {
            if (is_array($value)) {
                $depth = $this->getNestingDepth($value, $currentDepth + 1);
                $maxDepth = max($maxDepth, $depth);
            }
        }

        return $maxDepth;
    }

    // ============================================================================
    // Validation Methods
    // ============================================================================

    /**
     * Validate the total serialized size of metadata.
     *
     * @param  array  $metadata  The metadata to check
     *
     * @throws MetadataValidationException When size exceeds limit
     */
    private function validateTotalSize(array $metadata): void
    {
        $jsonSize = $this->getSize($metadata);

        if ($jsonSize > self::MAX_METADATA_SIZE) {
            throw MetadataValidationException::sizeExceeded($jsonSize, self::MAX_METADATA_SIZE);
        }
    }

    /**
     * Validate the nesting depth of metadata arrays.
     *
     * @param  array  $metadata  The metadata to check
     * @param  int  $currentDepth  Current depth in recursion
     *
     * @throws MetadataValidationException When depth exceeds limit
     */
    private function validateNestingDepth(array $metadata, int $currentDepth = 1): void
    {
        if ($currentDepth > self::MAX_NESTING_DEPTH) {
            throw MetadataValidationException::nestingTooDeep($currentDepth, self::MAX_NESTING_DEPTH);
        }

        foreach ($metadata as $value) {
            if (is_array($value)) {
                $this->validateNestingDepth($value, $currentDepth + 1);
            }
        }
    }

    /**
     * Validate that the number of metadata keys does not exceed the limit.
     *
     * @param  array  $metadata  The metadata to check
     *
     * @throws MetadataValidationException When key count exceeds limit
     */
    private function validateKeyCount(array $metadata): void
    {
        $keyCount = count($metadata);

        if ($keyCount > self::MAX_KEYS) {
            throw MetadataValidationException::tooManyKeys($keyCount, self::MAX_KEYS);
        }
    }

    /**
     * Validate all keys and their associated values.
     *
     * @param  array  $metadata  The metadata to validate
     *
     * @throws MetadataValidationException When any key or value is invalid
     */
    private function validateAllKeysAndValues(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            $this->validateKeyType($key);

            $keyString = (string) $key;
            $this->validateKeyLength($keyString);
            $this->validateValueType($value, $keyString);
        }
    }

    /**
     * Validate that a key is of an acceptable type (string or int).
     *
     * @param  mixed  $key  The key to validate
     *
     * @throws MetadataValidationException When key type is invalid
     */
    private function validateKeyType(mixed $key): void
    {
        if (! is_string($key) && ! is_int($key)) {
            throw MetadataValidationException::invalidKeyType(gettype($key));
        }
    }

    /**
     * Validate that a key does not exceed maximum length.
     *
     * @param  string  $key  The key to validate
     *
     * @throws MetadataValidationException When key exceeds maximum length
     */
    private function validateKeyLength(string $key): void
    {
        $length = strlen($key);

        if ($length > self::MAX_KEY_LENGTH) {
            throw MetadataValidationException::keyTooLong($key, $length, self::MAX_KEY_LENGTH);
        }
    }

    /**
     * Validate that a value is of an acceptable type.
     *
     * @param  mixed  $value  The value to validate
     * @param  string|null  $key  Optional key name for error context
     *
     * @throws MetadataValidationException When value type is invalid
     */
    private function validateValueType(mixed $value, ?string $key = null): void
    {
        if (! $this->isValidValue($value)) {
            throw MetadataValidationException::invalidValueType($key, gettype($value));
        }
    }

    /**
     * Check if a value is of a valid type.
     *
     * Valid types: scalar (string, int, float, bool), array, or null.
     *
     * @param  mixed  $value  The value to check
     * @return bool True if value type is valid, false otherwise
     */
    private function isValidValue(mixed $value): bool
    {
        return is_scalar($value) || is_array($value) || $value === null;
    }
}
