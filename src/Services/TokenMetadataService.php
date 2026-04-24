<?php

declare(strict_types=1);

namespace Kani\Nemesis\Services;

use Kani\Nemesis\Enums\ErrorCode;
use Kani\Nemesis\Exceptions\MetadataValidationException;
use Kani\Nemesis\Models\NemesisToken;

/**
 * Service for managing token metadata.
 *
 * Handles validation, storage, and retrieval of token metadata with security
 * constraints including size limits, nesting depth limits, and data sanitization.
 *
 * @example
 * // Basic usage
 * use Kani\Nemesis\Services\TokenMetadataService;
 *
 * // Store metadata
 * TokenMetadataService::set($token, ['user_agent' => 'Mozilla/5.0', 'ip' => '127.0.0.1']);
 *
 * // Retrieve a value
 * $userAgent = TokenMetadataService::get($token, 'user_agent');
 *
 * // Update a single key
 * TokenMetadataService::setKey($token, 'last_login', now()->toDateTimeString());
 *
 * // Check if a key exists
 * if (TokenMetadataService::has($token, 'session_id')) {
 *     $sessionId = TokenMetadataService::get($token, 'session_id');
 * }
 */
final class TokenMetadataService
{
    /**
     * Maximum allowed size of metadata in bytes (64KB).
     *
     * @var int
     */
    private const MAX_METADATA_SIZE = 65536;

    /**
     * Maximum nesting depth for metadata arrays.
     *
     * @var int
     */
    private const MAX_NESTING_DEPTH = 5;

    /**
     * Maximum number of keys in metadata.
     *
     * @var int
     */
    private const MAX_KEYS = 100;

    /**
     * Maximum length for a metadata key in characters.
     *
     * @var int
     */
    private const MAX_KEY_LENGTH = 255;

    // ============================================================================
    // Public API Methods
    // ============================================================================

    /**
     * Set all metadata for a token (replaces existing metadata).
     *
     * @param  NemesisToken  $token  The token to update
     * @param  array|null  $metadata  New metadata array or null to clear
     * @return NemesisToken The updated token instance
     *
     * @throws MetadataValidationException When metadata fails validation
     */
    public static function set(NemesisToken $token, ?array $metadata): NemesisToken
    {
        $validated = self::validate($metadata);
        $sanitized = self::sanitize($validated);

        $token->update(['metadata' => $sanitized]);

        return $token;
    }

    /**
     * Set or remove a single metadata key.
     *
     * @param  NemesisToken  $token  The token to update
     * @param  string  $key  The metadata key (max 255 characters)
     * @param  mixed  $value  The value to store (scalar, array, or null to remove)
     * @return NemesisToken The updated token instance
     *
     * @throws MetadataValidationException When key is too long or value type is invalid
     */
    public static function setKey(NemesisToken $token, string $key, mixed $value): NemesisToken
    {
        self::validateKeyLength($key);
        self::validateValueType($value, $key);

        $metadata = $token->metadata ?? [];

        if ($value === null) {
            unset($metadata[$key]);
        } else {
            $metadata[$key] = $value;
        }

        return self::set($token, empty($metadata) ? null : $metadata);
    }

    /**
     * Get a metadata value by key.
     *
     * @param  NemesisToken  $token  The token containing metadata
     * @param  string  $key  The metadata key to retrieve
     * @param  mixed  $default  Default value if key doesn't exist
     * @return mixed The metadata value or default
     */
    public static function get(NemesisToken $token, string $key, mixed $default = null): mixed
    {
        return $token->metadata[$key] ?? $default;
    }

    /**
     * Check if a metadata key exists.
     *
     * @param  NemesisToken  $token  The token to check
     * @param  string  $key  The metadata key to look for
     * @return bool True if key exists, false otherwise
     */
    public static function has(NemesisToken $token, string $key): bool
    {
        return isset($token->metadata[$key]);
    }

    /**
     * Remove a specific metadata key.
     *
     * @param  NemesisToken  $token  The token to update
     * @param  string  $key  The metadata key to remove
     * @return NemesisToken The updated token instance
     */
    public static function remove(NemesisToken $token, string $key): NemesisToken
    {
        $metadata = $token->metadata ?? [];

        if (isset($metadata[$key])) {
            unset($metadata[$key]);
            $token->update(['metadata' => empty($metadata) ? null : $metadata]);
        }

        return $token;
    }

    /**
     * Get all metadata from a token.
     *
     * @param  NemesisToken  $token  The token to retrieve metadata from
     * @return array|null All metadata as array or null if none exists
     */
    public static function all(NemesisToken $token): ?array
    {
        return $token->metadata;
    }

    /**
     * Clear all metadata from a token.
     *
     * @param  NemesisToken  $token  The token to clear
     * @return NemesisToken The updated token instance
     */
    public static function clear(NemesisToken $token): NemesisToken
    {
        $token->update(['metadata' => null]);

        return $token;
    }

    /**
     * Merge new metadata with existing metadata.
     *
     * New values overwrite existing keys with the same name.
     *
     * @param  NemesisToken  $token  The token to update
     * @param  array  $metadata  New metadata to merge
     * @return NemesisToken The updated token instance
     *
     * @throws MetadataValidationException When merged metadata fails validation
     */
    public static function merge(NemesisToken $token, array $metadata): NemesisToken
    {
        $existing = $token->metadata ?? [];
        $merged = array_merge($existing, $metadata);

        return self::set($token, $merged);
    }

    // ============================================================================
    // Validation Methods
    // ============================================================================

    /**
     * Validate metadata before storage.
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
    public static function validate(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        self::validateTotalSize($metadata);
        self::validateNestingDepth($metadata);
        self::validateKeyCount($metadata);
        self::validateAllKeysAndValues($metadata);

        return $metadata;
    }

    /**
     * Validate that a key does not exceed maximum length.
     *
     * @param  string  $key  The key to validate
     *
     * @throws MetadataValidationException When key exceeds maximum length
     */
    private static function validateKeyLength(string $key): void
    {
        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw new MetadataValidationException(
                ErrorCode::METADATA_KEY_TOO_LONG,
                sprintf(
                    'Metadata key exceeds maximum length of %d characters. Got %d characters.',
                    self::MAX_KEY_LENGTH,
                    strlen($key)
                ),
                [
                    'key' => substr($key, 0, 50),
                    'length' => strlen($key),
                    'max_length' => self::MAX_KEY_LENGTH,
                ]
            );
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
    private static function validateValueType(mixed $value, ?string $key = null): void
    {
        if (! self::isValidValue($value)) {
            $context = $key !== null ? sprintf(' for key "%s"', $key) : '';

            throw new MetadataValidationException(
                ErrorCode::METADATA_INVALID_VALUE,
                sprintf(
                    'Metadata value%s must be scalar, array, or null, %s given',
                    $context,
                    gettype($value)
                ),
                [
                    'key' => $key,
                    'type' => gettype($value),
                    'allowed_types' => ['scalar', 'array', 'null'],
                ]
            );
        }
    }

    /**
     * Validate the total serialized size of metadata.
     *
     * @param  array  $metadata  The metadata to check
     *
     * @throws MetadataValidationException When size exceeds limit
     */
    private static function validateTotalSize(array $metadata): void
    {
        $jsonSize = strlen(json_encode($metadata));

        if ($jsonSize > self::MAX_METADATA_SIZE) {
            throw new MetadataValidationException(
                ErrorCode::METADATA_SIZE_EXCEEDED,
                sprintf(
                    'Metadata size (%d bytes) exceeds maximum allowed (%d bytes)',
                    $jsonSize,
                    self::MAX_METADATA_SIZE
                ),
                [
                    'size' => $jsonSize,
                    'max_size' => self::MAX_METADATA_SIZE,
                    'size_mb' => round($jsonSize / 1024, 2),
                    'max_mb' => round(self::MAX_METADATA_SIZE / 1024, 2),
                ]
            );
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
    private static function validateNestingDepth(array $metadata, int $currentDepth = 1): void
    {
        if ($currentDepth > self::MAX_NESTING_DEPTH) {
            throw new MetadataValidationException(
                ErrorCode::METADATA_NESTING_TOO_DEEP,
                sprintf(
                    'Metadata nesting depth exceeds maximum allowed (%d)',
                    self::MAX_NESTING_DEPTH
                ),
                [
                    'current_depth' => $currentDepth,
                    'max_depth' => self::MAX_NESTING_DEPTH,
                ]
            );
        }

        foreach ($metadata as $value) {
            if (is_array($value)) {
                self::validateNestingDepth($value, $currentDepth + 1);
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
    private static function validateKeyCount(array $metadata): void
    {
        $keyCount = count($metadata);

        if ($keyCount > self::MAX_KEYS) {
            throw new MetadataValidationException(
                ErrorCode::METADATA_TOO_MANY_KEYS,
                sprintf(
                    'Metadata contains %d keys, maximum allowed is %d',
                    $keyCount,
                    self::MAX_KEYS
                ),
                [
                    'key_count' => $keyCount,
                    'max_keys' => self::MAX_KEYS,
                ]
            );
        }
    }

    /**
     * Validate all keys and their associated values.
     *
     * @param  array  $metadata  The metadata to validate
     *
     * @throws MetadataValidationException When any key or value is invalid
     */
    private static function validateAllKeysAndValues(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            self::validateKeyType($key);

            $keyString = (string) $key;
            self::validateKeyLength($keyString);
            self::validateValueType($value, $keyString);
        }
    }

    /**
     * Validate that a key is of an acceptable type (string or int).
     *
     * @param  mixed  $key  The key to validate
     *
     * @throws MetadataValidationException When key type is invalid
     */
    private static function validateKeyType(mixed $key): void
    {
        if (! is_string($key) && ! is_int($key)) {
            throw new MetadataValidationException(
                ErrorCode::METADATA_INVALID_KEY,
                sprintf(
                    'Metadata key must be string or int, %s given',
                    gettype($key)
                ),
                [
                    'key_type' => gettype($key),
                    'allowed_types' => ['string', 'int'],
                ]
            );
        }
    }

    /**
     * Check if a value is of a valid type for storage.
     *
     * Valid types: scalar (string, int, float, bool), array, or null.
     *
     * @param  mixed  $value  The value to check
     * @return bool True if value type is valid, false otherwise
     */
    private static function isValidValue(mixed $value): bool
    {
        return is_scalar($value) || is_array($value) || $value === null;
    }

    // ============================================================================
    // Sanitization Methods
    // ============================================================================

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
    public static function sanitize(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            // Skip null values
            if ($value === null) {
                continue;
            }

            // Recursively sanitize arrays
            if (is_array($value)) {
                $value = self::sanitize($value);
                // Skip empty arrays
                if ($value === null) {
                    continue;
                }
                if ($value === []) {
                    continue;
                }
            }

            $sanitized[$key] = $value;
        }

        return $sanitized === [] ? null : $sanitized;
    }
}
