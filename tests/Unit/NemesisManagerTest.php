<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Kani\Nemesis\Contracts\MustNemesis;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\NemesisManager;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for the NemesisManager class.
 *
 * Verifies that the manager provides a convenient facade for all token operations
 * including creation, validation, retrieval, deletion, revocation, and management
 * across multiple authenticatable models.
 */
final class NemesisManagerTest extends TestCase
{
    private const FROZEN_TEST_TIMESTAMP = '2025-01-01 12:00:00';
    private const DEFAULT_TOKEN_NAME = 'Test Token';
    private const DEFAULT_TOKEN_SOURCE = 'web';
    private const MOBILE_TOKEN_SOURCE = 'mobile';
    private const API_TOKEN_SOURCE = 'api';

    private NemesisManager $manager;
    private TestUser $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2025, 1, 1, 12, 0, 0));

        $this->manager = new NemesisManager();
        $this->testUser = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->testUser->exists) {
            $this->testUser->nemesisTokens()->forceDelete();
        }

        Carbon::setTestNow();
        parent::tearDown();
    }

    // ==============================================
    // Token Creation Tests
    // ==============================================

    public function test_create_token_returns_string(): void
    {
        // Arrange: Manager and user are ready

        // Act: Create a new token
        $token = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        // Assert: Token is a non-empty string
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function test_create_token_stores_token_in_database(): void
    {
        // Arrange: Manager and user are ready

        // Act: Create a new token
        $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        // Assert: Exactly one token exists in database
        $this->assertEquals(1, $this->testUser->nemesisTokens()->count());
    }

    public function test_create_token_with_abilities(): void
    {
        // Arrange: Define abilities for the token
        $abilities = ['read', 'write'];

        // Act: Create token with specific abilities
        $token = $this->manager->createToken(
            $this->testUser,
            'API Token',
            self::API_TOKEN_SOURCE,
            $abilities
        );

        // Assert: Token abilities are correctly stored
        $tokenModel = $this->testUser->getNemesisToken($token);
        $this->assertEquals($abilities, $tokenModel->abilities);
    }

    public function test_create_token_with_metadata(): void
    {
        // Arrange: Define metadata for the token
        $metadata = ['ip' => '127.0.0.1', 'device' => 'test'];

        // Act: Create token with custom metadata
        $token = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE,
            null,
            $metadata
        );

        // Assert: Token metadata is correctly stored
        $tokenModel = $this->testUser->getNemesisToken($token);
        $this->assertEquals($metadata, $tokenModel->metadata);
    }

    // ==============================================
    // Token Validation Tests
    // ==============================================

    public function test_validate_token_returns_true_for_valid_token(): void
    {
        // Arrange: Create a valid token
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        // Act: Validate the token
        $isValid = $this->manager->validateToken($this->testUser, $plainToken);

        // Assert: Token is considered valid
        $this->assertTrue($isValid);
    }

    public function test_validate_token_returns_false_for_invalid_token(): void
    {
        // Arrange: Invalid token string

        // Act: Validate a non-existent token
        $isValid = $this->manager->validateToken($this->testUser, 'invalid-token');

        // Assert: Token is considered invalid
        $this->assertFalse($isValid);
    }

    // ==============================================
    // Token Model Retrieval Tests
    // ==============================================

    public function test_get_token_model_returns_token(): void
    {
        // Arrange: Create a token
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        // Act: Retrieve the token model
        $tokenModel = $this->manager->getTokenModel($plainToken);

        // Assert: Correct token model is returned
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $this->assertEquals(self::DEFAULT_TOKEN_NAME, $tokenModel->name);
    }

    public function test_get_token_model_returns_null_for_invalid_token(): void
    {
        // Arrange: Invalid token string

        // Act: Retrieve model for non-existent token
        $tokenModel = $this->manager->getTokenModel('invalid-token');

        // Assert: Null is returned
        $this->assertNull($tokenModel);
    }

    public function test_get_tokenable_model_returns_model(): void
    {
        // Arrange: Create a token
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        // Act: Retrieve the token's owning model
        $model = $this->manager->getTokenableModel($plainToken);

        // Assert: Correct model is returned
        $this->assertInstanceOf(Model::class, $model);
        $this->assertEquals($this->testUser->id, $model->id);
    }

    public function test_get_tokenable_model_returns_null_for_expired_token(): void
    {
        // Arrange: Create and expire a token
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = Carbon::now()->subDay();
        $tokenModel->save();

        // Act: Retrieve tokenable model for expired token
        $model = $this->manager->getTokenableModel($plainToken);

        // Assert: Null is returned (expired tokens are considered invalid)
        $this->assertNull($model);
    }

    // ==============================================
    // Token Deletion Tests
    // ==============================================

    public function test_delete_token_returns_true_when_token_deleted(): void
    {
        // Arrange: Create a token
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        // Act: Delete the token
        $result = $this->manager->deleteToken($this->testUser, $plainToken);

        // Assert: Deletion succeeded and token count is zero
        $this->assertTrue($result);
        $this->assertEquals(0, $this->testUser->nemesisTokens()->count());
    }

    public function test_delete_token_returns_false_for_invalid_token(): void
    {
        // Arrange: Invalid token string

        // Act: Attempt to delete non-existent token
        $result = $this->manager->deleteToken($this->testUser, 'invalid-token');

        // Assert: Deletion failed
        $this->assertFalse($result);
    }

    public function test_delete_all_tokens_deletes_all(): void
    {
        // Arrange: Create multiple tokens
        $this->manager->createToken($this->testUser, 'Token 1', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'Token 2', self::DEFAULT_TOKEN_SOURCE);

        // Act: Delete all tokens
        $deletedCount = $this->manager->deleteAllTokens($this->testUser);

        // Assert: All tokens are deleted
        $this->assertEquals(2, $deletedCount);
        $this->assertEquals(0, $this->testUser->nemesisTokens()->count());
    }

    // ==============================================
    // Token Revocation Tests
    // ==============================================

    public function test_revoke_tokens_by_source_revokes_only_matching_source(): void
    {
        // Arrange: Create tokens with different sources
        $this->manager->createToken($this->testUser, 'Web Token', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'Web Token 2', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'Mobile Token', self::MOBILE_TOKEN_SOURCE);

        // Act: Revoke all web tokens
        $revokedCount = $this->manager->revokeTokensBySource($this->testUser, self::DEFAULT_TOKEN_SOURCE);

        // Assert: Only web tokens were revoked (2 out of 3)
        $this->assertEquals(2, $revokedCount);
        $this->assertEquals(1, $this->testUser->nemesisTokens()->count());
    }

    public function test_revoke_tokens_by_source_with_force_permanently_deletes(): void
    {
        // Arrange: Create a token
        $this->manager->createToken($this->testUser, 'Web Token', self::DEFAULT_TOKEN_SOURCE);

        // Act: Force delete the token (permanent deletion)
        $revokedCount = $this->manager->revokeTokensBySource($this->testUser, self::DEFAULT_TOKEN_SOURCE, true);

        // Assert: Token is permanently deleted (not just soft-deleted)
        $this->assertEquals(1, $revokedCount);
        $this->assertEquals(0, $this->testUser->nemesisTokens()->withTrashed()->count());
    }

    public function test_revoke_tokens_by_name_revokes_only_matching_name(): void
    {
        // Arrange: Create tokens with various names
        $this->manager->createToken($this->testUser, 'web_session', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'web_session', self::MOBILE_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'api_key', self::API_TOKEN_SOURCE);

        // Act: Revoke all tokens named 'web_session'
        $revokedCount = $this->manager->revokeTokensByName($this->testUser, 'web_session');

        // Assert: Both web_session tokens are revoked
        $this->assertEquals(2, $revokedCount);
        $this->assertEquals(1, $this->testUser->nemesisTokens()->count());
    }

    public function test_revoke_tokens_by_source_and_name_revokes_specific_tokens(): void
    {
        // Arrange: Create tokens with different source/name combinations
        $this->manager->createToken($this->testUser, 'web_session', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'web_admin', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'web_session', self::MOBILE_TOKEN_SOURCE);

        // Act: Revoke web_session tokens from web source only
        $revokedCount = $this->manager->revokeTokensBySourceAndName(
            $this->testUser,
            self::DEFAULT_TOKEN_SOURCE,
            'web_session'
        );

        // Assert: Only one token matches both criteria
        $this->assertEquals(1, $revokedCount);
        $this->assertEquals(2, $this->testUser->nemesisTokens()->count());
    }

    public function test_revoke_all_tokens_except_source_keeps_specified_source(): void
    {
        // Arrange: Create tokens from different sources
        $this->manager->createToken($this->testUser, 'Web Token', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'Mobile Token', self::MOBILE_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'API Token', self::API_TOKEN_SOURCE);

        // Act: Revoke all tokens except mobile ones
        $revokedCount = $this->manager->revokeAllTokensExceptSource($this->testUser, self::MOBILE_TOKEN_SOURCE);

        // Assert: Only mobile token remains
        $this->assertEquals(2, $revokedCount);
        $this->assertEquals(1, $this->testUser->nemesisTokens()->count());
        $this->assertEquals(
            self::MOBILE_TOKEN_SOURCE,
            $this->testUser->nemesisTokens()->first()->source
        );
    }

    public function test_revoke_tokens_where_with_operator(): void
    {
        // Arrange: Create tokens with different creation dates
        $token1 = $this->manager->createToken($this->testUser, 'Token 1', self::DEFAULT_TOKEN_SOURCE);
        $token2 = $this->manager->createToken($this->testUser, 'Token 2', self::DEFAULT_TOKEN_SOURCE);

        $token1Model = $this->testUser->getNemesisToken($token1);
        $token1Model->created_at = Carbon::now()->subDays(40);
        $token1Model->save();

        $cutoffDate = Carbon::now()->subDays(30);

        // Act: Revoke tokens older than cutoff date using operator syntax
        $revokedCount = $this->manager->revokeTokensWhere($this->testUser, [
            'created_at' => ['<', $cutoffDate]
        ]);

        // Assert: Only the older token is revoked
        $this->assertEquals(1, $revokedCount);

        $token1Model->refresh();
        $this->assertNotNull($token1Model->deleted_at);
    }

    // ==============================================
    // Global Token Operations Tests
    // ==============================================

    public function test_revoke_expired_tokens_global(): void
    {
        // Arrange: Create another user and tokens with one expired token
        $secondUser = TestUser::create(['name' => 'User 2', 'email' => 'user2@test.com']);

        $token1 = $this->manager->createToken($this->testUser, 'Token 1', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($secondUser, 'Token 2', self::DEFAULT_TOKEN_SOURCE);

        $token1Model = $this->testUser->getNemesisToken($token1);
        $token1Model->expires_at = Carbon::now()->subDay();
        $token1Model->save();

        // Act: Revoke all expired tokens across all users
        $revokedCount = $this->manager->revokeExpiredTokens();

        // Assert: Only the expired token is revoked
        $this->assertEquals(1, $revokedCount);

        $token1Model->refresh();
        $this->assertNotNull($token1Model->deleted_at);
    }

    public function test_revoke_tokens_older_than(): void
    {
        // Arrange: Create tokens with different ages
        $oldToken = $this->manager->createToken($this->testUser, 'Old Token', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'New Token', self::DEFAULT_TOKEN_SOURCE);

        $oldTokenModel = $this->testUser->getNemesisToken($oldToken);
        $oldTokenModel->created_at = Carbon::now()->subDays(60);
        $oldTokenModel->save();

        $cutoffDate = Carbon::now()->subDays(30);

        // Act: Revoke all tokens created before cutoff date
        $revokedCount = $this->manager->revokeTokensOlderThan($cutoffDate);

        // Assert: Only the older token is revoked
        $this->assertEquals(1, $revokedCount);

        $oldTokenModel->refresh();
        $this->assertNotNull($oldTokenModel->deleted_at);
    }

    // ==============================================
    // Token Information Tests
    // ==============================================

    public function test_get_tokens_by_source(): void
    {
        // Arrange: Create tokens from different sources
        $this->manager->createToken($this->testUser, 'Web Token', self::DEFAULT_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'Mobile Token', self::MOBILE_TOKEN_SOURCE);
        $this->manager->createToken($this->testUser, 'Web Token 2', self::DEFAULT_TOKEN_SOURCE);

        // Act: Retrieve all web source tokens
        $webTokens = $this->manager->getTokensBySource($this->testUser, self::DEFAULT_TOKEN_SOURCE);

        // Assert: Only tokens from web source are returned
        $this->assertCount(2, $webTokens);
        foreach ($webTokens as $token) {
            $this->assertEquals(self::DEFAULT_TOKEN_SOURCE, $token->source);
        }
    }

    public function test_is_token_valid_returns_true_for_valid_token(): void
    {
        // Arrange: Create a valid token
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        // Act: Check if token is valid
        $isValid = $this->manager->isTokenValid($plainToken);

        // Assert: Token is considered valid
        $this->assertTrue($isValid);
    }

    public function test_is_token_valid_returns_false_for_expired_token(): void
    {
        // Arrange: Create and expire a token
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $tokenModel->expires_at = Carbon::now()->subDay();
        $tokenModel->save();

        // Act: Check if expired token is valid
        $isValid = $this->manager->isTokenValid($plainToken);

        // Assert: Expired token is invalid
        $this->assertFalse($isValid);
    }

    public function test_token_has_ability_returns_true_when_token_has_ability(): void
    {
        // Arrange: Create token with multiple abilities
        $plainToken = $this->manager->createToken(
            $this->testUser,
            'API Token',
            self::API_TOKEN_SOURCE,
            ['read', 'write', 'delete']
        );

        // Act: Check if token has 'write' ability
        $hasAbility = $this->manager->tokenHasAbility($plainToken, 'write');

        // Assert: Token possesses the requested ability
        $this->assertTrue($hasAbility);
    }

    public function test_token_has_ability_returns_false_when_token_lacks_ability(): void
    {
        // Arrange: Create token with limited abilities
        $plainToken = $this->manager->createToken(
            $this->testUser,
            'API Token',
            self::API_TOKEN_SOURCE,
            ['read']
        );

        // Act: Check if token has 'delete' ability
        $hasAbility = $this->manager->tokenHasAbility($plainToken, 'delete');

        // Assert: Token does not have the requested ability
        $this->assertFalse($hasAbility);
    }

    public function test_get_token_expiration_returns_expiration_date(): void
    {
        // Arrange: Create a token with default expiration
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        // Act: Retrieve token's expiration date
        $expiration = $this->manager->getTokenExpiration($plainToken);

        // Assert: Expiration is a valid DateTimeInterface object
        $this->assertInstanceOf(DateTimeInterface::class, $expiration);
    }

    public function test_get_token_expiration_returns_null_for_invalid_token(): void
    {
        // Arrange: Invalid token string

        // Act: Attempt to get expiration of non-existent token
        $expiration = $this->manager->getTokenExpiration('invalid-token');

        // Assert: Null is returned
        $this->assertNull($expiration);
    }

    public function test_touch_token_updates_last_used_at(): void
    {
        // Arrange: Create a token and store original last_used timestamp
        $plainToken = $this->manager->createToken(
            $this->testUser,
            self::DEFAULT_TOKEN_NAME,
            self::DEFAULT_TOKEN_SOURCE
        );

        $tokenModel = $this->testUser->getNemesisToken($plainToken);
        $originalLastUsed = $tokenModel->last_used_at;

        Carbon::setTestNow(now()->addHour());

        // Act: Touch the token (update last_used_at)
        $result = $this->manager->touchToken($plainToken);

        // Assert: Last used timestamp was updated
        $this->assertTrue($result);
        $tokenModel->refresh();
        $this->assertNotEquals($originalLastUsed, $tokenModel->last_used_at);
    }

    public function test_touch_token_returns_false_for_invalid_token(): void
    {
        // Arrange: Invalid token string

        // Act: Attempt to touch non-existent token
        $result = $this->manager->touchToken('invalid-token');

        // Assert: Operation failed
        $this->assertFalse($result);
    }

    // ==============================================
    // Real World Scenarios
    // ==============================================

    public function test_real_world_scenario_logout_from_all_browsers_keep_mobile(): void
    {
        // Arrange: Simulate user with multiple sessions across devices
        for ($i = 1; $i <= 3; $i++) {
            $this->manager->createToken($this->testUser, 'web_session', self::DEFAULT_TOKEN_SOURCE);
        }

        $mobileToken = $this->manager->createToken($this->testUser, 'mobile_session', self::MOBILE_TOKEN_SOURCE);
        $apiToken = $this->manager->createToken($this->testUser, 'api_key', self::API_TOKEN_SOURCE);

        // Act: Logout from all browser sessions (web source)
        $revokedCount = $this->manager->revokeTokensBySource($this->testUser, self::DEFAULT_TOKEN_SOURCE);

        // Assert: Only web tokens are revoked, mobile and API remain active
        $this->assertEquals(3, $revokedCount);
        $this->assertTrue($this->manager->isTokenValid($mobileToken));
        $this->assertTrue($this->manager->isTokenValid($apiToken));
        $this->assertEquals(2, $this->testUser->nemesisTokens()->count());
    }

    public function test_real_world_scenario_revoke_old_inactive_tokens(): void
    {
        // Arrange: Create an old inactive token and a new active token
        $oldToken = $this->manager->createToken($this->testUser, 'Old Token', self::DEFAULT_TOKEN_SOURCE);
        $oldTokenModel = $this->testUser->getNemesisToken($oldToken);
        $oldTokenModel->last_used_at = Carbon::now()->subDays(60);
        $oldTokenModel->save();

        $recentToken = $this->manager->createToken($this->testUser, 'Recent Token', self::DEFAULT_TOKEN_SOURCE);

        $cutoffDate = Carbon::now()->subDays(30);

        // Act: Revoke tokens not used for 30 days
        $revokedCount = $this->manager->revokeTokensWhere($this->testUser, [
            'last_used_at' => ['<', $cutoffDate]
        ]);

        // Assert: Only the old inactive token is revoked
        $this->assertEquals(1, $revokedCount);
        $this->assertFalse($this->manager->isTokenValid($oldToken));
        $this->assertTrue($this->manager->isTokenValid($recentToken));
    }
}
