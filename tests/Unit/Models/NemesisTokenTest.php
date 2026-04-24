<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Models;

use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for NemesisToken model.
 *
 * Verifies token functionality including:
 * - Expiration and validity checks
 * - Revocation (soft delete)
 * - Ability/permission checks
 * - Origin/CORS restrictions
 * - Metadata management
 */
final class NemesisTokenTest extends TestCase
{
    private NemesisToken $tokenModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Create a user and a token for testing
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $plainToken = $user->createNemesisToken('Test Token', 'test');
        $this->tokenModel = $user->getNemesisToken($plainToken);
    }

    // ============================================================================
    // Tests for expiration
    // ============================================================================

    /**
     * Test that isExpired returns false for non-expired token.
     */
    public function test_is_expired_returns_false_for_non_expired_token(): void
    {
        // Assert: Token is not expired
        $this->assertFalse($this->tokenModel->isExpired());
    }

    /**
     * Test that isExpired returns true for expired token.
     */
    public function test_is_expired_returns_true_for_expired_token(): void
    {
        // Arrange: Expire the token
        $this->tokenModel->expires_at = now()->subDay();
        $this->tokenModel->save();

        // Assert: Token is expired
        $this->assertTrue($this->tokenModel->isExpired());
    }

    /**
     * Test that isExpired returns false when expiration is null.
     */
    public function test_is_expired_returns_false_when_expiration_is_null(): void
    {
        // Arrange: Set expiration to null
        $this->tokenModel->expires_at = null;
        $this->tokenModel->save();

        // Assert: Token never expires
        $this->assertFalse($this->tokenModel->isExpired());
    }

    // ============================================================================
    // Tests for revocation (soft delete)
    // ============================================================================

    /**
     * Test that isRevoked returns false for non-revoked token.
     */
    public function test_is_revoked_returns_false_for_non_revoked_token(): void
    {
        // Assert: Token is not revoked
        $this->assertFalse($this->tokenModel->isRevoked());
    }

    /**
     * Test that isRevoked returns true for revoked token.
     */
    public function test_is_revoked_returns_true_for_revoked_token(): void
    {
        // Arrange: Revoke the token
        $this->tokenModel->revoke();

        // Assert: Token is revoked
        $this->assertTrue($this->tokenModel->isRevoked());
    }

    /**
     * Test that revoke soft deletes the token.
     */
    public function test_revoke_soft_deletes_token(): void
    {
        // Act: Revoke the token
        $result = $this->tokenModel->revoke();

        // Assert: Token is soft deleted
        $this->assertTrue($result);
        $this->assertNotNull($this->tokenModel->deleted_at);
        $this->assertTrue($this->tokenModel->trashed());
    }

    /**
     * Test that restoreRevoked restores a revoked token.
     */
    public function test_restore_revoked_restores_revoked_token(): void
    {
        // Arrange: Revoke the token
        $this->tokenModel->revoke();

        // Act: Restore the token
        $result = $this->tokenModel->restoreRevoked();

        // Assert: Token is restored
        $this->assertTrue($result);
        $this->assertNull($this->tokenModel->deleted_at);
        $this->assertFalse($this->tokenModel->trashed());
    }

    // ============================================================================
    // Tests for validity
    // ============================================================================

    /**
     * Test that isValid returns true for valid token.
     */
    public function test_is_valid_returns_true_for_valid_token(): void
    {
        // Assert: Token is valid (not expired, not revoked)
        $this->assertTrue($this->tokenModel->isValid());
    }

    /**
     * Test that isValid returns false for expired token.
     */
    public function test_is_valid_returns_false_for_expired_token(): void
    {
        // Arrange: Expire the token
        $this->tokenModel->expires_at = now()->subDay();
        $this->tokenModel->save();

        // Assert: Token is invalid
        $this->assertFalse($this->tokenModel->isValid());
    }

    /**
     * Test that isValid returns false for revoked token.
     */
    public function test_is_valid_returns_false_for_revoked_token(): void
    {
        // Arrange: Revoke the token
        $this->tokenModel->revoke();

        // Assert: Token is invalid
        $this->assertFalse($this->tokenModel->isValid());
    }

    // ============================================================================
    // Tests for abilities
    // ============================================================================

    /**
     * Test that can returns true when token has the ability.
     */
    public function test_can_returns_true_when_token_has_ability(): void
    {
        // Arrange: Set abilities
        $this->tokenModel->abilities = ['read', 'write'];
        $this->tokenModel->save();

        // Assert: Token has the ability
        $this->assertTrue($this->tokenModel->can('read'));
        $this->assertTrue($this->tokenModel->can('write'));
    }

    /**
     * Test that can returns false when token lacks the ability.
     */
    public function test_can_returns_false_when_token_lacks_ability(): void
    {
        // Arrange: Set abilities
        $this->tokenModel->abilities = ['read'];
        $this->tokenModel->save();

        // Assert: Token lacks the ability
        $this->assertFalse($this->tokenModel->can('write'));
    }

    /**
     * Test that can returns true when abilities is null (unrestricted).
     */
    public function test_can_returns_true_when_abilities_is_null(): void
    {
        // Arrange: Set abilities to null
        $this->tokenModel->abilities = null;
        $this->tokenModel->save();

        // Assert: Token has all abilities
        $this->assertTrue($this->tokenModel->can('any-ability'));
    }

    /**
     * Test that canAll returns true when token has all abilities.
     */
    public function test_can_all_returns_true_when_token_has_all_abilities(): void
    {
        // Arrange: Set abilities
        $this->tokenModel->abilities = ['read', 'write', 'delete'];
        $this->tokenModel->save();

        // Assert: Token has all abilities
        $this->assertTrue($this->tokenModel->canAll(['read', 'write']));
    }

    /**
     * Test that canAll returns false when token lacks any ability.
     */
    public function test_can_all_returns_false_when_token_lacks_any_ability(): void
    {
        // Arrange: Set abilities
        $this->tokenModel->abilities = ['read', 'write'];
        $this->tokenModel->save();

        // Assert: Token lacks delete ability
        $this->assertFalse($this->tokenModel->canAll(['read', 'delete']));
    }

    // ============================================================================
    // Tests for origins
    // ============================================================================

    /**
     * Test that canUseFromOrigin returns true when origin is allowed.
     */
    public function test_can_use_from_origin_returns_true_when_origin_allowed(): void
    {
        // Arrange: Set allowed origins
        $this->tokenModel->setAllowedOrigins(['https://example.com']);

        // Assert: Origin is allowed
        $this->assertTrue($this->tokenModel->canUseFromOrigin('https://example.com'));
    }

    /**
     * Test that canUseFromOrigin returns false when origin is not allowed.
     */
    public function test_can_use_from_origin_returns_false_when_origin_not_allowed(): void
    {
        // Arrange: Set allowed origins
        $this->tokenModel->setAllowedOrigins(['https://example.com']);

        // Assert: Origin is not allowed
        $this->assertFalse($this->tokenModel->canUseFromOrigin('https://malicious.com'));
    }

    /**
     * Test that canUseFromOrigin returns true for wildcard subdomain.
     */
    public function test_can_use_from_origin_returns_true_for_wildcard_subdomain(): void
    {
        // Arrange: Set wildcard origin
        $this->tokenModel->setAllowedOrigins(['https://*.example.com']);

        // Assert: Subdomains are allowed
        $this->assertTrue($this->tokenModel->canUseFromOrigin('https://api.example.com'));
        $this->assertTrue($this->tokenModel->canUseFromOrigin('https://app.example.com'));
    }

    // ============================================================================
    // Tests for metadata
    // ============================================================================

    /**
     * Test that getMetadata returns value for existing key.
     */
    public function test_get_metadata_returns_value_for_existing_key(): void
    {
        // Arrange: Set metadata
        $this->tokenModel->setMetadata('user_agent', 'Mozilla/5.0');

        // Assert: Value is returned
        $this->assertEquals('Mozilla/5.0', $this->tokenModel->getMetadata('user_agent'));
    }

    /**
     * Test that getMetadata returns default for missing key.
     */
    public function test_get_metadata_returns_default_for_missing_key(): void
    {
        // Assert: Default value is returned
        $this->assertEquals('default', $this->tokenModel->getMetadata('missing', 'default'));
        $this->assertNull($this->tokenModel->getMetadata('missing'));
    }

    /**
     * Test that hasMetadata returns true for existing key.
     */
    public function test_has_metadata_returns_true_for_existing_key(): void
    {
        // Arrange: Set metadata
        $this->tokenModel->setMetadata('key', 'value');

        // Assert: Key exists
        $this->assertTrue($this->tokenModel->hasMetadata('key'));
    }

    /**
     * Test that hasMetadata returns false for missing key.
     */
    public function test_has_metadata_returns_false_for_missing_key(): void
    {
        // Assert: Key does not exist
        $this->assertFalse($this->tokenModel->hasMetadata('missing'));
    }

    /**
     * Test that hasMetadata returns true for key with null value.
     */
    public function test_has_metadata_returns_true_for_key_with_null_value(): void
    {
        // Arrange: Set metadata with null value
        $this->tokenModel->setMetadata('key', null);

        // Assert: Key exists (distinguishes from missing)
        $this->assertTrue($this->tokenModel->hasMetadata('key'));
        $this->assertNull($this->tokenModel->getMetadata('key'));
    }

    /**
     * Test that setMetadata stores a value.
     */
    public function test_set_metadata_stores_value(): void
    {
        // Act: Set metadata
        $result = $this->tokenModel->setMetadata('key', 'value');

        // Assert: Value is stored and method returns self for chaining
        $this->assertSame($this->tokenModel, $result);
        $this->assertEquals('value', $this->tokenModel->getMetadata('key'));
    }

    /**
     * Test that removeMetadata removes a key.
     */
    public function test_remove_metadata_removes_key(): void
    {
        // Arrange: Set metadata
        $this->tokenModel->setMetadata('key', 'value');

        // Act: Remove the key
        $result = $this->tokenModel->removeMetadata('key');

        // Assert: Key is removed and method returns self
        $this->assertSame($this->tokenModel, $result);
        $this->assertFalse($this->tokenModel->hasMetadata('key'));
    }

    /**
     * Test that getAllMetadata returns all metadata.
     */
    public function test_get_all_metadata_returns_all_metadata(): void
    {
        // Arrange: Set multiple metadata
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];
        $this->tokenModel->setAllMetadata($metadata);

        // Assert: All metadata is returned
        $this->assertSame($metadata, $this->tokenModel->getAllMetadata());
    }

    /**
     * Test that mergeMetadata merges with existing metadata.
     */
    public function test_merge_metadata_merges_with_existing(): void
    {
        // Arrange: Set initial metadata
        $this->tokenModel->setAllMetadata(['existing' => 'value']);

        // Act: Merge new metadata
        $result = $this->tokenModel->mergeMetadata(['new' => 'data']);

        // Assert: Both old and new keys exist
        $this->assertSame($this->tokenModel, $result);
        $this->assertEquals('value', $this->tokenModel->getMetadata('existing'));
        $this->assertEquals('data', $this->tokenModel->getMetadata('new'));
    }

    /**
     * Test that clearMetadata removes all metadata.
     */
    public function test_clear_metadata_removes_all_metadata(): void
    {
        // Arrange: Set metadata
        $this->tokenModel->setAllMetadata(['key' => 'value']);

        // Act: Clear all metadata
        $result = $this->tokenModel->clearMetadata();

        // Assert: Metadata is cleared
        $this->assertSame($this->tokenModel, $result);
        $this->assertNull($this->tokenModel->getAllMetadata());
    }

    // ============================================================================
    // Tests for force expiration
    // ============================================================================

    /**
     * Test that forceExpire immediately expires the token.
     */
    public function test_force_expire_immediately_expires_token(): void
    {
        // Act: Force expire
        $result = $this->tokenModel->forceExpire();

        // Assert: Token is expired
        $this->assertSame($this->tokenModel, $result);
        $this->assertTrue($this->tokenModel->isExpired());
    }

    /**
     * Test that forceExpireByMinutes expires token by minutes.
     */
    public function test_force_expire_by_minutes_expires_token_by_minutes(): void
    {
        // Act: Force expire by 60 minutes
        $result = $this->tokenModel->forceExpireByMinutes(60);

        // Assert: Token is expired
        $this->assertSame($this->tokenModel, $result);
        $this->assertTrue($this->tokenModel->isExpired());
    }
}
