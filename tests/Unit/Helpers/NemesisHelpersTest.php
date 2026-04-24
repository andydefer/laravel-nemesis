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

        $this->parameterName = config('nemesis.middleware.parameter_name', 'nemesisAuth');

        $this->createTestModels();
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

    public function test_nemesis_helper_returns_manager_instance(): void
    {
        $manager = nemesis();

        $this->assertInstanceOf(NemesisManager::class, $manager);
    }

    public function test_nemesis_helper_returns_same_instance(): void
    {
        $manager1 = nemesis();
        $manager2 = nemesis();

        $this->assertSame($manager1, $manager2);
    }

    public function test_nemesis_helper_can_validate_tokens(): void
    {
        $manager = nemesis();

        $isValid = $manager->validateToken($this->user, $this->plainToken);
        $this->assertTrue($isValid);

        $isValidInvalid = $manager->validateToken($this->user, 'invalid-token');
        $this->assertFalse($isValidInvalid);
    }

    public function test_nemesis_helper_can_create_tokens(): void
    {
        $manager = nemesis();

        $newToken = $manager->createToken($this->user, 'New Token', 'web', ['read']);

        $this->assertIsString($newToken);
        $this->assertEquals(64, strlen($newToken));

        $tokenModel = $this->user->getNemesisToken($newToken);
        $this->assertInstanceOf(NemesisToken::class, $tokenModel);
        $this->assertEquals('New Token', $tokenModel->name);
        $this->assertEquals('web', $tokenModel->source);
        $this->assertEquals(['read'], $tokenModel->abilities);
    }

    // ============================================================================
    // Tests for current_token() helper
    // ============================================================================

    public function test_current_token_helper_returns_token_model(): void
    {
        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);

        $token = current_token();

        $this->assertInstanceOf(NemesisToken::class, $token);
        $this->assertEquals($this->tokenModel->id, $token->id);
        $this->assertEquals($this->tokenModel->token_hash, $token->token_hash);
    }

    public function test_current_token_helper_returns_null_when_no_token(): void
    {
        $this->simulateUnauthenticatedRequest();

        $token = current_token();

        $this->assertNull($token);
    }

    public function test_current_token_helper_can_check_abilities(): void
    {
        $tokenWithAbilities = $this->user->createNemesisToken(
            'Abilities Token',
            'test',
            ['read', 'write', 'delete']
        );
        $tokenModel = $this->user->getNemesisToken($tokenWithAbilities);

        $this->simulateAuthenticatedRequest($this->user, $tokenWithAbilities, $tokenModel);

        $token = current_token();

        $this->assertTrue($token->can('read'));
        $this->assertTrue($token->can('write'));
        $this->assertTrue($token->can('delete'));
        $this->assertFalse($token->can('admin'));
    }

    public function test_current_token_helper_returns_metadata(): void
    {
        $metadataToken = $this->user->createNemesisToken(
            'Metadata Token',
            'test',
            null,
            ['user_agent' => 'Mozilla/5.0', 'ip' => '127.0.0.1']
        );
        $tokenModel = $this->user->getNemesisToken($metadataToken);

        $this->simulateAuthenticatedRequest($this->user, $metadataToken, $tokenModel);

        $token = current_token();

        $this->assertIsArray($token->metadata);
        $this->assertEquals('Mozilla/5.0', $token->metadata['user_agent']);
        $this->assertEquals('127.0.0.1', $token->metadata['ip']);
    }

    // ============================================================================
    // Tests for current_authenticatable() helper
    // ============================================================================

    public function test_current_authenticatable_helper_returns_user(): void
    {
        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);

        $authenticatable = current_authenticatable();

        $this->assertInstanceOf(TestUser::class, $authenticatable);
        $this->assertEquals($this->user->id, $authenticatable->id);
        $this->assertEquals($this->user->name, $authenticatable->name);
        $this->assertEquals($this->user->email, $authenticatable->email);
    }

    public function test_current_authenticatable_helper_returns_api_client(): void
    {
        $token = $this->apiClient->createNemesisToken('API Token', 'api');
        $tokenModel = $this->apiClient->getNemesisToken($token);

        $this->simulateAuthenticatedRequest($this->apiClient, $token, $tokenModel);

        $authenticatable = current_authenticatable();

        $this->assertInstanceOf(TestApiClient::class, $authenticatable);
        $this->assertEquals($this->apiClient->id, $authenticatable->id);
        $this->assertEquals($this->apiClient->name, $authenticatable->name);
    }

    public function test_current_authenticatable_helper_returns_checkpoint(): void
    {
        $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
        $tokenModel = $this->checkpoint->getNemesisToken($token);

        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);

        $authenticatable = current_authenticatable();

        $this->assertInstanceOf(TestCheckPoint::class, $authenticatable);
        $this->assertEquals($this->checkpoint->id, $authenticatable->id);
        $this->assertEquals($this->checkpoint->name, $authenticatable->name);
        $this->assertEquals($this->checkpoint->location, $authenticatable->location);
    }

    public function test_current_authenticatable_helper_returns_custom_user(): void
    {
        $token = $this->customUser->createNemesisToken('Custom Token', 'web');
        $tokenModel = $this->customUser->getNemesisToken($token);

        $this->simulateAuthenticatedRequest($this->customUser, $token, $tokenModel);

        $authenticatable = current_authenticatable();

        $this->assertInstanceOf(TestCustomFormatUser::class, $authenticatable);
        $this->assertEquals($this->customUser->id, $authenticatable->id);
        $this->assertEquals($this->customUser->name, $authenticatable->name);
        $this->assertEquals($this->customUser->email, $authenticatable->email);
    }

    public function test_current_authenticatable_helper_returns_null_when_not_authenticated(): void
    {
        $this->simulateUnauthenticatedRequest();

        $authenticatable = current_authenticatable();

        $this->assertNull($authenticatable);
    }

    // ============================================================================
    // Tests for current_authenticatable_format() helper
    // ============================================================================

    public function test_current_authenticatable_format_helper_returns_formatted_user(): void
    {
        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);

        $formatted = current_authenticatable_format();

        $this->assertIsArray($formatted);
        $this->assertEquals($this->user->id, $formatted['id']);
        $this->assertEquals($this->user->name, $formatted['name']);
        $this->assertEquals($this->user->email, $formatted['email']);
        $this->assertEquals('user', $formatted['type']);
    }

    public function test_current_authenticatable_format_helper_returns_formatted_api_client(): void
    {
        $token = $this->apiClient->createNemesisToken('API Token', 'api');
        $tokenModel = $this->apiClient->getNemesisToken($token);

        $this->simulateAuthenticatedRequest($this->apiClient, $token, $tokenModel);

        $formatted = current_authenticatable_format();

        $this->assertIsArray($formatted);
        $this->assertEquals($this->apiClient->id, $formatted['id']);
        $this->assertEquals($this->apiClient->name, $formatted['name']);
        $this->assertEquals('api_client', $formatted['type']);
        $this->assertArrayNotHasKey('api_key', $formatted);
    }

    public function test_current_authenticatable_format_helper_returns_formatted_checkpoint(): void
    {
        $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
        $tokenModel = $this->checkpoint->getNemesisToken($token);

        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);

        $formatted = current_authenticatable_format();

        $this->assertIsArray($formatted);
        $this->assertEquals($this->checkpoint->id, $formatted['id']);
        $this->assertEquals($this->checkpoint->name, $formatted['name']);
        $this->assertEquals($this->checkpoint->location, $formatted['location']);
        $this->assertEquals('active', $formatted['status']);
        $this->assertEquals('checkpoint', $formatted['type']);
        $this->assertNotNull($formatted['last_seen']);
    }

    public function test_current_authenticatable_format_helper_returns_formatted_custom_user(): void
    {
        $this->customUser->email_verified_at = now();
        $this->customUser->save();

        $token = $this->customUser->createNemesisToken('Custom Token', 'web');
        $tokenModel = $this->customUser->getNemesisToken($token);

        $this->simulateAuthenticatedRequest($this->customUser, $token, $tokenModel);

        $formatted = current_authenticatable_format();

        $this->assertIsArray($formatted);
        $this->assertArrayHasKey('user_id', $formatted);
        $this->assertArrayHasKey('full_name', $formatted);
        $this->assertArrayHasKey('is_verified', $formatted);
        $this->assertArrayHasKey('custom_field', $formatted);
        $this->assertArrayHasKey('type', $formatted);

        $this->assertEquals($this->customUser->id, $formatted['user_id']);
        $this->assertEquals($this->customUser->name, $formatted['full_name']);
        $this->assertTrue($formatted['is_verified']);
        $this->assertEquals('only_for_api', $formatted['custom_field']);
        $this->assertEquals('custom_user', $formatted['type']);
        $this->assertArrayNotHasKey('email', $formatted);
    }

    public function test_current_authenticatable_format_helper_returns_null_when_not_authenticated(): void
    {
        $this->simulateUnauthenticatedRequest();

        $formatted = current_authenticatable_format();

        $this->assertNull($formatted);
    }

    // ============================================================================
    // Integration tests - All helpers working together
    // ============================================================================

    public function test_all_helpers_work_together_for_user(): void
    {
        $this->simulateAuthenticatedRequest($this->user, $this->plainToken, $this->tokenModel);

        $manager = nemesis();
        $this->assertInstanceOf(NemesisManager::class, $manager);
        $this->assertTrue($manager->validateToken($this->user, $this->plainToken));

        $token = current_token();
        $this->assertInstanceOf(NemesisToken::class, $token);

        $authenticatable = current_authenticatable();
        $this->assertInstanceOf(TestUser::class, $authenticatable);

        $formatted = current_authenticatable_format();
        $this->assertIsArray($formatted);
        $this->assertEquals($authenticatable->id, $formatted['id']);

        $this->assertEquals($this->user->id, $authenticatable->id);
        $this->assertEquals($this->user->id, $formatted['id']);
        $this->assertEquals($token->tokenable_id, $authenticatable->id);
    }

    public function test_all_helpers_work_together_for_checkpoint(): void
    {
        $token = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
        $tokenModel = $this->checkpoint->getNemesisToken($token);

        $this->simulateAuthenticatedRequest($this->checkpoint, $token, $tokenModel);

        $manager = nemesis();
        $this->assertTrue($manager->validateToken($this->checkpoint, $token));

        $currentToken = current_token();
        $this->assertInstanceOf(NemesisToken::class, $currentToken);

        $authenticatable = current_authenticatable();
        $this->assertInstanceOf(TestCheckPoint::class, $authenticatable);

        $formatted = current_authenticatable_format();
        $this->assertIsArray($formatted);
        $this->assertEquals($authenticatable->id, $formatted['id']);
        $this->assertEquals('checkpoint', $formatted['type']);
        $this->assertEquals($this->checkpoint->location, $formatted['location']);
    }

    public function test_formatted_data_differs_between_user_and_checkpoint(): void
    {
        $userToken = $this->user->createNemesisToken('User Token', 'web');
        $userTokenModel = $this->user->getNemesisToken($userToken);
        $this->simulateAuthenticatedRequest($this->user, $userToken, $userTokenModel);
        $userFormatted = current_authenticatable_format();

        $checkpointToken = $this->checkpoint->createNemesisToken('Checkpoint Token', 'kiosk');
        $checkpointTokenModel = $this->checkpoint->getNemesisToken($checkpointToken);
        $this->simulateAuthenticatedRequest($this->checkpoint, $checkpointToken, $checkpointTokenModel);
        $checkpointFormatted = current_authenticatable_format();

        $this->assertArrayHasKey('email', $userFormatted);
        $this->assertArrayNotHasKey('email', $checkpointFormatted);

        $this->assertArrayNotHasKey('location', $userFormatted);
        $this->assertArrayHasKey('location', $checkpointFormatted);

        $this->assertEquals('user', $userFormatted['type']);
        $this->assertEquals('checkpoint', $checkpointFormatted['type']);
    }

    public function test_custom_format_differs_from_regular_user(): void
    {
        $userToken = $this->user->createNemesisToken('User Token', 'web');
        $userTokenModel = $this->user->getNemesisToken($userToken);
        $this->simulateAuthenticatedRequest($this->user, $userToken, $userTokenModel);
        $userFormatted = current_authenticatable_format();

        $customToken = $this->customUser->createNemesisToken('Custom Token', 'web');
        $customTokenModel = $this->customUser->getNemesisToken($customToken);
        $this->simulateAuthenticatedRequest($this->customUser, $customToken, $customTokenModel);
        $customFormatted = current_authenticatable_format();

        $this->assertArrayHasKey('id', $userFormatted);
        $this->assertArrayHasKey('email', $userFormatted);
        $this->assertArrayHasKey('user_id', $customFormatted);
        $this->assertArrayHasKey('full_name', $customFormatted);
        $this->assertArrayNotHasKey('email', $customFormatted);

        $this->assertArrayHasKey('is_verified', $customFormatted);
        $this->assertArrayHasKey('custom_field', $customFormatted);
    }
}
