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
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Set a fixed time for consistent testing
        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        // Arrange: Create a fresh test user for each test
        $this->user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up: Delete all tokens created during the test
        if ($this->user instanceof TestUser && $this->user->exists) {
            $this->user->nemesisTokens()->forceDelete();
        }

        Carbon::setTestNow();
        parent::tearDown();
    }

    // ============================================================================
    // Tests for createNemesisToken()
    // ============================================================================

    public function test_create_nemesis_token_creates_new_token(): void
    {
        $name = 'API Token';
        $source = 'api';
        $abilities = ['read', 'write'];

        $plainToken = $this->user->createNemesisToken($name, $source, $abilities);

        $this->assertIsString($plainToken);
        $this->assertSame(64, strlen($plainToken));

        $tokenModel = $this->user->getNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $this->assertEquals($name, $tokenModel->name);
        $this->assertEquals($source, $tokenModel->source);
        $this->assertEquals($abilities, $tokenModel->abilities);
    }

    public function test_create_nemesis_token_validates_and_sanitizes_metadata(): void
    {
        $metadata = ['keep' => 'value', 'remove' => null, 'nested' => ['keep' => 'data', 'empty' => []]];

        $plainToken = $this->user->createNemesisToken(
            name: 'Test Token',
            metadata: $metadata
        );

        $tokenModel = $this->user->getNemesisToken($plainToken);
        $this->assertEquals(['keep' => 'value', 'nested' => ['keep' => 'data']], $tokenModel->metadata);
    }

    // ============================================================================
    // Tests for deleteNemesisTokens() and revokeNemesisTokens()
    // ============================================================================

    public function test_delete_nemesis_tokens_permanently_deletes_all_tokens(): void
    {
        $this->user->createNemesisToken('Token 1');
        $this->user->createNemesisToken('Token 2');
        $this->assertEquals(2, $this->user->nemesisTokens()->count());

        $deletedCount = $this->user->deleteNemesisTokens();

        $this->assertSame(2, $deletedCount);
        $this->assertEquals(0, $this->user->nemesisTokens()->count());
        $this->assertEquals(0, $this->user->nemesisTokens()->withTrashed()->count());
    }

    public function test_revoke_nemesis_tokens_soft_deletes_all_tokens(): void
    {
        $this->user->createNemesisToken('Token 1');
        $this->user->createNemesisToken('Token 2');
        $this->assertEquals(2, $this->user->nemesisTokens()->count());

        $revokedCount = $this->user->revokeNemesisTokens();

        $this->assertSame(2, $revokedCount);
        $this->assertEquals(0, $this->user->nemesisTokens()->count());
        $this->assertEquals(2, $this->user->nemesisTokens()->withTrashed()->count());
    }

    // ============================================================================
    // Tests for deleteCurrentNemesisToken() and revokeCurrentNemesisToken()
    // ============================================================================

    public function test_delete_current_nemesis_token_permanently_deletes_current_token(): void
    {
        $plainToken = $this->user->createNemesisToken('Current Token');
        $this->withBearerToken($plainToken);

        $this->assertInstanceOf(NemesisToken::class, $this->user->currentNemesisToken());

        $this->user->deleteCurrentNemesisToken();

        $this->assertNotInstanceOf(NemesisToken::class, $this->user->currentNemesisToken());
        $this->assertEquals(0, $this->user->nemesisTokens()->withTrashed()->count());
    }

    public function test_revoke_current_nemesis_token_soft_deletes_current_token(): void
    {
        $plainToken = $this->user->createNemesisToken('Current Token');
        $this->withBearerToken($plainToken);

        $this->assertInstanceOf(NemesisToken::class, $this->user->currentNemesisToken());

        $this->user->revokeCurrentNemesisToken();

        $this->assertNotInstanceOf(NemesisToken::class, $this->user->currentNemesisToken());
        $this->assertEquals(1, $this->user->nemesisTokens()->withTrashed()->count());
    }

    // ============================================================================
    // Tests for hasNemesisTokens()
    // ============================================================================

    public function test_has_nemesis_tokens_returns_false_when_no_tokens(): void
    {
        $this->assertFalse($this->user->hasNemesisTokens());
    }

    public function test_has_nemesis_tokens_returns_true_when_tokens_exist(): void
    {
        $this->user->createNemesisToken('Test Token');
        $this->assertTrue($this->user->hasNemesisTokens());
    }

    public function test_has_nemesis_tokens_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

        // Act: Soft delete the token
        $tokenModel->delete();

        // Assert: Without trashed returns false (soft deleted not visible)
        $this->assertFalse($this->user->hasNemesisTokens());

        // Assert: With trashed returns true (includes soft deleted)
        $this->assertTrue($this->user->hasNemesisTokens(withTrashed: true));
    }

    // ============================================================================
    // Tests for getNemesisToken()
    // ============================================================================

    public function test_get_nemesis_token_retrieves_token_by_plain_text(): void
    {
        $plainToken = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($plainToken);

        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $this->assertEquals('Test Token', $tokenModel->name);
    }

    public function test_get_nemesis_token_returns_null_for_invalid_token(): void
    {
        $tokenModel = $this->user->getNemesisToken('invalid-token');
        $this->assertNotInstanceOf(NemesisToken::class, $tokenModel);
    }

    public function test_get_nemesis_token_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

        // Act: Soft delete the token
        $tokenModel->delete();

        // Assert: Without trashed returns null
        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($plainToken));

        // Assert: With trashed returns the soft deleted token
        $foundToken = $this->user->getNemesisToken($plainToken, withTrashed: true);
        $this->assertInstanceOf(NemesisToken::class, $foundToken);
        $this->assertTrue($foundToken->trashed());
    }

    // ============================================================================
    // Tests for validateNemesisToken()
    // ============================================================================

    public function test_validate_nemesis_token_returns_true_for_valid_token(): void
    {
        $plainToken = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $tokenModel->save();

        $this->assertTrue($this->user->validateNemesisToken($plainToken));
    }

    public function test_validate_nemesis_token_returns_false_for_invalid_token(): void
    {
        $this->assertFalse($this->user->validateNemesisToken('invalid-token'));
    }

    public function test_validate_nemesis_token_returns_false_for_revoked_token(): void
    {
        $plainToken = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $tokenModel->save();
        $tokenModel->delete();

        $this->assertFalse($this->user->validateNemesisToken($plainToken));
    }

    public function test_validate_nemesis_token_with_include_revoked_includes_revoked_tokens(): void
    {
        // Arrange: Create a token with future expiration and soft delete it
        $plainToken = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $tokenModel->save();
        $tokenModel->delete();

        // Assert: With includeRevoked returns true (token exists and is not expired)
        $this->assertTrue($this->user->validateNemesisToken($plainToken, includeRevoked: true));
    }

    public function test_validate_nemesis_token_with_include_revoked_returns_false_for_expired_revoked_token(): void
    {
        // Arrange: Create a token with past expiration and soft delete it
        $plainToken = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->subDay();
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $tokenModel->save();
        $tokenModel->delete();

        // Assert: With includeRevoked returns false (token is expired)
        $this->assertFalse($this->user->validateNemesisToken($plainToken, includeRevoked: true));
    }

    // ============================================================================
    // Tests for touchNemesisToken()
    // ============================================================================

    public function test_touch_nemesis_token_updates_last_used_at(): void
    {
        $plainToken = $this->user->createNemesisToken('Test Token');
        $tokenModel = $this->user->getNemesisToken($plainToken);
        $originalLastUsed = $tokenModel->last_used_at;

        Carbon::setTestNow(now()->addHour());

        $this->user->touchNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

        $tokenModel->refresh();
        $this->assertNotEquals($originalLastUsed, $tokenModel->last_used_at);
    }

    // ============================================================================
    // Tests for getNemesisTokensBySource()
    // ============================================================================

    public function test_get_nemesis_tokens_by_source_returns_filtered_tokens(): void
    {
        $this->user->createNemesisToken('Web Token', 'web');
        $this->user->createNemesisToken('Mobile Token', 'mobile');
        $this->user->createNemesisToken('API Token', 'api');

        $webTokens = $this->user->getNemesisTokensBySource('web');
        $mobileTokens = $this->user->getNemesisTokensBySource('mobile');

        $this->assertCount(1, $webTokens);
        $this->assertEquals('Web Token', $webTokens->first()->name);
        $this->assertCount(1, $mobileTokens);
        $this->assertEquals('Mobile Token', $mobileTokens->first()->name);
    }

    public function test_get_nemesis_tokens_by_source_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create tokens with same source
        $this->user->createNemesisToken('Web Token 1', 'web');
        $this->user->createNemesisToken('Web Token 2', 'web');

        // Get the second token and revoke it
        $tokens = $this->user->getNemesisTokensBySource('web');
        $this->assertCount(2, $tokens);

        $secondToken = $tokens->last();
        $secondToken->delete();

        // Act: Get tokens by source without trashed
        $webTokensWithoutTrashed = $this->user->getNemesisTokensBySource('web');

        // Assert: Without trashed returns only non-deleted (1 token)
        $this->assertCount(1, $webTokensWithoutTrashed);
        $this->assertEquals('Web Token 1', $webTokensWithoutTrashed->first()->name);

        // Act: Get tokens by source with trashed
        $webTokensWithTrashed = $this->user->getNemesisTokensBySource('web', withTrashed: true);

        // Assert: With trashed returns all tokens (including soft deleted)
        $this->assertCount(2, $webTokensWithTrashed);
    }

    // ============================================================================
    // Tests for revokeExpiredNemesisTokens()
    // ============================================================================

    public function test_revoke_expired_nemesis_tokens_soft_deletes_expired_tokens(): void
    {
        // Arrange: Create expired and valid tokens
        $expiredPlainToken = $this->user->createNemesisToken('Expired Token');
        $validPlainToken = $this->user->createNemesisToken('Valid Token');

        // Expire the first token
        $expiredTokenModel = $this->user->getNemesisToken($expiredPlainToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
        $expiredTokenModel->save();

        // Set valid token to future expiration
        $validTokenModel = $this->user->getNemesisToken($validPlainToken);
        $validTokenModel->expires_at = now()->addDays(10);
        $this->assertInstanceOf(NemesisToken::class, $validTokenModel);
        $validTokenModel->save();

        // Act: Revoke expired tokens
        $revokedCount = $this->user->revokeExpiredNemesisTokens();

        // Assert: Only expired token is soft deleted
        $this->assertSame(1, $revokedCount);

        // Verify expired token is soft deleted
        $deletedToken = $this->user->getNemesisToken($expiredPlainToken, withTrashed: true);
        $this->assertInstanceOf(NemesisToken::class, $deletedToken);
        $this->assertTrue($deletedToken->trashed());

        // Verify valid token still exists and is not soft deleted
        $validToken = $this->user->getNemesisToken($validPlainToken);
        $this->assertInstanceOf(NemesisToken::class, $validToken);
        $this->assertFalse($validToken->trashed());
    }

    public function test_revoke_expired_nemesis_tokens_does_not_affect_valid_tokens(): void
    {
        // Arrange: Create a valid token
        $validPlainToken = $this->user->createNemesisToken('Valid Token');
        $validTokenModel = $this->user->getNemesisToken($validPlainToken);
        $validTokenModel->expires_at = now()->addDays(10);
        $this->assertInstanceOf(NemesisToken::class, $validTokenModel);
        $validTokenModel->save();

        // Act: Revoke expired tokens
        $revokedCount = $this->user->revokeExpiredNemesisTokens();

        // Assert: No tokens were deleted
        $this->assertSame(0, $revokedCount);
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($validPlainToken));
        $this->assertFalse($validTokenModel->fresh()->trashed());
    }

    // ============================================================================
    // Tests for forceDeleteExpiredNemesisTokens()
    // ============================================================================

    public function test_force_delete_expired_nemesis_tokens_permanently_deletes_expired_tokens(): void
    {
        $expiredPlainToken = $this->user->createNemesisToken('Expired Token');
        $validPlainToken = $this->user->createNemesisToken('Valid Token');

        $expiredTokenModel = $this->user->getNemesisToken($expiredPlainToken);
        $expiredTokenModel->expires_at = now()->subDay();
        $this->assertInstanceOf(NemesisToken::class, $expiredTokenModel);
        $expiredTokenModel->save();

        $deletedCount = $this->user->forceDeleteExpiredNemesisTokens();

        $this->assertSame(1, $deletedCount);
        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($expiredPlainToken, withTrashed: true));
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($validPlainToken));
    }

    // ============================================================================
    // Tests for restoreNemesisTokens()
    // ============================================================================

    public function test_restore_nemesis_tokens_restores_all_revoked_tokens(): void
    {
        // Arrange: Create multiple tokens
        $token1 = $this->user->createNemesisToken('Token 1');
        $token2 = $this->user->createNemesisToken('Token 2');

        // Soft delete both tokens
        $tokenModel1 = $this->user->getNemesisToken($token1);
        $tokenModel2 = $this->user->getNemesisToken($token2);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel1);
        $tokenModel1->delete();
        $this->assertInstanceOf(NemesisToken::class, $tokenModel2);
        $tokenModel2->delete();

        // Verify they are soft deleted
        $this->assertEquals(0, $this->user->nemesisTokens()->count());
        $this->assertEquals(2, $this->user->nemesisTokens()->withTrashed()->count());

        // Act: Restore all tokens
        $restoredCount = $this->user->restoreNemesisTokens();

        // Assert: All tokens should be restored
        $this->assertSame(2, $restoredCount);
        $this->assertEquals(2, $this->user->nemesisTokens()->count());
        $this->assertEquals(0, $this->user->nemesisTokens()->onlyTrashed()->count());
    }

    public function test_restore_nemesis_tokens_returns_zero_when_no_revoked_tokens(): void
    {
        // Arrange: Create valid tokens (not deleted)
        $token1 = $this->user->createNemesisToken('Token 1');
        $token2 = $this->user->createNemesisToken('Token 2');

        // Verify both tokens are active
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($token1));
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($token2));
        $this->assertEquals(2, $this->user->nemesisTokens()->count());

        // Act: Try to restore tokens (nothing to restore)
        $restoredCount = $this->user->restoreNemesisTokens();

        // Assert: No tokens were restored (restore returns 0 when nothing to restore)
        $this->assertSame(0, $restoredCount);

        // Verify tokens still exist and are still active
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($token1));
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($token2));
        $this->assertEquals(2, $this->user->nemesisTokens()->count());
    }

    public function test_can_restore_only_revoked_tokens_while_keeping_valid_tokens(): void
    {
        // Arrange: Create tokens - one valid, one revoked
        $validPlainToken = $this->user->createNemesisToken('Valid Token');
        $revokedPlainToken = $this->user->createNemesisToken('Revoked Token');

        // Verify both tokens are active initially
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($validPlainToken));
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($revokedPlainToken));
        $this->assertEquals(2, $this->user->nemesisTokens()->count());

        // Revoke only the second token
        $revokedTokenModel = $this->user->getNemesisToken($revokedPlainToken);
        $this->assertInstanceOf(NemesisToken::class, $revokedTokenModel);
        $revokedTokenModel->delete();

        // Verify state: 1 active token (valid), 1 revoked (soft deleted)
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($validPlainToken));
        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($revokedPlainToken));
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($revokedPlainToken, withTrashed: true));
        $this->assertEquals(1, $this->user->nemesisTokens()->count());
        $this->assertEquals(1, $this->user->nemesisTokens()->onlyTrashed()->count());

        // Act: Restore all tokens
        $restoredCount = $this->user->restoreNemesisTokens();

        // Assert: Only the revoked token was restored (1 restored)
        $this->assertSame(1, $restoredCount);

        // Now both tokens should be active (2 active tokens)
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($validPlainToken));
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($revokedPlainToken));
        $this->assertEquals(2, $this->user->nemesisTokens()->count());
        $this->assertEquals(0, $this->user->nemesisTokens()->onlyTrashed()->count());
    }

    // ============================================================================
    // Tests for combined soft delete operations
    // ============================================================================

    public function test_revoke_then_restore_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->user->createNemesisToken('Test Token');

        // Act: Revoke the token
        $this->user->revokeNemesisTokens();

        // Assert: Token is soft deleted
        $this->assertNotInstanceOf(NemesisToken::class, $this->user->getNemesisToken($plainToken));
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($plainToken, withTrashed: true));

        // Act: Restore the token
        $this->user->restoreNemesisTokens();

        // Assert: Token is restored
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($plainToken));
        $this->assertFalse($this->user->getNemesisToken($plainToken)->trashed());
    }

    public function test_revoke_then_permanently_delete_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->user->createNemesisToken('Test Token');

        // Act: Revoke the token (soft delete)
        $this->user->revokeNemesisTokens();

        // Assert: Token is soft deleted
        $this->assertInstanceOf(NemesisToken::class, $this->user->getNemesisToken($plainToken, withTrashed: true));

        // Act: Permanently delete all tokens
        $this->user->deleteNemesisTokens();

        // Assert: Token is permanently deleted
        $this->assertNull($this->user->getNemesisToken($plainToken, withTrashed: true));
    }

    // ============================================================================
    // Helper methods
    // ============================================================================

    private function withBearerToken(string $token): void
    {
        $this->app['request']->headers->set('Authorization', 'Bearer ' . $token);
    }
}
