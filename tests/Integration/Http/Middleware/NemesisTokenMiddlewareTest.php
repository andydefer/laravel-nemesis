<?php

// tests/Integration/Http/Middleware/NemesisTokenMiddlewareTest.php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Integration\Http\Middleware;

use AndyDefer\DomainStructures\Services\HydrationService;
use AndyDefer\Nemesis\Contracts\Configs\NemesisConfigInterface;
use AndyDefer\Nemesis\Models\NemesisToken;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use AndyDefer\Nemesis\Services\NemesisAuthenticationService;
use AndyDefer\Nemesis\Services\NemesisService;
use AndyDefer\Nemesis\Tests\Fixtures\Models\TestUser;
use AndyDefer\Nemesis\Tests\IntegrationTestCase;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

final class NemesisTokenMiddlewareTest extends IntegrationTestCase
{
    private TestUser $user;

    private NemesisConfigInterface $config;

    private NemesisService $service;

    private NemesisAuthenticationService $authService;

    private HydrationService $hydration;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2024, 1, 1, 12, 0, 0));

        $this->hydration = new HydrationService;
        $this->config = $this->app->make(NemesisConfigInterface::class);
        $this->service = $this->app->make(NemesisService::class);
        $this->authService = $this->app->make(NemesisAuthenticationService::class);

        $this->user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Register test routes with the middleware
        Route::middleware('nemesis.token')->get('/test-protected', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::middleware('nemesis.token:read')->get('/test-ability', function () {
            return response()->json(['message' => 'OK']);
        });

        Route::middleware('nemesis.token:admin')->get('/test-admin', function () {
            return response()->json(['message' => 'OK']);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createTokenForUser(
        ?string $name = null,
        ?string $source = null,
        ?DateTimeVO $expiresAt = null
    ): array {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => $name ?? 'Test Token',
            'source' => $source ?? 'web',
            'expires_at' => $expiresAt,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    private function createTokenWithAbilitiesForUser(array $abilities): array
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'API Token',
            'source' => 'api',
            'abilities' => $abilities,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    private function createTokenWithAllowedOriginsForUser(array $origins): array
    {
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'API Token',
            'source' => 'api',
            'allowed_origins' => $origins,
        ]);

        return $this->service->createWithPlainToken($record, $this->user);
    }

    // ============================================================================
    // Authentication Success Tests
    // ============================================================================

    public function test_middleware_allows_request_with_valid_token(): void
    {
        // Arrange: Create a valid token for the user
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request to a protected route with the token
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_without_abilities_and_no_requirement(): void
    {
        // Arrange: Create a token without abilities
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with no ability requirement
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_with_abilities_but_no_requirement(): void
    {
        // Arrange: Create a token with abilities
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);

        // Act: Make a request with no ability requirement
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_attaches_token_to_request(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request to a protected route
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
    }

    public function test_middleware_attaches_formatted_authenticatable_when_implemented(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request to a protected route
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
    }

    // ============================================================================
    // Authentication Failure Tests
    // ============================================================================

    public function test_middleware_returns_401_when_no_token_provided(): void
    {
        // Act: Make a request without a token
        $response = $this->get('/test-protected');

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
        $response->assertJsonStructure(['errorCode', 'message', 'status']);
    }

    public function test_middleware_returns_401_when_token_in_wrong_header(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with the token in the wrong header
        $response = $this->get('/test-protected', [
            'X-API-Key' => $plainToken,
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    public function test_middleware_returns_401_when_invalid_token_provided(): void
    {
        // Act: Make a request with an invalid token
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer invalid-token',
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    public function test_middleware_returns_401_when_token_expired(): void
    {
        // Arrange: Create an expired token
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        [$token, $plainToken] = $this->createTokenForUser(expiresAt: $expiredDate);

        // Act: Make a request with the expired token
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    public function test_middleware_returns_401_when_token_revoked(): void
    {
        // Arrange: Create and then revoke a token
        [$token, $plainToken] = $this->createTokenForUser();
        $this->service->revoke($token);

        // Act: Make a request with the revoked token
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    // ============================================================================
    // Ability Check Tests
    // ============================================================================

    public function test_middleware_allows_request_with_required_ability(): void
    {
        // Arrange: Create a token with the required abilities
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write']);

        // Act: Make a request requiring the 'read' ability
        $response = $this->get('/test-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_with_correct_ability(): void
    {
        // Arrange: Create a token with the 'admin' ability
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['admin']);

        // Act: Make a request requiring the 'admin' ability
        $response = $this->get('/test-admin', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_allows_request_with_multiple_abilities(): void
    {
        // Arrange: Create a token with multiple abilities including 'admin'
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write', 'admin']);

        // Act: Make a request requiring the 'admin' ability
        $response = $this->get('/test-admin', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);
    }

    public function test_middleware_returns_403_when_token_lacks_required_ability(): void
    {
        // Arrange: Create a token without the required ability
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read', 'write']);

        // Act: Make a request requiring the 'admin' ability
        $response = $this->get('/test-admin', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be forbidden
        $response->assertStatus(403);
    }

    public function test_middleware_returns_403_when_token_has_no_abilities_and_ability_required(): void
    {
        // Arrange: Create a token without abilities
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request requiring the 'admin' ability
        $response = $this->get('/test-admin', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be forbidden
        $response->assertStatus(403);
    }

    public function test_middleware_returns_401_when_revoked_token_with_ability(): void
    {
        // Arrange: Create a token with abilities and revoke it
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);
        $this->service->revoke($token);

        // Act: Make a request requiring the 'read' ability
        $response = $this->get('/test-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    public function test_middleware_returns_401_when_expired_token_with_ability(): void
    {
        // Arrange: Create an expired token with abilities
        $expiredDate = new DateTimeVO(Carbon::getTestNow()->subDay()->format('Y-m-d\TH:i:sP'));
        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Expired Token',
            'abilities' => ['read'],
            'expires_at' => $expiredDate,
        ]);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $this->user);

        // Act: Make a request requiring the 'read' ability
        $response = $this->get('/test-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    // ============================================================================
    // CORS Origin Tests
    // ============================================================================

    public function test_middleware_allows_request_when_origin_allowed(): void
    {
        // Arrange: Create a token with allowed origins
        [$token, $plainToken] = $this->createTokenWithAllowedOriginsForUser(['https://allowed.com']);

        // Act: Make a request from the allowed origin
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => 'https://allowed.com',
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
    }

    public function test_middleware_returns_403_when_origin_not_allowed(): void
    {
        // Arrange: Create a token with allowed origins
        [$token, $plainToken] = $this->createTokenWithAllowedOriginsForUser(['https://allowed.com']);

        // Act: Make a request from a disallowed origin
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => 'https://evil.com',
        ]);

        // Assert: The request should be forbidden
        $response->assertStatus(403);
    }

    // ============================================================================
    // Custom Header Tests
    // ============================================================================

    public function test_middleware_accepts_token_in_custom_header(): void
    {
        // Arrange: Configure custom header and create a token
        $originalTokenHeader = config('nemesis.middleware.token_header');
        $originalValidateOrigin = config('nemesis.middleware.validate_origin');

        config()->set('nemesis.middleware.token_header', 'X-API-Key');
        config()->set('nemesis.middleware.validate_origin', false);

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);

        [$token, $plainToken] = $this->createTokenForUser();

        Route::get('/test-custom-header', function () {
            return response()->json(['message' => 'OK']);
        });

        // Act: Make a request with the token in the custom header
        $response = $this->get('/test-custom-header', [
            'X-API-Key' => $plainToken,
        ]);

        // Assert: The request should be successful
        $response->assertStatus(200);
        $response->assertJson(['message' => 'OK']);

        // Cleanup: Restore original configuration
        config()->set('nemesis.middleware.token_header', $originalTokenHeader);
        config()->set('nemesis.middleware.validate_origin', $originalValidateOrigin);

        $this->app->forgetInstance(NemesisConfigInterface::class);
        $this->app->make(NemesisConfigInterface::class);
    }

    // ============================================================================
    // Security Headers Tests
    // ============================================================================

    public function test_middleware_applies_security_headers(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request to a protected route
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be successful and include security headers
        $response->assertStatus(200);
    }

    // ============================================================================
    // CORS Headers Tests
    // ============================================================================

    public function test_middleware_applies_cors_headers_when_origin_validated(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make a request with an origin header
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
            'Origin' => 'https://example.com',
        ]);

        // Assert: The request should be successful with CORS headers
        $response->assertStatus(200);
    }

    // ============================================================================
    // Preflight Request Tests
    // ============================================================================

    public function test_middleware_handles_preflight_request(): void
    {
        // Arrange: Create a valid token
        [$token, $plainToken] = $this->createTokenForUser();

        // Act: Make an OPTIONS preflight request
        $response = $this->call('OPTIONS', '/test-protected', [], [], [], [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_AUTHORIZATION' => 'Bearer '.$plainToken,
        ]);

        // Assert: The preflight request should be successful
        $response->assertStatus(200);
    }

    // ============================================================================
    // Edge Cases Tests
    // ============================================================================

    public function test_middleware_handles_token_with_nonexistent_tokenable_type(): void
    {
        // Arrange: Create a token with a non-existent tokenable type
        NemesisToken::create([
            'token_hash' => hash('sha256', 'bad-token'),
            'tokenable_type' => 'NonExistent\\Model\\Class',
            'tokenable_id' => $this->user->id,
            'name' => 'Bad Token',
            'source' => 'web',
        ]);

        // Act: Make a request with the invalid token
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer bad-token',
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    public function test_middleware_handles_token_with_deleted_tokenable(): void
    {
        // Arrange: Create a user, create a token, then delete the user
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, ['name' => 'Temp Token']);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        // Act: Make a request with the token
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    public function test_middleware_returns_401_when_deleted_tokenable_with_ability(): void
    {
        // Arrange: Create a user with abilities, create a token, then delete the user
        $user = TestUser::create(['name' => 'Temp', 'email' => 'temp@example.com']);

        $record = $this->hydration->hydrate(NemesisTokenRecord::class, [
            'name' => 'Temp Token',
            'abilities' => ['read'],
        ]);
        [$token, $plainToken] = $this->service->createWithPlainToken($record, $user);

        $user->delete();

        // Act: Make a request requiring the 'read' ability
        $response = $this->get('/test-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The request should be unauthorized
        $response->assertStatus(401);
    }

    // ============================================================================
    // Last Used Update Tests
    // ============================================================================

    public function test_middleware_updates_last_used_on_successful_authentication(): void
    {
        // Arrange: Create a token and verify last_used_at is null
        [$token, $plainToken] = $this->createTokenForUser();
        $this->assertNull($token->last_used_at);

        // Act: Make a request with the token
        $response = $this->get('/test-protected', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The token should be updated with a last_used_at timestamp
        $token->refresh();
        $response->assertStatus(200);
        $this->assertNotNull($token->last_used_at);
    }

    public function test_middleware_updates_last_used_with_ability(): void
    {
        // Arrange: Create a token with abilities and verify last_used_at is null
        [$token, $plainToken] = $this->createTokenWithAbilitiesForUser(['read']);
        $this->assertNull($token->last_used_at);

        // Act: Make a request requiring the 'read' ability
        $response = $this->get('/test-ability', [
            'Authorization' => 'Bearer '.$plainToken,
        ]);

        // Assert: The token should be updated with a last_used_at timestamp
        $token->refresh();
        $response->assertStatus(200);
        $this->assertNotNull($token->last_used_at);
    }
}
