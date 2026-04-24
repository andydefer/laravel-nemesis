<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Services;

use Kani\Nemesis\Exceptions\MetadataValidationException;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Services\TokenMetadataService;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for TokenMetadataService.
 *
 * Verifies metadata validation, storage, retrieval, and sanitization operations
 * on token metadata. Tests cover all CRUD operations, validation rules,
 * sanitization behaviors, and edge cases.
 */
final class TokenMetadataServiceTest extends TestCase
{
    private NemesisToken $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create a test user with an associated token
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $plainToken = $user->createNemesisToken('Test Token', 'test');
        $this->token = $user->getNemesisToken($plainToken);
    }

    // ============================================================================
    // Tests for set() method - Storing complete metadata
    // ============================================================================

    /**
     * Test that set() stores metadata correctly in the token.
     */
    public function test_set_stores_metadata_correctly(): void
    {
        // Arrange: Prepare metadata to store
        $metadata = ['user_agent' => 'Mozilla/5.0', 'ip' => '127.0.0.1'];

        // Act: Store the metadata
        TokenMetadataService::set($this->token, $metadata);

        // Assert: Metadata is saved and matches expected values
        $this->token->refresh();
        $this->assertEquals($metadata, $this->token->metadata);
    }

    /**
     * Test that set() with null value clears existing metadata.
     */
    public function test_set_with_null_clears_metadata(): void
    {
        // Arrange: Set initial metadata and verify it exists
        $this->token->update(['metadata' => ['key' => 'value']]);
        $this->assertNotNull($this->token->metadata);

        // Act: Set metadata to null
        TokenMetadataService::set($this->token, null);

        // Assert: Metadata is cleared
        $this->token->refresh();
        $this->assertNull($this->token->metadata);
    }

    /**
     * Test that set() with empty array clears existing metadata.
     */
    public function test_set_with_empty_array_clears_metadata(): void
    {
        // Arrange: Set initial metadata and verify it exists
        $this->token->update(['metadata' => ['key' => 'value']]);
        $this->assertNotNull($this->token->metadata);

        // Act: Set metadata to empty array
        TokenMetadataService::set($this->token, []);

        // Assert: Metadata is cleared
        $this->token->refresh();
        $this->assertNull($this->token->metadata);
    }

    // ============================================================================
    // Tests for setKey() method - Storing individual keys
    // ============================================================================

    /**
     * Test that setKey() stores a single key-value pair correctly.
     */
    public function test_set_key_stores_single_key(): void
    {
        // Arrange: Prepare key-value pair
        $key = 'user_agent';
        $value = 'Mozilla/5.0';

        // Act: Store the key
        TokenMetadataService::setKey($this->token, $key, $value);

        // Assert: Key exists and has correct value
        $this->token->refresh();
        $this->assertEquals($value, $this->token->metadata[$key]);
    }

    /**
     * Test that setKey() adds to existing metadata without overwriting other keys.
     */
    public function test_set_key_adds_to_existing_metadata(): void
    {
        // Arrange: Set initial metadata
        TokenMetadataService::set($this->token, ['existing' => 'value']);
        $this->assertArrayHasKey('existing', $this->token->metadata);

        // Act: Add a new key
        TokenMetadataService::setKey($this->token, 'new', 'data');

        // Assert: Both old and new keys exist with correct values
        $this->token->refresh();
        $this->assertEquals('value', $this->token->metadata['existing']);
        $this->assertEquals('data', $this->token->metadata['new']);
    }

    /**
     * Test that setKey() with null value removes the key from metadata.
     */
    public function test_set_key_with_null_removes_key(): void
    {
        // Arrange: Set multiple keys
        TokenMetadataService::set($this->token, ['key1' => 'value1', 'key2' => 'value2']);

        // Act: Set key1 to null
        TokenMetadataService::setKey($this->token, 'key1', null);

        // Assert: key1 is removed, key2 remains
        $this->token->refresh();
        $this->assertArrayNotHasKey('key1', $this->token->metadata);
        $this->assertEquals('value2', $this->token->metadata['key2']);
    }

    /**
     * Test that setKey() throws exception when key exceeds maximum length.
     */
    public function test_set_key_throws_exception_for_key_too_long(): void
    {
        // Arrange: Generate a key longer than 255 characters
        $longKey = str_repeat('a', 256);

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('Metadata key exceeds maximum length');

        // Act: Attempt to store the oversized key
        TokenMetadataService::setKey($this->token, $longKey, 'value');
    }

    /**
     * Test that setKey() throws exception when value type is invalid.
     */
    public function test_set_key_throws_exception_for_invalid_value_type(): void
    {
        // Arrange: Create an invalid resource value
        $invalidValue = fopen('php://memory', 'r');

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('Metadata value for key "key" must be scalar, array, or null, resource given');

        // Act: Attempt to store a resource (invalid type)
        TokenMetadataService::setKey($this->token, 'key', $invalidValue);
    }

    // ============================================================================
    // Tests for get() method - Retrieving metadata values
    // ============================================================================

    /**
     * Test that get() returns the correct value for an existing key.
     */
    public function test_get_returns_metadata_value(): void
    {
        // Arrange: Set metadata
        TokenMetadataService::set($this->token, ['user_agent' => 'Mozilla/5.0']);

        // Act: Retrieve the value
        $result = TokenMetadataService::get($this->token, 'user_agent');

        // Assert: Value matches expected
        $this->assertEquals('Mozilla/5.0', $result);
    }

    /**
     * Test that get() returns default value for missing key when provided.
     */
    public function test_get_returns_default_for_missing_key(): void
    {
        // Act: Get missing key with default
        $result = TokenMetadataService::get($this->token, 'missing', 'default');

        // Assert: Default value is returned
        $this->assertEquals('default', $result);
    }

    /**
     * Test that get() returns null for missing key when no default provided.
     */
    public function test_get_returns_null_for_missing_key_without_default(): void
    {
        // Act: Get missing key without default
        $result = TokenMetadataService::get($this->token, 'missing');

        // Assert: Null is returned
        $this->assertNull($result);
    }

    // ============================================================================
    // Tests for has() method - Checking key existence
    // ============================================================================

    /**
     * Test that has() returns true for existing keys.
     */
    public function test_has_returns_true_for_existing_key(): void
    {
        // Arrange: Set metadata
        TokenMetadataService::set($this->token, ['user_agent' => 'Mozilla/5.0']);

        // Act & Assert: Key existence check passes
        $this->assertTrue(TokenMetadataService::has($this->token, 'user_agent'));
    }

    /**
     * Test that has() returns false for missing keys.
     */
    public function test_has_returns_false_for_missing_key(): void
    {
        // Act & Assert: Non-existent key returns false
        $this->assertFalse(TokenMetadataService::has($this->token, 'missing'));
    }

    // ============================================================================
    // Tests for remove() method - Deleting keys
    // ============================================================================

    /**
     * Test that remove() deletes a specific metadata key.
     */
    public function test_remove_deletes_metadata_key(): void
    {
        // Arrange: Set multiple keys
        TokenMetadataService::set($this->token, ['key1' => 'value1', 'key2' => 'value2']);

        // Act: Remove key1
        TokenMetadataService::remove($this->token, 'key1');

        // Assert: key1 is removed, key2 remains
        $this->token->refresh();
        $this->assertArrayNotHasKey('key1', $this->token->metadata);
        $this->assertEquals('value2', $this->token->metadata['key2']);
    }

    /**
     * Test that remove() does nothing for non-existent keys.
     */
    public function test_remove_does_nothing_for_nonexistent_key(): void
    {
        // Arrange: Set metadata
        TokenMetadataService::set($this->token, ['key1' => 'value1']);

        // Act: Try to remove non-existent key
        TokenMetadataService::remove($this->token, 'nonexistent');

        // Assert: Original metadata unchanged
        $this->token->refresh();
        $this->assertEquals('value1', $this->token->metadata['key1']);
    }

    // ============================================================================
    // Tests for all() method - Retrieving all metadata
    // ============================================================================

    /**
     * Test that all() returns all metadata when present.
     */
    public function test_all_returns_all_metadata(): void
    {
        // Arrange: Set metadata
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        TokenMetadataService::set($this->token, $metadata);

        // Act: Retrieve all metadata
        $result = TokenMetadataService::all($this->token);

        // Assert: All metadata returned
        $this->assertSame($metadata, $result);
    }

    /**
     * Test that all() returns null when no metadata exists.
     */
    public function test_all_returns_null_when_no_metadata(): void
    {
        // Act: Get all metadata from empty token
        $result = TokenMetadataService::all($this->token);

        // Assert: Null is returned
        $this->assertNull($result);
    }

    // ============================================================================
    // Tests for clear() method - Removing all metadata
    // ============================================================================

    /**
     * Test that clear() removes all metadata from the token.
     */
    public function test_clear_removes_all_metadata(): void
    {
        // Arrange: Set multiple metadata entries
        TokenMetadataService::set($this->token, ['key1' => 'value1', 'key2' => 'value2']);
        $this->assertNotNull($this->token->metadata);

        // Act: Clear all metadata
        TokenMetadataService::clear($this->token);

        // Assert: Metadata is cleared
        $this->token->refresh();
        $this->assertNull($this->token->metadata);
    }

    // ============================================================================
    // Tests for merge() method - Combining metadata
    // ============================================================================

    /**
     * Test that merge() combines new metadata with existing.
     */
    public function test_merge_combines_metadata(): void
    {
        // Arrange: Set initial metadata
        TokenMetadataService::set($this->token, ['existing' => 'value']);

        // Act: Merge new metadata
        TokenMetadataService::merge($this->token, ['new' => 'data']);

        // Assert: Both old and new keys exist
        $this->token->refresh();
        $this->assertEquals('value', $this->token->metadata['existing']);
        $this->assertEquals('data', $this->token->metadata['new']);
    }

    /**
     * Test that merge() overwrites existing keys with new values.
     */
    public function test_merge_overwrites_existing_keys(): void
    {
        // Arrange: Set initial metadata
        TokenMetadataService::set($this->token, ['key' => 'old']);

        // Act: Merge with same key
        TokenMetadataService::merge($this->token, ['key' => 'new']);

        // Assert: Key value is overwritten
        $this->token->refresh();
        $this->assertEquals('new', $this->token->metadata['key']);
    }

    // ============================================================================
    // Tests for validate() method - Input validation
    // ============================================================================

    /**
     * Test that validate() passes for valid metadata structures.
     */
    public function test_validate_passes_for_valid_metadata(): void
    {
        // Arrange: Prepare valid nested metadata
        $metadata = ['key' => 'value', 'nested' => ['deep' => 'data']];

        // Act: Validate the metadata
        $result = TokenMetadataService::validate($metadata);

        // Assert: Validated metadata is returned unchanged
        $this->assertSame($metadata, $result);
    }

    /**
     * Test that validate() returns null for empty metadata.
     */
    public function test_validate_returns_null_for_empty_metadata(): void
    {
        // Act: Validate empty array
        $result = TokenMetadataService::validate([]);

        // Assert: Null is returned
        $this->assertNull($result);
    }

    /**
     * Test that validate() throws exception when total size exceeds limit.
     */
    public function test_validate_throws_exception_for_size_exceeded(): void
    {
        // Arrange: Create metadata exceeding 64KB limit
        $largeMetadata = ['data' => str_repeat('a', 70000)];

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('Metadata size');

        // Act: Attempt to validate oversized metadata
        TokenMetadataService::validate($largeMetadata);
    }

    /**
     * Test that validate() throws exception when nesting depth is too deep.
     */
    public function test_validate_throws_exception_for_nesting_too_deep(): void
    {
        // Arrange: Create deeply nested array (6 levels deep, max is 5)
        $deepMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => [
                                'level6' => 'too deep',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('nesting depth exceeds maximum');

        // Act: Attempt to validate deeply nested metadata
        TokenMetadataService::validate($deepMetadata);
    }

    /**
     * Test that validate() throws exception when too many keys exist.
     */
    public function test_validate_throws_exception_for_too_many_keys(): void
    {
        // Arrange: Create array with 101 keys (max is 100)
        $manyKeys = [];
        for ($i = 0; $i < 101; ++$i) {
            $manyKeys['key_' . $i] = 'value_' . $i;
        }

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('Metadata contains 101 keys, maximum allowed is 100');

        // Act: Attempt to validate metadata with too many keys
        TokenMetadataService::validate($manyKeys);
    }

    /**
     * Test that validate() accepts integer keys as valid.
     */
    public function test_validate_accepts_integer_keys(): void
    {
        // Arrange: Prepare metadata with integer keys
        $metadata = [1 => 'value', 2 => 'another'];

        // Act: Validate the metadata
        $result = TokenMetadataService::validate($metadata);

        // Assert: Integer keys are preserved
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertEquals('value', $result[1]);
        $this->assertEquals('another', $result[2]);
    }

    // ============================================================================
    // Tests for sanitize() method - Cleaning metadata
    // ============================================================================

    /**
     * Test that sanitize() removes null values from metadata.
     */
    public function test_sanitize_removes_null_values(): void
    {
        // Arrange: Prepare metadata with null values
        $metadata = ['keep' => 'value', 'remove' => null, 'also_keep' => 'data'];

        // Act: Sanitize the metadata
        $result = TokenMetadataService::sanitize($metadata);

        // Assert: Null values are removed
        $this->assertSame(['keep' => 'value', 'also_keep' => 'data'], $result);
    }

    /**
     * Test that sanitize() removes empty arrays recursively.
     */
    public function test_sanitize_removes_empty_arrays_recursively(): void
    {
        // Arrange: Prepare nested metadata with empty arrays
        $metadata = [
            'keep' => 'value',
            'nested' => [
                'keep' => 'data',
                'empty' => [],
            ],
            'empty_array' => [],
        ];

        // Act: Sanitize the metadata
        $result = TokenMetadataService::sanitize($metadata);

        // Assert: Empty arrays are removed recursively
        $this->assertSame([
            'keep' => 'value',
            'nested' => ['keep' => 'data'],
        ], $result);
    }

    /**
     * Test that sanitize() returns null for empty arrays.
     */
    public function test_sanitize_returns_null_for_empty_array(): void
    {
        // Act: Sanitize empty array
        $result = TokenMetadataService::sanitize([]);

        // Assert: Null is returned
        $this->assertNull($result);
    }

    /**
     * Test that sanitize() returns null for null input.
     */
    public function test_sanitize_returns_null_for_null(): void
    {
        // Act: Sanitize null
        $result = TokenMetadataService::sanitize(null);

        // Assert: Null is returned
        $this->assertNull($result);
    }

    /**
     * Test that sanitize() correctly handles deeply nested structures.
     */
    public function test_sanitize_works_with_nested_arrays(): void
    {
        // Arrange: Prepare deeply nested metadata with empty values
        $metadata = [
            'level1' => [
                'level2' => [
                    'keep' => 'value',
                    'null' => null,
                    'empty' => [],
                ],
            ],
        ];

        // Act: Sanitize the metadata
        $result = TokenMetadataService::sanitize($metadata);

        // Assert: Only valid values remain in nested structure
        $this->assertSame([
            'level1' => [
                'level2' => ['keep' => 'value'],
            ],
        ], $result);
    }

    // ============================================================================
    // Integration tests - Complete workflows
    // ============================================================================

    /**
     * Test that a complete metadata workflow works correctly.
     */
    public function test_full_workflow(): void
    {
        // Arrange: Set initial metadata
        TokenMetadataService::set($this->token, ['user' => 'john', 'role' => 'admin']);

        // Act & Assert: Test retrieval
        $this->assertEquals('john', TokenMetadataService::get($this->token, 'user'));

        // Act & Assert: Test key update
        TokenMetadataService::setKey($this->token, 'user', 'jane');
        $this->assertEquals('jane', TokenMetadataService::get($this->token, 'user'));

        // Act & Assert: Test existence checks
        $this->assertTrue(TokenMetadataService::has($this->token, 'role'));
        $this->assertFalse(TokenMetadataService::has($this->token, 'missing'));

        // Act & Assert: Test removal
        TokenMetadataService::remove($this->token, 'role');
        $this->assertNull(TokenMetadataService::get($this->token, 'role'));

        // Act & Assert: Test clear all
        TokenMetadataService::clear($this->token);
        $this->assertNull(TokenMetadataService::all($this->token));
    }

    /**
     * Test that validation prevents malicious cyclic reference metadata.
     */
    public function test_validation_prevents_malicious_metadata(): void
    {
        // Arrange: Create recursive/circular reference structure
        $malicious = [];
        $ref = &$malicious;
        for ($i = 0; $i < 10; ++$i) {
            $ref['nested'] = [];
            $ref = &$ref['nested'];
        }

        $this->expectException(MetadataValidationException::class);

        // Act: Attempt to validate malicious structure
        TokenMetadataService::validate($malicious);
    }
}
