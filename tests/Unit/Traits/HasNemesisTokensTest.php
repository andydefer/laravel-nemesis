<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Traits;

use Carbon\Carbon;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for HasNemesisTokens trait.
 *
 * Verifies that the trait provides all token management functionality
 * including creation, deletion, revocation, restoration, and querying.
 */
final class HasNemesisTokensTest extends TestCase
{
    private TestUser $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Freeze time for consistent test results
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        // Arrange: Create a fresh test user for each test
        $this->testUser = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
    }

    protected function tearDown(): void
    {
        // Arrange: Clean up all tokens created during the test
        if ($this->testUser->exists) {
            $this->testUser->nemesisTokens()->forceDelete();
        }

        // Arrange: Restore normal time behavior
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ============================================================================
    // Token Creation Tests
    // ============================================================================

    /**
     * Test that createNemesisToken generates a valid token with correct attributes.
     */
    public function test_create_nemesis_token_creates_new_token(): void
    {
        // Arrange: Define token properties
        $tokenName = 'API Token';
        $tokenSource = 'api';
        $tokenAbilities = ['read', 'write'];

        // Act: Create a new token
        $plainToken = $this->testUser->createNemesisToken(
            name: $tokenName,
            source: $tokenSource,
            abilities: $tokenAbilities
        );

        // Assert: Token is a 64-character string
        $this->assertIsString($plainToken);
        $this->assertSame(64, strlen($plainToken));

        // Assert: Token is correctly stored in database
        $storedToken = $this->testUser->getNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $storedToken);
        $this->assertEquals($tokenName, $storedToken->name);
        $this->assertEquals($tokenSource, $storedToken->source);
        $this->assertEquals($tokenAbilities, $storedToken->abilities);
    }

    /**
     * Test that createNemesisToken validates and sanitizes metadata correctly.
     */
    public function test_create_nemesis_token_validates_and_sanitizes_metadata(): void
    {
        // Arrange: Prepare metadata with null values and empty arrays to be sanitized
        $rawMetadata = [
            'keep' => 'value',
            'remove' => null,
            'nested' => ['keep' => 'data', 'empty' => []]
        ];

        // Act: Create a token with metadata
        $plainToken = $this->testUser->createNemesisToken(
            name: 'Test Token',
            metadata: $rawMetadata
        );

        // Assert: Null values and empty arrays are removed from metadata
        $storedToken = $this->testUser->getNemesisToken($plainToken);
        $expectedMetadata = ['keep' => 'value', 'nested' => ['keep' => 'data']];
        $this->assertEquals($expectedMetadata, $storedToken->metadata);
    }

    // ============================================================================
    // Bulk Token Deletion Tests
    // ============================================================================

    /**
     * Test that deleteNemesisTokens permanently deletes all tokens.
     */
    public function test_delete_nemesis_tokens_permanently_deletes_all_tokens(): void
    {
        // Arrange: Create multiple tokens
        $this->testUser->createNemesisToken('Token 1');
        $this->testUser->createNemesisToken('Token 2');

        // Arrange: Verify tokens exist before deletion
        $initialCount = $this->testUser->nemesisTokens()->count();
        $this->assertEquals(2, $initialCount);

        // Act: Permanently delete all tokens
        $deletedCount = $this->testUser->deleteNemesisTokens();

        // Assert: All tokens are permanently removed
        $this->assertSame(2, $deletedCount);
        $this->assertEquals(0, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(0, $this->testUser->nemesisTokens()->withTrashed()->count());
    }

    /**
     * Test that revokeNemesisTokens soft deletes all tokens.
     */
    public function test_revoke_nemesis_tokens_soft_deletes_all_tokens(): void
    {
        // Arrange: Create multiple tokens
        $this->testUser->createNemesisToken('Token 1');
        $this->testUser->createNemesisToken('Token 2');

        // Arrange: Verify tokens exist before revocation
        $initialCount = $this->testUser->nemesisTokens()->count();
        $this->assertEquals(2, $initialCount);

        // Act: Soft delete all tokens
        $revokedCount = $this->testUser->revokeNemesisTokens();

        // Assert: All tokens are soft deleted
        $this->assertSame(2, $revokedCount);
        $this->assertEquals(0, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(2, $this->testUser->nemesisTokens()->withTrashed()->count());
    }

    // ============================================================================
    // Current Token Deletion Tests
    // ============================================================================

    /**
     * Test that deleteCurrentNemesisToken permanently deletes the current token and returns true.
     */
    public function test_delete_current_nemesis_token_permanently_deletes_current_token(): void
    {
        // Arrange: Create a token and authenticate the request
        $plainToken = $this->testUser->createNemesisToken('Current Token');
        $this->withBearerToken($plainToken);

        // Arrange: Verify token exists before deletion
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->currentNemesisToken());

        // Act: Delete the current token
        $result = $this->testUser->deleteCurrentNemesisToken();

        // Assert: Token is permanently deleted and method returns true
        $this->assertTrue($result);
        $this->assertNull($this->testUser->currentNemesisToken());
        $this->assertEquals(0, $this->testUser->nemesisTokens()->withTrashed()->count());
    }

    /**
     * Test that deleteCurrentNemesisToken returns false when no token is present.
     */
    public function test_delete_current_nemesis_token_returns_false_when_no_token(): void
    {
        // Arrange: Ensure no token is set in the request
        $this->withBearerToken('');

        // Act: Attempt to delete the current token
        $result = $this->testUser->deleteCurrentNemesisToken();

        // Assert: Method returns false because no token was found
        $this->assertFalse($result);
    }

    /**
     * Test that revokeCurrentNemesisToken soft deletes the current token and returns true.
     */
    public function test_revoke_current_nemesis_token_soft_deletes_current_token(): void
    {
        // Arrange: Create a token and authenticate the request
        $plainToken = $this->testUser->createNemesisToken('Current Token');
        $this->withBearerToken($plainToken);

        // Arrange: Verify token exists before revocation
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->currentNemesisToken());

        // Act: Revoke the current token
        $result = $this->testUser->revokeCurrentNemesisToken();

        // Assert: Token is soft deleted and method returns true
        $this->assertTrue($result);
        $this->assertNull($this->testUser->currentNemesisToken());
        $this->assertEquals(1, $this->testUser->nemesisTokens()->withTrashed()->count());
    }

    /**
     * Test that revokeCurrentNemesisToken returns false when no token is present.
     */
    public function test_revoke_current_nemesis_token_returns_false_when_no_token(): void
    {
        // Arrange: Ensure no token is set in the request
        $this->withBearerToken('');

        // Act: Attempt to revoke the current token
        $result = $this->testUser->revokeCurrentNemesisToken();

        // Assert: Method returns false because no token was found
        $this->assertFalse($result);
    }

    // ============================================================================
    // Token Existence Tests
    // ============================================================================

    /**
     * Test that hasNemesisTokens returns false when no tokens exist.
     */
    public function test_has_nemesis_tokens_returns_false_when_no_tokens(): void
    {
        // Assert: No tokens exist
        $this->assertFalse($this->testUser->hasNemesisTokens());
    }

    /**
     * Test that hasNemesisTokens returns true when tokens exist.
     */
    public function test_has_nemesis_tokens_returns_true_when_tokens_exist(): void
    {
        // Arrange: Create a token
        $this->testUser->createNemesisToken('Test Token');

        // Assert: Tokens exist
        $this->assertTrue($this->testUser->hasNemesisTokens());
    }

    /**
     * Test that hasNemesisTokens with trashed parameter includes soft deleted tokens.
     */
    public function test_has_nemesis_tokens_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->testUser->createNemesisToken('Test Token');
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

        // Act: Soft delete the token
        $tokenModel->delete();

        // Assert: Without trashed returns false (soft deleted not visible)
        $this->assertFalse($this->testUser->hasNemesisTokens());

        // Assert: With trashed returns true (includes soft deleted)
        $this->assertTrue($this->testUser->hasNemesisTokens(withTrashed: true));
    }

    // ============================================================================
    // Token Retrieval Tests
    // ============================================================================

    /**
     * Test that getNemesisToken retrieves a token by its plain text value.
     */
    public function test_get_nemesis_token_retrieves_token_by_plain_text(): void
    {
        // Arrange: Create a token
        $plainToken = $this->testUser->createNemesisToken('Test Token');

        // Act: Retrieve the token
        $tokenModel = $this->testUser->getNemesisToken($plainToken);

        // Assert: Token is correctly retrieved
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $this->assertEquals('Test Token', $tokenModel->name);
    }

    /**
     * Test that getNemesisToken returns null for an invalid token.
     */
    public function test_get_nemesis_token_returns_null_for_invalid_token(): void
    {
        // Act: Attempt to retrieve an invalid token
        $tokenModel = $this->testUser->getNemesisToken('invalid-token');

        // Assert: Null is returned
        $this->assertNull($tokenModel);
    }

    /**
     * Test that getNemesisToken with trashed parameter includes soft deleted tokens.
     */
    public function test_get_nemesis_token_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->testUser->createNemesisToken('Test Token');
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

        // Act: Soft delete the token
        $tokenModel->delete();

        // Assert: Without trashed returns null
        $this->assertNull($this->testUser->getNemesisToken($plainToken));

        // Assert: With trashed returns the soft deleted token
        $foundToken = $this->testUser->getNemesisToken($plainToken, withTrashed: true);
        $this->assertInstanceOf(NemesisToken::class, $foundToken);
        $this->assertTrue($foundToken->trashed());
    }

    // ============================================================================
    // Token Validation Tests
    // ============================================================================

    /**
     * Test that validateNemesisToken returns true for a valid token.
     */
    public function test_validate_nemesis_token_returns_true_for_valid_token(): void
    {
        // Arrange: Create a valid token with future expiration
        $plainToken = $this->testUser->createNemesisToken('Test Token');
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $tokenModel->save();

        // Assert: Token is valid
        $this->assertTrue($this->testUser->validateNemesisToken($plainToken));
    }

    /**
     * Test that validateNemesisToken returns false for an invalid token.
     */
    public function test_validate_nemesis_token_returns_false_for_invalid_token(): void
    {
        // Assert: Invalid token returns false
        $this->assertFalse($this->testUser->validateNemesisToken('invalid-token'));
    }

    /**
     * Test that validateNemesisToken returns false for a revoked token.
     */
    public function test_validate_nemesis_token_returns_false_for_revoked_token(): void
    {
        // Arrange: Create a token and revoke it
        $plainToken = $this->testUser->createNemesisToken('Test Token');
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $tokenModel->save();
        $tokenModel->delete();

        // Assert: Revoked token is invalid
        $this->assertFalse($this->testUser->validateNemesisToken($plainToken));
    }

    /**
     * Test that validateNemesisToken with includeRevoked includes revoked tokens.
     */
    public function test_validate_nemesis_token_with_include_revoked_includes_revoked_tokens(): void
    {
        // Arrange: Create a token with future expiration and soft delete it
        $plainToken = $this->testUser->createNemesisToken('Test Token');
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $tokenModel->save();
        $tokenModel->delete();

        // Assert: With includeRevoked returns true (token exists and is not expired)
        $this->assertTrue($this->testUser->validateNemesisToken($plainToken, includeRevoked: true));
    }

    /**
     * Test that validateNemesisToken with includeRevoked returns false for expired revoked tokens.
     */
    public function test_validate_nemesis_token_with_include_revoked_returns_false_for_expired_revoked_token(): void
    {
        // Arrange: Create a token with past expiration and soft delete it
        $plainToken = $this->testUser->createNemesisToken('Test Token');
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->subDay();
        $tokenModel->save();
        $tokenModel->delete();

        // Assert: With includeRevoked returns false (token is expired)
        $this->assertFalse($this->testUser->validateNemesisToken($plainToken, includeRevoked: true));
    }

    // ============================================================================
    // Token Touch Tests
    // ============================================================================

    /**
     * Test that touchNemesisToken updates last_used_at and returns true.
     */
    public function test_touch_nemesis_token_updates_last_used_at_and_returns_true(): void
    {
        // Arrange: Create a token and store its original last_used_at
        $plainToken = $this->testUser->createNemesisToken('Test Token');
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $originalLastUsed = $tokenModel->last_used_at;

        // Arrange: Advance time by one hour
        Carbon::setTestNow(now()->addHour());

        // Act: Touch the token
        $result = $this->testUser->touchNemesisToken($plainToken);

        // Assert: Method returns true and last_used_at was updated
        $this->assertTrue($result);
        $tokenModel->refresh();
        $this->assertNotEquals($originalLastUsed, $tokenModel->last_used_at);
    }

    /**
     * Test that touchNemesisToken returns false for an invalid token.
     */
    public function test_touch_nemesis_token_returns_false_for_invalid_token(): void
    {
        // Act: Attempt to touch an invalid token
        $result = $this->testUser->touchNemesisToken('invalid-token');

        // Assert: Method returns false because token was not found
        $this->assertFalse($result);
    }

    // ============================================================================
    // Token Source Filtering Tests
    // ============================================================================

    /**
     * Test that getNemesisTokensBySource returns tokens filtered by source.
     */
    public function test_get_nemesis_tokens_by_source_returns_filtered_tokens(): void
    {
        // Arrange: Create tokens with different sources
        $this->testUser->createNemesisToken('Web Token', 'web');
        $this->testUser->createNemesisToken('Mobile Token', 'mobile');
        $this->testUser->createNemesisToken('API Token', 'api');

        // Act: Filter tokens by source
        $webTokens = $this->testUser->getNemesisTokensBySource('web');
        $mobileTokens = $this->testUser->getNemesisTokensBySource('mobile');

        // Assert: Only tokens with matching source are returned
        $this->assertCount(1, $webTokens);
        $this->assertEquals('Web Token', $webTokens->first()->name);
        $this->assertCount(1, $mobileTokens);
        $this->assertEquals('Mobile Token', $mobileTokens->first()->name);
    }

    /**
     * Test that getNemesisTokensBySource with trashed includes soft deleted tokens.
     */
    public function test_get_nemesis_tokens_by_source_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create tokens with same source
        $this->testUser->createNemesisToken('Web Token 1', 'web');
        $this->testUser->createNemesisToken('Web Token 2', 'web');

        // Arrange: Get the second token and revoke it
        $tokens = $this->testUser->getNemesisTokensBySource('web');
        $this->assertCount(2, $tokens);
        $secondToken = $tokens->last();
        $secondToken->delete();

        // Act: Get tokens by source without trashed
        $webTokensWithoutTrashed = $this->testUser->getNemesisTokensBySource('web');

        // Assert: Without trashed returns only non-deleted tokens
        $this->assertCount(1, $webTokensWithoutTrashed);
        $this->assertEquals('Web Token 1', $webTokensWithoutTrashed->first()->name);

        // Act: Get tokens by source with trashed
        $webTokensWithTrashed = $this->testUser->getNemesisTokensBySource('web', withTrashed: true);

        // Assert: With trashed returns all tokens (including soft deleted)
        $this->assertCount(2, $webTokensWithTrashed);
    }

    // ============================================================================
    // Expired Token Revocation Tests
    // ============================================================================

    /**
     * Test that revokeExpiredNemesisTokens soft deletes expired tokens only.
     */
    public function test_revoke_expired_nemesis_tokens_soft_deletes_expired_tokens(): void
    {
        // Arrange: Create expired and valid tokens
        $expiredPlainToken = $this->testUser->createNemesisToken('Expired Token');
        $validPlainToken = $this->testUser->createNemesisToken('Valid Token');

        // Arrange: Expire the first token
        $expiredTokenModel = $this->testUser->getNemesisToken($expiredPlainToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $expiredTokenModel->save();

        // Arrange: Set valid token to future expiration
        $validTokenModel = $this->testUser->getNemesisToken($validPlainToken);
        $validTokenModel->expires_at = now()->addDays(10);
        $validTokenModel->save();

        // Act: Revoke expired tokens
        $revokedCount = $this->testUser->revokeExpiredNemesisTokens();

        // Assert: Only expired token is soft deleted
        $this->assertSame(1, $revokedCount);

        // Assert: Expired token is soft deleted
        $deletedToken = $this->testUser->getNemesisToken($expiredPlainToken, withTrashed: true);
        $this->assertInstanceOf(NemesisToken::class, $deletedToken);
        $this->assertTrue($deletedToken->trashed());

        // Assert: Valid token remains active
        $validToken = $this->testUser->getNemesisToken($validPlainToken);
        $this->assertInstanceOf(NemesisToken::class, $validToken);
        $this->assertFalse($validToken->trashed());
    }

    /**
     * Test that revokeExpiredNemesisTokens does not affect valid tokens.
     */
    public function test_revoke_expired_nemesis_tokens_does_not_affect_valid_tokens(): void
    {
        // Arrange: Create a valid token with future expiration
        $validPlainToken = $this->testUser->createNemesisToken('Valid Token');
        $validTokenModel = $this->testUser->getNemesisToken($validPlainToken);
        $validTokenModel->expires_at = now()->addDays(10);
        $validTokenModel->save();

        // Act: Revoke expired tokens
        $revokedCount = $this->testUser->revokeExpiredNemesisTokens();

        // Assert: No tokens were deleted
        $this->assertSame(0, $revokedCount);
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($validPlainToken));
        $this->assertFalse($validTokenModel->fresh()->trashed());
    }

    // ============================================================================
    // Expired Token Permanent Deletion Tests
    // ============================================================================

    /**
     * Test that forceDeleteExpiredNemesisTokens permanently deletes expired tokens.
     */
    public function test_force_delete_expired_nemesis_tokens_permanently_deletes_expired_tokens(): void
    {
        // Arrange: Create expired and valid tokens
        $expiredPlainToken = $this->testUser->createNemesisToken('Expired Token');
        $validPlainToken = $this->testUser->createNemesisToken('Valid Token');

        // Arrange: Expire the first token
        $expiredTokenModel = $this->testUser->getNemesisToken($expiredPlainToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $expiredTokenModel->save();

        // Act: Permanently delete expired tokens
        $deletedCount = $this->testUser->forceDeleteExpiredNemesisTokens();

        // Assert: Only expired token is permanently deleted
        $this->assertSame(1, $deletedCount);
        $this->assertNull($this->testUser->getNemesisToken($expiredPlainToken, withTrashed: true));
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($validPlainToken));
    }

    // ============================================================================
    // Token Restoration Tests
    // ============================================================================

    /**
     * Test that restoreNemesisTokens restores all revoked tokens.
     */
    public function test_restore_nemesis_tokens_restores_all_revoked_tokens(): void
    {
        // Arrange: Create multiple tokens
        $token1 = $this->testUser->createNemesisToken('Token 1');
        $token2 = $this->testUser->createNemesisToken('Token 2');

        // Arrange: Soft delete both tokens
        $tokenModel1 = $this->testUser->getNemesisToken($token1);
        $tokenModel2 = $this->testUser->getNemesisToken($token2);
        $tokenModel1->delete();
        $tokenModel2->delete();

        // Arrange: Verify they are soft deleted
        $this->assertEquals(0, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(2, $this->testUser->nemesisTokens()->withTrashed()->count());

        // Act: Restore all tokens
        $restoredCount = $this->testUser->restoreNemesisTokens();

        // Assert: All tokens are restored
        $this->assertSame(2, $restoredCount);
        $this->assertEquals(2, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(0, $this->testUser->nemesisTokens()->onlyTrashed()->count());
    }

    /**
     * Test that restoreNemesisTokens returns zero when no revoked tokens exist.
     */
    public function test_restore_nemesis_tokens_returns_zero_when_no_revoked_tokens(): void
    {
        // Arrange: Create valid tokens (not deleted)
        $token1 = $this->testUser->createNemesisToken('Token 1');
        $token2 = $this->testUser->createNemesisToken('Token 2');

        // Arrange: Verify both tokens are active
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($token1));
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($token2));
        $this->assertEquals(2, $this->testUser->nemesisTokens()->count());

        // Act: Try to restore tokens (nothing to restore)
        $restoredCount = $this->testUser->restoreNemesisTokens();

        // Assert: No tokens were restored
        $this->assertSame(0, $restoredCount);

        // Assert: Tokens are still active
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($token1));
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($token2));
        $this->assertEquals(2, $this->testUser->nemesisTokens()->count());
    }

    /**
     * Test that restoreNemesisTokens restores only revoked tokens while keeping valid tokens.
     */
    public function test_can_restore_only_revoked_tokens_while_keeping_valid_tokens(): void
    {
        // Arrange: Create valid and revoked tokens
        $validPlainToken = $this->testUser->createNemesisToken('Valid Token');
        $revokedPlainToken = $this->testUser->createNemesisToken('Revoked Token');

        // Arrange: Verify both tokens are active initially
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($validPlainToken));
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($revokedPlainToken));

        // Arrange: Revoke only the second token
        $revokedTokenModel = $this->testUser->getNemesisToken($revokedPlainToken);
        $revokedTokenModel->delete();

        // Arrange: Verify state: 1 active, 1 soft deleted
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($validPlainToken));
        $this->assertNull($this->testUser->getNemesisToken($revokedPlainToken));
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($revokedPlainToken, withTrashed: true));
        $this->assertEquals(1, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(1, $this->testUser->nemesisTokens()->onlyTrashed()->count());

        // Act: Restore all tokens
        $restoredCount = $this->testUser->restoreNemesisTokens();

        // Assert: Only the revoked token was restored
        $this->assertSame(1, $restoredCount);

        // Assert: Both tokens are now active
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($validPlainToken));
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($revokedPlainToken));
        $this->assertEquals(2, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(0, $this->testUser->nemesisTokens()->onlyTrashed()->count());
    }

    // ============================================================================
    // Combined Soft Delete Operation Tests
    // ============================================================================

    /**
     * Test that revoke then restore tokens works correctly.
     */
    public function test_revoke_then_restore_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->testUser->createNemesisToken('Test Token');

        // Act: Revoke the token
        $this->testUser->revokeNemesisTokens();

        // Assert: Token is soft deleted
        $this->assertNull($this->testUser->getNemesisToken($plainToken));
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($plainToken, withTrashed: true));

        // Act: Restore the token
        $this->testUser->restoreNemesisTokens();

        // Assert: Token is restored
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($plainToken));
        $this->assertFalse($this->testUser->getNemesisToken($plainToken)->trashed());
    }

    /**
     * Test that revoke then permanently delete tokens works correctly.
     */
    public function test_revoke_then_permanently_delete_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->testUser->createNemesisToken('Test Token');

        // Act: Revoke the token (soft delete)
        $this->testUser->revokeNemesisTokens();

        // Assert: Token is soft deleted
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($plainToken, withTrashed: true));

        // Act: Permanently delete all tokens
        $this->testUser->deleteNemesisTokens();

        // Assert: Token is permanently deleted
        $this->assertNull($this->testUser->getNemesisToken($plainToken, withTrashed: true));
    }

    // ============================================================================
    // Token Revocation by Source/Name Tests
    // ============================================================================

    /**
     * Test that revokeNemesisTokensBySource revokes only tokens with matching source.
     */
    public function test_revoke_nemesis_tokens_by_source_revokes_only_matching_source(): void
    {
        // Arrange: Create tokens with different sources
        $this->testUser->createNemesisToken('Web Token 1', 'web');
        $this->testUser->createNemesisToken('Web Token 2', 'web');
        $mobileToken = $this->testUser->createNemesisToken('Mobile Token', 'mobile');
        $apiToken = $this->testUser->createNemesisToken('API Token', 'api');

        // Act: Revoke all web tokens
        $revokedCount = $this->testUser->revokeNemesisTokensBySource('web');

        // Assert: Only web tokens are revoked
        $this->assertEquals(2, $revokedCount);

        // Web tokens should be soft deleted
        $webTokens = $this->testUser->getNemesisTokensBySource('web', withTrashed: true);
        $this->assertCount(2, $webTokens);
        foreach ($webTokens as $token) {
            $this->assertTrue($token->trashed());
        }

        // Mobile token should remain active
        $mobileTokenModel = $this->testUser->getNemesisToken($mobileToken);
        $this->assertInstanceOf(NemesisToken::class, $mobileTokenModel);
        $this->assertFalse($mobileTokenModel->trashed());

        // API token should remain active
        $apiTokenModel = $this->testUser->getNemesisToken($apiToken);
        $this->assertInstanceOf(NemesisToken::class, $apiTokenModel);
        $this->assertFalse($apiTokenModel->trashed());
    }

    /**
     * Test that revokeNemesisTokensByName revokes only tokens with matching name.
     */
    public function test_revoke_nemesis_tokens_by_name_revokes_only_matching_name(): void
    {
        // Arrange: Create tokens with different names
        $this->testUser->createNemesisToken('web_session', 'web');
        $this->testUser->createNemesisToken('web_session', 'mobile');
        $this->testUser->createNemesisToken('api_key', 'api');

        // Act: Revoke all 'web_session' tokens
        $revokedCount = $this->testUser->revokeNemesisTokensByName('web_session');

        // Assert: Both web and mobile 'web_session' tokens are revoked
        $this->assertEquals(2, $revokedCount);

        // 'api_key' token should remain active
        $apiKeyToken = $this->testUser->getNemesisTokensBySource('api')->first();
        $this->assertInstanceOf(NemesisToken::class, $apiKeyToken);
        $this->assertFalse($apiKeyToken->trashed());
    }

    /**
     * Test that revokeNemesisTokensBySourceAndName revokes only specific tokens.
     */
    public function test_revoke_nemesis_tokens_by_source_and_name_revokes_specific_tokens(): void
    {
        // Arrange: Create various tokens
        $this->testUser->createNemesisToken('web_session', 'web');
        $this->testUser->createNemesisToken('web_session', 'mobile');
        $this->testUser->createNemesisToken('web_admin', 'web');
        $mobileToken = $this->testUser->createNemesisToken('mobile_session', 'mobile');

        // Act: Revoke only web_session tokens from web source
        $revokedCount = $this->testUser->revokeNemesisTokensBySourceAndName('web', 'web_session');

        // Assert: Only one token revoked
        $this->assertEquals(1, $revokedCount);

        // Other tokens should remain active
        $allActiveTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(3, $allActiveTokens->count());

        // Mobile token should be active
        $mobileTokenModel = $this->testUser->getNemesisToken($mobileToken);
        $this->assertInstanceOf(NemesisToken::class, $mobileTokenModel);
        $this->assertFalse($mobileTokenModel->trashed());
    }

    /**
     * Test that revokeAllNemesisTokensExceptSource keeps specified source tokens.
     */
    public function test_revoke_all_nemesis_tokens_except_source_keeps_specified_source(): void
    {
        // Arrange: Create tokens with different sources
        $webToken1 = $this->testUser->createNemesisToken('Web Token 1', 'web');
        $webToken2 = $this->testUser->createNemesisToken('Web Token 2', 'web');
        $mobileToken = $this->testUser->createNemesisToken('Mobile Token', 'mobile');
        $apiToken = $this->testUser->createNemesisToken('API Token', 'api');

        // Act: Revoke all tokens except mobile
        $revokedCount = $this->testUser->revokeAllNemesisTokensExceptSource('mobile');

        // Assert: 3 tokens revoked (2 web + 1 api)
        $this->assertEquals(3, $revokedCount);

        // Only mobile token remains active
        $activeTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(1, $activeTokens->count());
        $this->assertEquals('Mobile Token', $activeTokens->first()->name);

        // Mobile token should be active
        $mobileTokenModel = $this->testUser->getNemesisToken($mobileToken);
        $this->assertInstanceOf(NemesisToken::class, $mobileTokenModel);
        $this->assertFalse($mobileTokenModel->trashed());
    }

    /**
     * Test that revokeNemesisTokensWhere with custom criteria works.
     */
    public function test_revoke_nemesis_tokens_where_with_custom_criteria(): void
    {
        // Arrange: Create tokens with different names
        $tokenToRevoke = $this->testUser->createNemesisToken('token_to_revoke', 'web');
        $tokenToKeep = $this->testUser->createNemesisToken('token_to_keep', 'web');
        $anotherToken = $this->testUser->createNemesisToken('another_token', 'web');

        // Act: Revoke tokens with specific name
        $revokedCount = $this->testUser->revokeNemesisTokensWhere([
            'name' => 'token_to_revoke'
        ]);

        // Assert: Only matching token is revoked
        $this->assertEquals(1, $revokedCount);

        // Get the token models using the actual token values
        $revokedToken = $this->testUser->getNemesisToken($tokenToRevoke, withTrashed: true);
        $keptToken = $this->testUser->getNemesisToken($tokenToKeep);
        $anotherKeptToken = $this->testUser->getNemesisToken($anotherToken);

        $this->assertNotNull($revokedToken);
        $this->assertNotNull($keptToken);
        $this->assertNotNull($anotherKeptToken);

        $this->assertTrue($revokedToken->trashed());
        $this->assertFalse($keptToken->trashed());
        $this->assertFalse($anotherKeptToken->trashed());

        // Verify the revoked token has the correct name
        $this->assertEquals('token_to_revoke', $revokedToken->name);
        $this->assertEquals('token_to_keep', $keptToken->name);
        $this->assertEquals('another_token', $anotherKeptToken->name);
    }

    /**
     * Test real-world scenario: logout from all browsers but keep mobile app.
     */
    public function test_real_world_scenario_logout_from_all_browsers_keep_mobile(): void
    {
        // Arrange: Simulate user with multiple sessions
        // User is logged in on 3 different browsers (web_session tokens)
        $browserTokens = [];
        for ($i = 1; $i <= 3; $i++) {
            $browserTokens[] = $this->testUser->createNemesisToken(
                name: 'web_session',
                source: 'web'
            );
        }

        // User also has a mobile app token
        $mobileToken = $this->testUser->createNemesisToken(
            name: 'mobile_session',
            source: 'mobile'
        );

        // User also has an API token for integrations
        $apiToken = $this->testUser->createNemesisToken(
            name: 'api_key',
            source: 'api'
        );

        // Act: User clicks "Logout from all browsers"
        $revokedCount = $this->testUser->revokeNemesisTokensBySource('web');

        // Assert: Only web tokens were revoked
        $this->assertEquals(3, $revokedCount);

        // All web tokens should be invalid/revoked
        foreach ($browserTokens as $browserToken) {
            $this->assertFalse($this->testUser->validateNemesisToken($browserToken));
            $token = $this->testUser->getNemesisToken($browserToken, withTrashed: true);
            $this->assertTrue($token->trashed());
        }

        // Mobile token should still be valid
        $this->assertTrue($this->testUser->validateNemesisToken($mobileToken));

        // API token should still be valid
        $this->assertTrue($this->testUser->validateNemesisToken($apiToken));

        // Active tokens count should be 2 (mobile + api)
        $activeTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(2, $activeTokens->count());
    }

    /**
     * Test force delete variants.
     */
    public function test_force_delete_variants_permanently_delete_tokens(): void
    {
        // Arrange: Create tokens
        $webToken = $this->testUser->createNemesisToken('Web Token', 'web');
        $mobileToken = $this->testUser->createNemesisToken('Mobile Token', 'mobile');

        // Act: Force delete web tokens by source
        $deletedCount = $this->testUser->revokeNemesisTokensBySource('web', force: true);

        // Assert: Web token is permanently deleted
        $this->assertEquals(1, $deletedCount);
        $this->assertNull($this->testUser->getNemesisToken($webToken, withTrashed: true));

        // Mobile token remains
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($mobileToken));
    }

    /**
     * Test that revokeNemesisTokensBySource returns 0 when no matching tokens exist.
     */
    public function test_revoke_nemesis_tokens_by_source_returns_zero_when_no_matching_tokens(): void
    {
        // Arrange: Create only mobile token
        $this->testUser->createNemesisToken('Mobile Token', 'mobile');

        // Act: Try to revoke web tokens
        $revokedCount = $this->testUser->revokeNemesisTokensBySource('web');

        // Assert: No tokens were revoked
        $this->assertEquals(0, $revokedCount);

        // Mobile token still active
        $this->assertEquals(1, $this->testUser->nemesisTokens()->count());
    }

    /**
     * Test chaining revoke operations.
     */
    public function test_chaining_revoke_operations(): void
    {
        // Arrange: Create multiple tokens
        $this->testUser->createNemesisToken('web_session', 'web');
        $this->testUser->createNemesisToken('web_admin', 'web');
        $this->testUser->createNemesisToken('mobile_session', 'mobile');
        $this->testUser->createNemesisToken('api_key', 'api');

        // Act: First revoke all web tokens, then revoke mobile tokens
        $webRevoked = $this->testUser->revokeNemesisTokensBySource('web');
        $mobileRevoked = $this->testUser->revokeNemesisTokensBySource('mobile');

        // Assert: Correct counts
        $this->assertEquals(2, $webRevoked);
        $this->assertEquals(1, $mobileRevoked);

        // Only API token remains active
        $activeTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(1, $activeTokens->count());
        $this->assertEquals('api', $activeTokens->first()->source);
    }

    // ============================================================================
    // Helper Methods
    // ============================================================================

    /**
     * Set the bearer token in the request header for authentication simulation.
     *
     * @param string $token The token to set in the Authorization header
     */
    private function withBearerToken(string $token): void
    {
        $this->app['request']->headers->set('Authorization', 'Bearer ' . $token);
    }
}
