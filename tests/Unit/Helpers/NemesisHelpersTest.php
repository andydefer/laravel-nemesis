<?php

declare(strict_types=1);

namespace Kani\Nemesis\Tests\Unit\Helpers;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Kani\Nemesis\Models\NemesisToken;
use Kani\Nemesis\NemesisManager;
use Kani\Nemesis\Tests\Support\TestApiClient;
use Kani\Nemesis\Tests\Support\TestCheckPoint;
use Kani\Nemesis\Tests\Support\TestCustomFormatUser;
use Kani\Nemesis\Tests\Support\TestUser;
use Kani\Nemesis\Tests\TestCase;

/**
 * Test suite for Nemesis helper functions.
 *
 * Verifies that all global helper functions work correctly:
 * - nemesis() - Returns the NemesisManager instance
 * - current_token() - Returns the current token model
 * - current_authenticatable() - Returns the authenticated model
 * - current_authenticatable_format() - Returns formatted authenticated data
 *
 * @package Kani\Nemesis\Tests\Unit\Helpers
 */
final class NemesisHelpersTest extends TestCase
{
    private TestUser $user;
    private TestApiClient $apiClient;
    private TestCheckPoint $checkpoint;
    private TestCustomFormatUser $customUser;
    private string $parameterName;
    private string $plainToken;
    private NemesisToken $tokenModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Arrange: Get the configured parameter name for authentication
        $this->parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');

        // Arrange: Create all test model instances
        $this->createTestModels();

        // Arrange: Create a test token for the user model
        $this->createTestToken();
    }

    /**
     * Create all test model instances.
     */
    private function createTestModels(): void
    {
        $this->user = TestUser::create([
            'name' => 'Helper Test User',
            'email' => 'user@test.com'
        ]);

        $this->apiClient = TestApiClient::create([
            'name' => 'API Test Client',
            'api_key' => 'secret-key-123'
        ]);

        $this->checkpoint = TestCheckPoint::create([
            'name' => 'Test Gate',
            'location' => 'Gate B',
            'is_active' => true,
            'last_ping_at' => now(),
        ]);

        $this->customUser = TestCustomFormatUser::create([
            'name' => 'Custom User',
            'email' => 'custom@test.com',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Create a test token for the user model.
     */
    private function createTestToken(): void
    {
        $this->plainToken = $this->user->createNemesisToken('Test Token', 'test');
        $this->tokenModel = $this->user->getNemesisToken($this->plainToken);
    }

    /**
     * Create a fresh request with a properly bound route for testing.
     *
     * @return Request The configured request instance
     */
    private function createFreshRequest(): Request
    {
        $request = Request::create('/test', 'GET');
        $route = new Route('GET', '/test', fn() => null);

        $request->setRouteResolver(fn() => $route);
        $route->bind($request);

        return $request;
    }

    /**
     * Simulate an authenticated request with model, token, and formatted data.
     *
     * @param mixed $model The authenticatable model
     * @param string $token The plain text token
     * @param NemesisToken $tokenModel The token model
     */
    private function simulateAuthenticatedRequest($model, string $token, NemesisToken $tokenModel): void
    {
        $request = $this->createFreshRequest();

        $request->route()->setParameter($this->parameterName, $model);
        $request->route()->setParameter('currentNemesisToken', $tokenModel);

        $request->merge([
            $this->parameterName . 'Format' => $model->nemesisFormat(),
        ]);

        $this->app->instance('request', $request);
    }

    /**
     * Simulate an unauthenticated request.
     */
    private function simulateUnauthenticatedRequest(): void
    {
        $request = $this->createFreshRequest();
        $this->app->instance('request', $request);
    }

    // ============================================================================
    // Tests for nemesis() helper
    // ============================================================================

    /**
     * Test that nemesis() returns the NemesisManager instance.
     */
    public function test_nemesis_helper_returns_manager_instance(): void
    {
        // Act: Call the nemesis helper
        $manager = nemesis();

        // Assert: The returned object is a NemesisManager instance
        $this->assertInstanceOf(NemesisManager::class, $manager);
    }

    /**
     * Test that nemesis() returns the same instance on multiple calls.
     */
    public function test_nemesis_helper_returns_same_instance(): void
    {
        // Act: Call the nemesis helper twice
        $manager1 = nemesis();
        $manager2 = nemesis();

        // Assert: Both calls return the same instance
        $this->assertSame($manager1, $manager2);
    }

    /**
     * Test that nemesis() can validate tokens.
     */
    public function test_nemesis_helper_can_validate_tokens(): void
    {
        // Arrange: Get the nemesis manager
        $manager = nemesis();

        // Act & Assert: Valid token returns true
        $isValid = $manager->validateToken($this->user, $this->plainToken);
        $this->assertTrue($isValid);

        // Act & Assert: Invalid token returns false
        $isValidInvalid = $manager->validateToken($this->user, 'invalid-token');
        $this->assertFalse($isValidInvalid);
    }

    /**
     * Test that nemesis() can create tokens.
     */
    public function test_nemesis_helper_can_create_tokens(): void
    {
        // Arrange: Get the nemesis manager
        $manager = nemesis();

        // Act: Create a new token
        $newToken = $manager->createToken($this->user, 'New Token', 'web', ['read']);

        // Assert: Token is a 64-character string
        $this->assertIsString($newToken);
        $this->assertEquals(64, strlen($newToken));

        // Assert: Token is correctly stored in database
        $tokenModel = $this->user->getNemesisToken($newToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $this->assertEquals('New Token', $tokenModel->name);
        $this->assertEquals('web', $tokenModel->source);
        $this->assertEquals(['read'], $tokenModel->abilities);
    }

    // ============================================================================
    // Tests for current_token() helper
    // ============================================================================

    /**
     * Test that current_token() returns the current token model.
     */
    public function test_current_token_helper_returns_token_model(): void
    {
        // Arrange: Simulate an authenticated request
        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);

        // Act: Get the current token
        $token = current_token();

        // Assert: Token model is returned with correct data
        $this->assertInstanceOf(NemesisToken::class, $token);
        $this->assertEquals($this->tokenModel->id, $token->id);
        $this->assertEquals($this->tokenModel->token_hash, $token->token_hash);
    }

    /**
     * Test that current_token() returns null when no token in request.
     */
    public function test_current_token_helper_returns_null_when_no_token(): void
    {
        // Arrange: Simulate an unauthenticated request
        $this->simulateUnauthenticatedRequest();

        // Act: Get the current token
        $token = current_token();

        // Assert: Null is returned
        $this->assertNull($token);
    }

    /**
     * Test that current_token() can check token abilities.
     */
    public function test_current_token_helper_can_check_abilities(): void
    {
        // Arrange: Create a token with specific abilities
        $tokenWithAbilities = $this->user->createNemesisToken(
            'Abilities Token',
            'test',
            ['read', 'write', 'delete']
        );
        $tokenModel = $this->user->getNemesisToken($tokenWithAbilities);

        // Arrange: Simulate an authenticated request with this token
        $this->simulateAuthenticatedRequest($this->user, $tokenWithAbilities, $tokenModel);

        // Act: Get the current token
        $token = current_token();

        // Assert: Token has the expected abilities
        $this->assertTrue($token->can('read'));
        $this->assertTrue($token->can('write'));
        $this->assertTrue($token->can('delete'));

        // Assert: Token does not have unauthorized abilities
        $this->assertFalse($token->can('admin'));
    }

    /**
     * Test that current_token() returns token metadata.
     */
    public function test_current_token_helper_returns_metadata(): void
    {
        // Arrange: Create a token with metadata
        $metadataToken = $this->user->createNemesisToken(
            'Metadata Token',
            'test',
            null,
            ['user_agent' => 'Mozilla/5.0', 'ip' => '127.0.0.1']
        );
        $tokenModel = $this->user->getNemesisToken($metadataToken);

        // Arrange: Simulate an authenticated request with this token
        $this->simulateAuthenticatedRequest($this->user, $metadataToken, $tokenModel);

        // Act: Get the current token
        $token = current_token();

        // Assert: Metadata is correctly stored and accessible
        $this->assertIsArray($token->metadata);
        $this->assertEquals('Mozilla/5.0', $token->metadata['user_agent']);
        $this->assertEquals('127.0.0.1', $token->metadata['ip']);
    }

    // ============================================================================
    // Tests for current_authenticatable() helper
    // ============================================================================

    /**
     * Test that current_authenticatable() returns User model.
     */
    public function test_current_authenticatable_helper_returns_user(): void
    {
        // Arrange: Simulate an authenticated request with user
        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);

        // Act: Get the current authenticatable
        $authenticatable = current_authenticatable();

        // Assert: User model is returned with correct data
        $this->assertInstanceOf(TestUser::class, $authenticatable);
        $this->assertEquals($this->user->id, $authenticatable->id);
        $this->assertEquals($this->user->name, $authenticatable->name);
        $this->assertEquals($this->user->email, $authenticatable->email);
    }

    /**
     * Test that current_authenticatable() returns ApiClient model.
     */
    public function test_current_authenticatable_helper_returns_api_client(): void
    {
        // Arrange: Create token for API client
        $token = $this->apiClient->createNemesisToken('API Token', 'api');
        $tokenModel = $this->apiClient->getNemesisToken($token);

        // Arrange: Simulate an authenticated request with API client
        $this->simulateAuthenticatedRequest($this->apiClient, $token, $tokenModel);

        // Act: Get the current authenticatable
        $authenticatable = current_authenticatable();

        // Assert: ApiClient model is returned with correct data
        $this->assertInstanceOf(TestApiClient::class, $authenticatable);
        $this->assertEquals($this->apiClient->id, $authenticatable->id);
        $this->assertEquals($this->apiClient->name, $authenticatable->name);
    }

    /**
     * Test that current_authenticatable() returns CheckPoint model.
     */
    public function test_current_authenticatable_helper_returns_checkpoint(): void
    {
        // Arrange: Create token for checkpoint
        $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
        $tokenModel = $this->checkpoint->getNemesisToken($token);

        // Arrange: Simulate an authenticated request with checkpoint
        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);

        // Act: Get the current authenticatable
        $authenticatable = current_authenticatable();

        // Assert: CheckPoint model is returned with correct data
        $this->assertInstanceOf(TestCheckPoint::class, $authenticatable);
        $this->assertEquals($this->checkpoint->id, $authenticatable->id);
        $this->assertEquals($this->checkpoint->name, $authenticatable->name);
        $this->assertEquals($this->checkpoint->location, $authenticatable->location);
    }

    /**
     * Test that current_authenticatable() returns CustomFormatUser model.
     */
    public function test_current_authenticatable_helper_returns_custom_user(): void
    {
        // Arrange: Create token for custom user
        $token = $this->customUser->createNemesisToken('Custom Token', 'web');
        $tokenModel = $this->customUser->getNemesisToken($token);

        // Arrange: Simulate an authenticated request with custom user
        $this->simulateAuthenticatedRequest($this->customUser, $token, $tokenModel);

        // Act: Get the current authenticatable
        $authenticatable = current_authenticatable();

        // Assert: CustomFormatUser model is returned with correct data
        $this->assertInstanceOf(TestCustomFormatUser::class, $authenticatable);
        $this->assertEquals($this->customUser->id, $authenticatable->id);
        $this->assertEquals($this->customUser->name, $authenticatable->name);
        $this->assertEquals($this->customUser->email, $authenticatable->email);
    }

    /**
     * Test that current_authenticatable() returns null when not authenticated.
     */
    public function test_current_authenticatable_helper_returns_null_when_not_authenticated(): void
    {
        // Arrange: Simulate an unauthenticated request
        $this->simulateUnauthenticatedRequest();

        // Act: Get the current authenticatable
        $authenticatable = current_authenticatable();

        // Assert: Null is returned
        $this->assertNull($authenticatable);
    }

    // ============================================================================
    // Tests for current_authenticatable_format() helper
    // ============================================================================

    /**
     * Test that current_authenticatable_format() returns formatted User data.
     */
    public function test_current_authenticatable_format_helper_returns_formatted_user(): void
    {
        // Arrange: Simulate an authenticated request with user
        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);

        // Act: Get the formatted authenticatable data
        $formatted = current_authenticatable_format();

        // Assert: Formatted data matches User format
        $this->assertIsArray($formatted);
        $this->assertEquals($this->user->id, $formatted['id']);
        $this->assertEquals($this->user->name, $formatted['name']);
        $this->assertEquals($this->user->email, $formatted['email']);
        $this->assertEquals('user', $formatted['type']);
    }

    /**
     * Test that current_authenticatable_format() returns formatted ApiClient data.
     */
    public function test_current_authenticatable_format_helper_returns_formatted_api_client(): void
    {
        // Arrange: Create token for API client
        $token = $this->apiClient->createNemesisToken('API Token', 'api');
        $tokenModel = $this->apiClient->getNemesisToken($token);

        // Arrange: Simulate an authenticated request with API client
        $this->simulateAuthenticatedRequest($this->apiClient, $token, $tokenModel);

        // Act: Get the formatted authenticatable data
        $formatted = current_authenticatable_format();

        // Assert: Formatted data matches ApiClient format
        $this->assertIsArray($formatted);
        $this->assertEquals($this->apiClient->id, $formatted['id']);
        $this->assertEquals($this->apiClient->name, $formatted['name']);
        $this->assertEquals('api_client', $formatted['type']);

        // Assert: Sensitive api_key is not exposed
        $this->assertArrayNotHasKey('api_key', $formatted);
    }

    /**
     * Test that current_authenticatable_format() returns formatted CheckPoint data.
     */
    public function test_current_authenticatable_format_helper_returns_formatted_checkpoint(): void
    {
        // Arrange: Create token for checkpoint
        $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
        $tokenModel = $this->checkpoint->getNemesisToken($token);

        // Arrange: Simulate an authenticated request with checkpoint
        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);

        // Act: Get the formatted authenticatable data
        $formatted = current_authenticatable_format();

        // Assert: Formatted data matches CheckPoint format
        $this->assertIsArray($formatted);
        $this->assertEquals($this->checkpoint->id, $formatted['id']);
        $this->assertEquals($this->checkpoint->name, $formatted['name']);
        $this->assertEquals($this->checkpoint->location, $formatted['location']);
        $this->assertEquals('active', $formatted['status']);
        $this->assertEquals('checkpoint', $formatted['type']);
        $this->assertNotNull($formatted['last_seen']);
    }

    /**
     * Test that current_authenticatable_format() returns formatted custom user data.
     */
    public function test_current_authenticatable_format_helper_returns_formatted_custom_user(): void
    {
        // Arrange: Ensure email_verified_at is set
        $this->customUser->email_verified_at = now();
        $this->customUser->save();

        // Arrange: Create token for custom user
        $token = $this->customUser->createNemesisToken('Custom Token', 'web');
        $tokenModel = $this->customUser->getNemesisToken($token);

        // Arrange: Simulate an authenticated request with custom user
        $this->simulateAuthenticatedRequest($this->customUser, $token, $tokenModel);

        // Act: Get the formatted authenticatable data
        $formatted = current_authenticatable_format();

        // Assert: Formatted data uses custom fields
        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('user_id', $formatted);
        $this->assertArrayHasKey('full_name', $formatted);
        $this->assertArrayHasKey('is_verified', $formatted);
        $this->assertArrayHasKey('custom_field', $formatted);
        $this->assertArrayHasKey('type', $formatted);

        // Assert: Field values are correct
        $this->assertEquals($this->customUser->id, $formatted['user_id']);
        $this->assertEquals($this->customUser->name, $formatted['full_name']);
        $this->assertTrue($formatted['is_verified']);
        $this->assertEquals('only_for_api', $formatted['custom_field']);
        $this->assertEquals('custom_user', $formatted['type']);

        // Assert: Email is NOT exposed in custom format
        $this->assertArrayNotHasKey('email', $formatted);
    }

    /**
     * Test that current_authenticatable_format() returns null when not authenticated.
     */
    public function test_current_authenticatable_format_helper_returns_null_when_not_authenticated(): void
    {
        // Arrange: Simulate an unauthenticated request
        $this->simulateUnauthenticatedRequest();

        // Act: Get the formatted authenticatable data
        $formatted = current_authenticatable_format();

        // Assert: Null is returned
        $this->assertNull($formatted);
    }

    // ============================================================================
    // Integration tests - All helpers working together
    // ============================================================================

    /**
     * Test that all helpers work together in a real scenario with User.
     */
    public function test_all_helpers_work_together_for_user(): void
    {
        // Arrange: Simulate an authenticated request with user
        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);

        // Act & Assert: nemesis helper returns manager and validates token
        $manager = nemesis();
        $this->assertInstanceOf(NemesisManager::class, $manager);
        $this->assertTrue($manager->validateToken($this->user, $this->plainToken));

        // Act & Assert: current_token returns the token model
        $token = current_token();
        $this->assertInstanceOf(NemesisToken::class, $token);

        // Act & Assert: current_authenticatable returns the user model
        $authenticatable = current_authenticatable();
        $this->assertInstanceOf(TestUser::class, $authenticatable);

        // Act & Assert: current_authenticatable_format returns formatted data
        $formatted = current_authenticatable_format();
        $this->assertIsArray($formatted);
        $this->assertEquals($authenticatable->id, $formatted['id']);

        // Assert: All data is consistent
        $this->assertEquals($this->user->id, $authenticatable->id);
        $this->assertEquals($this->user->id, $formatted['id']);
        $this->assertEquals($token->tokenable_id, $authenticatable->id);
    }

    /**
     * Test that all helpers work together for CheckPoint.
     */
    public function test_all_helpers_work_together_for_checkpoint(): void
    {
        // Arrange: Create token for checkpoint
        $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
        $tokenModel = $this->checkpoint->getNemesisToken($token);

        // Arrange: Simulate an authenticated request with checkpoint
        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);

        // Act & Assert: nemesis validates token
        $manager = nemesis();
        $this->assertTrue($manager->validateToken($this->checkpoint, $token));

        // Act & Assert: current_token returns token model
        $currentToken = current_token();
        $this->assertInstanceOf(NemesisToken::class, $currentToken);

        // Act & Assert: current_authenticatable returns checkpoint model
        $authenticatable = current_authenticatable();
        $this->assertInstanceOf(TestCheckPoint::class, $authenticatable);

        // Act & Assert: formatted data matches checkpoint format
        $formatted = current_authenticatable_format();
        $this->assertIsArray($formatted);
        $this->assertEquals($authenticatable->id, $formatted['id']);
        $this->assertEquals('checkpoint', $formatted['type']);
        $this->assertEquals($this->checkpoint->location, $formatted['location']);
    }

    /**
     * Test that formatted data differs between User and CheckPoint.
     */
    public function test_formatted_data_differs_between_user_and_checkpoint(): void
    {
        // Arrange: Simulate user authentication
        $userToken = $this->user->createNemesisToken('User Token', 'web');
        $userTokenModel = $this->user->getNemesisToken($userToken);
        $this->simulateAuthenticatedRequest($this->user, $userToken, $userTokenModel);
        $userFormatted = current_authenticatable_format();

        // Arrange: Simulate checkpoint authentication
        $checkpointToken = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
        $checkpointTokenModel = $this->checkpoint->getNemesisToken($checkpointToken);
        $this->simulateAuthenticatedRequest($this->checkpoint, $checkpointToken, $checkpointTokenModel);
        $checkpointFormatted = current_authenticatable_format();

        // Assert: User has email, CheckPoint does not
        $this->assertArrayHasKey('email', $userFormatted);
        $this->assertArrayNotHasKey('email', $checkpointFormatted);

        // Assert: CheckPoint has location, User does not
        $this->assertArrayNotHasKey('location', $userFormatted);
        $this->assertArrayHasKey('location', $checkpointFormatted);

        // Assert: Types are different
        $this->assertEquals('user', $userFormatted['type']);
        $this->assertEquals('checkpoint', $checkpointFormatted['type']);
    }

    /**
     * Test that custom format user has different structure than regular user.
     */
    public function test_custom_format_differs_from_regular_user(): void
    {
        // Arrange: Simulate regular user authentication
        $userToken = $this->user->createNemesisToken('User Token', 'web');
        $userTokenModel = $this->user->getNemesisToken($userToken);
        $this->simulateAuthenticatedRequest($this->user, $userToken, $userTokenModel);
        $userFormatted = current_authenticatable_format();

        // Arrange: Simulate custom user authentication
        $customToken = $this->customUser->createNemesisToken('Custom Token', 'web');
        $customTokenModel = $this->customUser->getNemesisToken($customToken);
        $this->simulateAuthenticatedRequest($this->customUser, $customToken, $customTokenModel);
        $customFormatted = current_authenticatable_format();

        // Assert: Regular user uses 'id' and 'email', custom user uses 'user_id' and 'full_name'
        $this->assertArrayHasKey('id', $userFormatted);
        $this->assertArrayHasKey('email', $userFormatted);
        $this->assertArrayHasKey('user_id', $customFormatted);
        $this->assertArrayHasKey('full_name', $customFormatted);
        $this->assertArrayNotHasKey('email', $customFormatted);

        // Assert: Custom user has additional fields
        $this->assertArrayHasKey('is_verified', $customFormatted);
        $this->assertArrayHasKey('custom_field', $customFormatted);
    }
}
