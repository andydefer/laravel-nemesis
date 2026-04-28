<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Traits;

use Carbon\Carbon;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for the HasNemesisTokens trait.
 *
 * Verifies that the trait provides complete token management functionality
 * including creation, validation, deletion, revocation, restoration,
 * querying, and filtering operations.
 */
final class HasNemesisTokensTest extends TestCase
{
    private const FROZEN_TEST_TIMESTAMP = '2025-01-01 12:00:00';
    private const DEFAULT_TOKEN_NAME = 'Test Token';
    private const DEFAULT_TOKEN_SOURCE = 'web';
    private const MOBILE_TOKEN_SOURCE = 'mobile';
    private const API_TOKEN_SOURCE = 'api';
    private const TOKEN_HASH_LENGTH = 64;

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

    // ==============================================
    // Token Creation Tests
    // ==============================================

    public function test_create_nemesis_token_creates_new_token(): void
    {
        // Arrange: Define token properties
        $tokenName = 'API Token';
        $tokenSource = self::API_TOKEN_SOURCE;
        $tokenAbilities = ['read', 'write'];

        // Act: Create a new token
        $plainToken = $this->testUser->createNemesisToken(
            name: $tokenName,
            source: $tokenSource,
            abilities: $tokenAbilities
        );

        // Assert: Token has correct format (64-character hex string)
        $this->assertIsString($plainToken);
        $this->assertSame(self::TOKEN_HASH_LENGTH, strlen($plainToken));

        // Assert: Token is correctly stored in database
        $storedToken = $this->testUser->getNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $storedToken);
        $this->assertEquals($tokenName, $storedToken->name);
        $this->assertEquals($tokenSource, $storedToken->source);
        $this->assertEquals($tokenAbilities, $storedToken->abilities);
    }

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
            name: self::DEFAULT_TOKEN_NAME,
            metadata: $rawMetadata
        );

        // Assert: Null values and empty arrays are recursively removed from metadata
        $storedToken = $this->testUser->getNemesisToken($plainToken);
        $expectedMetadata = ['keep' => 'value', 'nested' => ['keep' => 'data']];
        $this->assertEquals($expectedMetadata, $storedToken->metadata);
    }

    // ==============================================
    // Bulk Token Deletion Tests
    // ==============================================

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

        // Assert: All tokens are permanently removed from database
        $this->assertSame(2, $deletedCount);
        $this->assertEquals(0, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(0, $this->testUser->nemesisTokens()->withTrashed()->count());
    }

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

        // Assert: All tokens are soft deleted (visible only with trashed)
        $this->assertSame(2, $revokedCount);
        $this->assertEquals(0, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(2, $this->testUser->nemesisTokens()->withTrashed()->count());
    }

    // ==============================================
    // Current Token Deletion Tests
    // ==============================================

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

    public function test_delete_current_nemesis_token_returns_false_when_no_token(): void
    {
        // Arrange: Ensure no token is set in the request
        $this->withBearerToken('');

        // Act: Attempt to delete the current token
        $result = $this->testUser->deleteCurrentNemesisToken();

        // Assert: Method returns false because no token was found
        $this->assertFalse($result);
    }

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

    public function test_revoke_current_nemesis_token_returns_false_when_no_token(): void
    {
        // Arrange: Ensure no token is set in the request
        $this->withBearerToken('');

        // Act: Attempt to revoke the current token
        $result = $this->testUser->revokeCurrentNemesisToken();

        // Assert: Method returns false because no token was found
        $this->assertFalse($result);
    }

    // ==============================================
    // Token Existence Tests
    // ==============================================

    public function test_has_nemesis_tokens_returns_false_when_no_tokens(): void
    {
        // Arrange: No tokens created

        // Act & Assert: Check that no tokens exist
        $this->assertFalse($this->testUser->hasNemesisTokens());
    }

    public function test_has_nemesis_tokens_returns_true_when_tokens_exist(): void
    {
        // Arrange: Create a token
        $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);

        // Act & Assert: Check that tokens exist
        $this->assertTrue($this->testUser->hasNemesisTokens());
    }

    public function test_has_nemesis_tokens_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);

        // Act: Soft delete the token
        $tokenModel->delete();

        // Assert: Without trashed returns false (soft deleted not visible)
        $this->assertFalse($this->testUser->hasNemesisTokens());

        // Assert: With trashed returns true (includes soft deleted)
        $this->assertTrue($this->testUser->hasNemesisTokens(withTrashed: true));
    }

    // ==============================================
    // Token Retrieval Tests
    // ==============================================

    public function test_get_nemesis_token_retrieves_token_by_plain_text(): void
    {
        // Arrange: Create a token
        $plainToken = $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);

        // Act: Retrieve the token
        $tokenModel = $this->testUser->getNemesisToken($plainToken);

        // Assert: Token is correctly retrieved
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $this->assertEquals(self::DEFAULT_TOKEN_NAME, $tokenModel->name);
    }

    public function test_get_nemesis_token_returns_null_for_invalid_token(): void
    {
        // Act: Attempt to retrieve an invalid token
        $tokenModel = $this->testUser->getNemesisToken('invalid-token');

        // Assert: Null is returned
        $this->assertNull($tokenModel);
    }

    public function test_get_nemesis_token_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create a token
        $plainToken = $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);
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

    // ==============================================
    // Token Validation Tests
    // ==============================================

    public function test_validate_nemesis_token_returns_true_for_valid_token(): void
    {
        // Arrange: Create a valid token with future expiration
        $plainToken = $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $tokenModel->save();

        // Act & Assert: Token is valid
        $this->assertTrue($this->testUser->validateNemesisToken($plainToken));
    }

    public function test_validate_nemesis_token_returns_false_for_invalid_token(): void
    {
        // Act & Assert: Invalid token returns false
        $this->assertFalse($this->testUser->validateNemesisToken('invalid-token'));
    }

    public function test_validate_nemesis_token_returns_false_for_revoked_token(): void
    {
        // Arrange: Create a token and revoke it
        $plainToken = $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $tokenModel->save();
        $tokenModel->delete();

        // Act & Assert: Revoked token is invalid
        $this->assertFalse($this->testUser->validateNemesisToken($plainToken));
    }

    public function test_validate_nemesis_token_with_include_revoked_includes_revoked_tokens(): void
    {
        // Arrange: Create a token with future expiration and soft delete it
        $plainToken = $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->addDays(10);
        $tokenModel->save();
        $tokenModel->delete();

        // Act & Assert: With includeRevoked returns true (token exists and is not expired)
        $this->assertTrue($this->testUser->validateNemesisToken($plainToken, includeRevoked: true));
    }

    public function test_validate_nemesis_token_with_include_revoked_returns_false_for_expired_revoked_token(): void
    {
        // Arrange: Create a token with past expiration and soft delete it
        $plainToken = $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);
        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = now()->subDay();
        $tokenModel->save();
        $tokenModel->delete();

        // Act & Assert: With includeRevoked returns false (token is expired)
        $this->assertFalse($this->testUser->validateNemesisToken($plainToken, includeRevoked: true));
    }

    // ==============================================
    // Token Touch Tests
    // ==============================================

    public function test_touch_nemesis_token_updates_last_used_at_and_returns_true(): void
    {
        // Arrange: Create a token and store its original last_used_at
        $plainToken = $this->testUser->createNemesisToken(self::DEFAULT_TOKEN_NAME);
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

    public function test_touch_nemesis_token_returns_false_for_invalid_token(): void
    {
        // Act: Attempt to touch an invalid token
        $result = $this->testUser->touchNemesisToken('invalid-token');

        // Assert: Method returns false because token was not found
        $this->assertFalse($result);
    }

    // ==============================================
    // Token Source Filtering Tests
    // ==============================================

    public function test_get_nemesis_tokens_by_source_returns_filtered_tokens(): void
    {
        // Arrange: Create tokens with different sources
        $this->testUser->createNemesisToken('Web Token', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('Mobile Token', self::MOBILE_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('API Token', self::API_TOKEN_SOURCE);

        // Act: Filter tokens by source
        $webTokens = $this->testUser->getNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE);
        $mobileTokens = $this->testUser->getNemesisTokensBySource(self::MOBILE_TOKEN_SOURCE);

        // Assert: Only tokens with matching source are returned
        $this->assertCount(1, $webTokens);
        $this->assertEquals('Web Token', $webTokens->first()->name);
        $this->assertCount(1, $mobileTokens);
        $this->assertEquals('Mobile Token', $mobileTokens->first()->name);
    }

    public function test_get_nemesis_tokens_by_source_with_trashed_includes_soft_deleted_tokens(): void
    {
        // Arrange: Create tokens with same source
        $this->testUser->createNemesisToken('Web Token 1', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('Web Token 2', self::DEFAULT_TOKEN_SOURCE);

        // Arrange: Get the second token and revoke it
        $tokens = $this->testUser->getNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE);
        $this->assertCount(2, $tokens);
        $secondToken = $tokens->last();
        $secondToken->delete();

        // Act: Get tokens by source without trashed
        $webTokensWithoutTrashed = $this->testUser->getNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE);

        // Assert: Without trashed returns only non-deleted tokens
        $this->assertCount(1, $webTokensWithoutTrashed);
        $this->assertEquals('Web Token 1', $webTokensWithoutTrashed->first()->name);

        // Act: Get tokens by source with trashed
        $webTokensWithTrashed = $this->testUser->getNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE, withTrashed: true);

        // Assert: With trashed returns all tokens (including soft deleted)
        $this->assertCount(2, $webTokensWithTrashed);
    }

    // ==============================================
    // Expired Token Revocation Tests
    // ==============================================

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

    // ==============================================
    // Flexible Where Conditions Tests
    // ==============================================

    public function test_revoke_nemesis_tokens_where_with_operator(): void
    {
        // Arrange: Create tokens with different creation dates
        $token1 = $this->testUser->createNemesisToken('Token 1', self::DEFAULT_TOKEN_SOURCE);
        $token2 = $this->testUser->createNemesisToken('Token 2', self::DEFAULT_TOKEN_SOURCE);

        // Arrange: Set token1 creation date to 40 days ago
        $token1Model = $this->testUser->getNemesisToken($token1);
        $token1Model->created_at = Carbon::now()->subDays(40);
        $token1Model->save();

        $cutoffDate = Carbon::now()->subDays(30);

        // Act: Revoke tokens older than cutoff date using operator syntax
        $revokedCount = $this->testUser->revokeNemesisTokensWhere([
            'created_at' => ['<', $cutoffDate]
        ]);

        // Assert: Only the older token is revoked
        $this->assertEquals(1, $revokedCount);

        $token1Model->refresh();
        $token2Model = $this->testUser->getNemesisToken($token2);

        $this->assertNotNull($token1Model->deleted_at);
        $this->assertNull($token2Model->deleted_at);
    }

    public function test_revoke_nemesis_tokens_where_with_array_of_conditions(): void
    {
        // Arrange: Create tokens with various source/name combinations
        $this->testUser->createNemesisToken('web_session', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('mobile_session', self::MOBILE_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('web_admin', self::DEFAULT_TOKEN_SOURCE);

        // Act: Revoke web tokens that are NOT web_admin
        $revokedCount = $this->testUser->revokeNemesisTokensWhere([
            ['source', '=', self::DEFAULT_TOKEN_SOURCE],
            ['name', '!=', 'web_admin']
        ]);

        // Assert: Only web_session (web source, name not web_admin) is revoked
        $this->assertEquals(1, $revokedCount);
        $this->assertEquals(2, $this->testUser->nemesisTokens()->count());
    }

    public function test_revoke_nemesis_tokens_where_with_mixed_formats(): void
    {
        // Arrange: Create tokens for mixed format testing
        $this->testUser->createNemesisToken('test_token', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('other_token', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('test_token', self::MOBILE_TOKEN_SOURCE);

        // Act: Mix simple equality (name) with operator format (source != mobile)
        $revokedCount = $this->testUser->revokeNemesisTokensWhere([
            'name' => 'test_token',
            'source' => ['!=', self::MOBILE_TOKEN_SOURCE]
        ]);

        // Assert: Only web test_token is revoked (mobile test_token remains)
        $this->assertEquals(1, $revokedCount);

        $remainingTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(2, $remainingTokens->count());
        $this->assertTrue($remainingTokens->contains('name', 'other_token'));
        $this->assertTrue($remainingTokens->contains('name', 'test_token'));
    }

    // ==============================================
    // Expired Token Permanent Deletion Tests
    // ==============================================

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

    // ==============================================
    // Token Restoration Tests
    // ==============================================

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

    // ==============================================
    // Token Revocation by Source/Name Tests
    // ==============================================

    public function test_revoke_nemesis_tokens_by_source_revokes_only_matching_source(): void
    {
        // Arrange: Create tokens with different sources
        $this->testUser->createNemesisToken('Web Token 1', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('Web Token 2', self::DEFAULT_TOKEN_SOURCE);
        $mobileToken = $this->testUser->createNemesisToken('Mobile Token', self::MOBILE_TOKEN_SOURCE);
        $apiToken = $this->testUser->createNemesisToken('API Token', self::API_TOKEN_SOURCE);

        // Act: Revoke all web tokens
        $revokedCount = $this->testUser->revokeNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE);

        // Assert: Only web tokens are revoked
        $this->assertEquals(2, $revokedCount);

        // Assert: Web tokens should be soft deleted
        $webTokens = $this->testUser->getNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE, withTrashed: true);
        $this->assertCount(2, $webTokens);
        foreach ($webTokens as $token) {
            $this->assertTrue($token->trashed());
        }

        // Assert: Mobile token should remain active
        $mobileTokenModel = $this->testUser->getNemesisToken($mobileToken);
        $this->assertInstanceOf(NemesisToken::class, $mobileTokenModel);
        $this->assertFalse($mobileTokenModel->trashed());

        // Assert: API token should remain active
        $apiTokenModel = $this->testUser->getNemesisToken($apiToken);
        $this->assertInstanceOf(NemesisToken::class, $apiTokenModel);
        $this->assertFalse($apiTokenModel->trashed());
    }

    public function test_revoke_nemesis_tokens_by_name_revokes_only_matching_name(): void
    {
        // Arrange: Create tokens with different names
        $this->testUser->createNemesisToken('web_session', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('web_session', self::MOBILE_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('api_key', self::API_TOKEN_SOURCE);

        // Act: Revoke all 'web_session' tokens
        $revokedCount = $this->testUser->revokeNemesisTokensByName('web_session');

        // Assert: Both web and mobile 'web_session' tokens are revoked
        $this->assertEquals(2, $revokedCount);

        // Assert: 'api_key' token should remain active
        $apiKeyToken = $this->testUser->getNemesisTokensBySource(self::API_TOKEN_SOURCE)->first();
        $this->assertInstanceOf(NemesisToken::class, $apiKeyToken);
        $this->assertFalse($apiKeyToken->trashed());
    }

    public function test_revoke_nemesis_tokens_by_source_and_name_revokes_specific_tokens(): void
    {
        // Arrange: Create various tokens
        $this->testUser->createNemesisToken('web_session', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('web_session', self::MOBILE_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('web_admin', self::DEFAULT_TOKEN_SOURCE);
        $mobileToken = $this->testUser->createNemesisToken('mobile_session', self::MOBILE_TOKEN_SOURCE);

        // Act: Revoke only web_session tokens from web source
        $revokedCount = $this->testUser->revokeNemesisTokensBySourceAndName(
            self::DEFAULT_TOKEN_SOURCE,
            'web_session'
        );

        // Assert: Only one token revoked
        $this->assertEquals(1, $revokedCount);

        // Assert: Other tokens should remain active
        $allActiveTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(3, $allActiveTokens->count());

        // Assert: Mobile token should be active
        $mobileTokenModel = $this->testUser->getNemesisToken($mobileToken);
        $this->assertInstanceOf(NemesisToken::class, $mobileTokenModel);
        $this->assertFalse($mobileTokenModel->trashed());
    }

    public function test_revoke_all_nemesis_tokens_except_source_keeps_specified_source(): void
    {
        // Arrange: Create tokens with different sources
        $this->testUser->createNemesisToken('Web Token 1', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('Web Token 2', self::DEFAULT_TOKEN_SOURCE);
        $mobileToken = $this->testUser->createNemesisToken('Mobile Token', self::MOBILE_TOKEN_SOURCE);
        $apiToken = $this->testUser->createNemesisToken('API Token', self::API_TOKEN_SOURCE);

        // Act: Revoke all tokens except mobile
        $revokedCount = $this->testUser->revokeAllNemesisTokensExceptSource(self::MOBILE_TOKEN_SOURCE);

        // Assert: 3 tokens revoked (2 web + 1 api)
        $this->assertEquals(3, $revokedCount);

        // Assert: Only mobile token remains active
        $activeTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(1, $activeTokens->count());
        $this->assertEquals('Mobile Token', $activeTokens->first()->name);

        // Assert: Mobile token should be active
        $mobileTokenModel = $this->testUser->getNemesisToken($mobileToken);
        $this->assertInstanceOf(NemesisToken::class, $mobileTokenModel);
        $this->assertFalse($mobileTokenModel->trashed());
    }

    public function test_revoke_nemesis_tokens_where_with_custom_criteria(): void
    {
        // Arrange: Create tokens with different names
        $tokenToRevoke = $this->testUser->createNemesisToken('token_to_revoke', self::DEFAULT_TOKEN_SOURCE);
        $tokenToKeep = $this->testUser->createNemesisToken('token_to_keep', self::DEFAULT_TOKEN_SOURCE);
        $anotherToken = $this->testUser->createNemesisToken('another_token', self::DEFAULT_TOKEN_SOURCE);

        // Act: Revoke tokens with specific name
        $revokedCount = $this->testUser->revokeNemesisTokensWhere([
            'name' => 'token_to_revoke'
        ]);

        // Assert: Only matching token is revoked
        $this->assertEquals(1, $revokedCount);

        // Assert: Get the token models using the actual token values
        $revokedToken = $this->testUser->getNemesisToken($tokenToRevoke, withTrashed: true);
        $keptToken = $this->testUser->getNemesisToken($tokenToKeep);
        $anotherKeptToken = $this->testUser->getNemesisToken($anotherToken);

        $this->assertNotNull($revokedToken);
        $this->assertNotNull($keptToken);
        $this->assertNotNull($anotherKeptToken);

        $this->assertTrue($revokedToken->trashed());
        $this->assertFalse($keptToken->trashed());
        $this->assertFalse($anotherKeptToken->trashed());

        // Assert: Verify token names
        $this->assertEquals('token_to_revoke', $revokedToken->name);
        $this->assertEquals('token_to_keep', $keptToken->name);
        $this->assertEquals('another_token', $anotherKeptToken->name);
    }

    // ==============================================
    // Real World Scenarios
    // ==============================================

    public function test_real_world_scenario_logout_from_all_browsers_keep_mobile(): void
    {
        // Arrange: Simulate user with multiple sessions on different devices
        $browserTokens = [];
        for ($i = 1; $i <= 3; $i++) {
            $browserTokens[] = $this->testUser->createNemesisToken(
                name: 'web_session',
                source: self::DEFAULT_TOKEN_SOURCE
            );
        }

        // Arrange: User also has a mobile app token
        $mobileToken = $this->testUser->createNemesisToken(
            name: 'mobile_session',
            source: self::MOBILE_TOKEN_SOURCE
        );

        // Arrange: User also has an API token for external integrations
        $apiToken = $this->testUser->createNemesisToken(
            name: 'api_key',
            source: self::API_TOKEN_SOURCE
        );

        // Act: User clicks "Logout from all browsers"
        $revokedCount = $this->testUser->revokeNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE);

        // Assert: Only web tokens were revoked
        $this->assertEquals(3, $revokedCount);

        // Assert: All web tokens should be invalid/revoked
        foreach ($browserTokens as $browserToken) {
            $this->assertFalse($this->testUser->validateNemesisToken($browserToken));
            $token = $this->testUser->getNemesisToken($browserToken, withTrashed: true);
            $this->assertTrue($token->trashed());
        }

        // Assert: Mobile token should still be valid
        $this->assertTrue($this->testUser->validateNemesisToken($mobileToken));

        // Assert: API token should still be valid
        $this->assertTrue($this->testUser->validateNemesisToken($apiToken));

        // Assert: Active tokens count should be 2 (mobile + api)
        $activeTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(2, $activeTokens->count());
    }

    public function test_force_delete_variants_permanently_delete_tokens(): void
    {
        // Arrange: Create tokens
        $webToken = $this->testUser->createNemesisToken('Web Token', self::DEFAULT_TOKEN_SOURCE);
        $mobileToken = $this->testUser->createNemesisToken('Mobile Token', self::MOBILE_TOKEN_SOURCE);

        // Act: Force delete web tokens by source
        $deletedCount = $this->testUser->revokeNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE, force: true);

        // Assert: Web token is permanently deleted
        $this->assertEquals(1, $deletedCount);
        $this->assertNull($this->testUser->getNemesisToken($webToken, withTrashed: true));

        // Assert: Mobile token remains
        $this->assertInstanceOf(NemesisToken::class, $this->testUser->getNemesisToken($mobileToken));
    }

    public function test_revoke_nemesis_tokens_by_source_returns_zero_when_no_matching_tokens(): void
    {
        // Arrange: Create only mobile token
        $this->testUser->createNemesisToken('Mobile Token', self::MOBILE_TOKEN_SOURCE);

        // Act: Try to revoke web tokens
        $revokedCount = $this->testUser->revokeNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE);

        // Assert: No tokens were revoked
        $this->assertEquals(0, $revokedCount);

        // Assert: Mobile token still active
        $this->assertEquals(1, $this->testUser->nemesisTokens()->count());
    }

    public function test_chaining_revoke_operations(): void
    {
        // Arrange: Create multiple tokens from various sources
        $this->testUser->createNemesisToken('web_session', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('web_admin', self::DEFAULT_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('mobile_session', self::MOBILE_TOKEN_SOURCE);
        $this->testUser->createNemesisToken('api_key', self::API_TOKEN_SOURCE);

        // Act: First revoke all web tokens, then revoke mobile tokens
        $webRevoked = $this->testUser->revokeNemesisTokensBySource(self::DEFAULT_TOKEN_SOURCE);
        $mobileRevoked = $this->testUser->revokeNemesisTokensBySource(self::MOBILE_TOKEN_SOURCE);

        // Assert: Correct counts for each revocation operation
        $this->assertEquals(2, $webRevoked);
        $this->assertEquals(1, $mobileRevoked);

        // Assert: Only API token remains active after both revocations
        $activeTokens = $this->testUser->nemesisTokens()->get();
        $this->assertEquals(1, $activeTokens->count());
        $this->assertEquals(self::API_TOKEN_SOURCE, $activeTokens->first()->source);
    }

    // ==============================================
    // Helper Methods
    // ==============================================

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
